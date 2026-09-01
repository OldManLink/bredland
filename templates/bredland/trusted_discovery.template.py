import json
import secrets
import ssl
import sys
import time
import threading
import urllib.request

from html.parser import HTMLParser
from http.server import BaseHTTPRequestHandler
from http.server import ThreadingHTTPServer
from routeros_rest import create_routeros_action_executor
from routeros_rest import create_routeros_rest_poster
from routeros_rest import create_routeros_rest_tls_context
from routeros_rest import load_routeros_rest_credentials
from routeros_rest import post_json
from routeros_rest import create_routeros_rest_getter
from routeros_rest import routeros_update_available
from routeros_rest import get_json

TRUSTED_BASE_URL = '__BREDLAND_TRUSTED_BASE_URL__'
TRUSTED_ALLOWED_ORIGIN = '__BREDLAND_TRUSTED_ALLOWED_ORIGIN__'
TRUSTED_SCRIPT_PATH = '__BREDLAND_TRUSTED_SCRIPT_PATH__'
TRUSTED_STYLESHEET_PATH = '__BREDLAND_TRUSTED_STYLESHEET_PATH__'
TRUSTED_SCRIPT_FILE = '/usr/local/lib/bredland/static/trusted.js'
TRUSTED_STYLESHEET_FILE = '/usr/local/lib/bredland/static/trusted.css'
TRUSTED_BIND_HOST = '0.0.0.0'
TRUSTED_PORT = 8081
TRUSTED_CERT_FILE = '/etc/bredland/tls/fullchain.pem'
TRUSTED_KEY_FILE = '/etc/bredland/tls/privkey.pem'
MIKROTIK_REST_BASE_URL = '__MIKROTIK_REST_BASE_URL__'
MIKROTIK_REST_CREDENTIALS_FILE = '/etc/bredland/mikrotik-rest/credentials.env'
MIKROTIK_REST_CA_FILE = '/etc/bredland/mikrotik-rest/ca.pem'

class TrustedDiscoveryServer(ThreadingHTTPServer):
    tls_context = None

    def process_request_thread(
        self,
        request,
        client_address,
    ):
        if self.tls_context is not None:
            request = self.tls_context.wrap_socket(
                request,
                server_side=True,
            )

        ThreadingHTTPServer.process_request_thread(
            self,
            request,
            client_address,
        )

    def handle_error(self, request, client_address):
        exception = sys.exc_info()[1]

        if isinstance(
                exception,
                (BrokenPipeError, ConnectionResetError),
        ):
            return

        ThreadingHTTPServer.handle_error(
            self,
            request,
            client_address,
        )

class ResolutionParser(HTMLParser):
    def __init__(self):
        HTMLParser.__init__(self)
        self.resolutions = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)

        if 'data-resolution' in attributes:
            self.resolutions.append(
                attributes['data-resolution']
            )

class CapabilityRegistry:
    def __init__(self, now):
        self.now = now
        self.capabilities = {}
        self.lock = threading.Lock()

    def register(
        self,
        resolution,
        token,
        script_name,
        expires_at,
    ):
        self.capabilities[token] = {
            'resolution': resolution,
            'script_name': script_name,
            'expires_at': expires_at,
        }

    def consume(
            self,
            resolution,
            token,
    ):
        with self.lock:
            capability = self.capabilities.get(token)

            if capability is None:
                return None

            if capability['resolution'] != resolution:
                return None

            if self.now() >= capability['expires_at']:
                del self.capabilities[token]
                return None

            del self.capabilities[token]

            return capability['script_name']

class ActionGuard:
    def __init__(self, now, cooldown):
        self.now = now
        self.cooldown = cooldown
        self.claims = {}
        self.lock = threading.Lock()

    def claim(self, resolution):
        with self.lock:
            claim = self.claims.get(
                resolution
            )

            if claim is None and resolution in self.claims:
                return False

            if claim is not None:
                if self.now() < claim:
                    return False

                del self.claims[resolution]

            self.claims[resolution] = None
            return True

    def release(self, resolution):
        with self.lock:
            self.claims.pop(
                resolution,
                None,
            )

    def complete(self, resolution):
        with self.lock:
            self.claims[resolution] = (
                    self.now() + self.cooldown
            )

def resolutions_from_noc_html(html):
    parser = ResolutionParser()
    parser.feed(html)
    return parser.resolutions

def supported_rendered_resolutions(resolutions):
    return [
        resolution
        for resolution in resolutions
        if routeros_script_for_resolution(resolution) is not None
    ]

def issue_capabilities(
        resolutions,
        token_generator,
        registry,
        expires_at,
):
    capabilities = {}

    for resolution in resolutions:
        token = token_generator()
        script_name = routeros_script_for_resolution(
            resolution
        )

        registry.register(
            resolution,
            token,
            script_name,
            expires_at,
        )

        capabilities[resolution] = token

    return capabilities

def render_trusted_script(
        script_body,
        base_url,
        noc_html_loader,
        token_generator,
        registry,
        expires_at,
):
    noc_html = noc_html_loader()

    resolutions = resolutions_from_noc_html(
        noc_html
    )

    supported_resolutions = supported_rendered_resolutions(
        resolutions
    )

    capabilities = issue_capabilities(
        supported_resolutions,
        token_generator,
        registry,
        expires_at,
    )

    capability_json = json.dumps(
        capabilities,
    )

    banner_lines = script_body.splitlines(
        True
    )

    if len(banner_lines) >= 3:
        banner = ''.join(
            banner_lines[:3]
        )

        script_body = ''.join(
            banner_lines[3:]
        )

        return (
            '{}\n'
            'window.TRUSTED_BASE_URL = "{}";\n'
            'window.TRUSTED_CAPABILITIES = {};\n{}'
            .format(
                banner,
                base_url,
                capability_json,
                script_body,
            )
        )

    return (
        'window.TRUSTED_BASE_URL = "{}";\n'
        'window.TRUSTED_CAPABILITIES = {};\n{}'
        .format(
            base_url,
            capability_json,
            script_body,
        )
    )

def fetch_noc_html(
    allowed_origin,
    open_url,
):
    response = open_url(
        allowed_origin + '/'
    )

    return response.read().decode('utf-8')

def generate_capability_token(token_bytes):
    return token_bytes(32).hex()

def create_capability_token():
    return generate_capability_token(
        secrets.token_bytes,
    )

def capability_expiry(
        now,
        ttl,
):
    return now() + ttl

def create_trusted_script_renderer(
    base_url,
    noc_html_loader,
    token_generator,
    registry,
    expires_at,
):
    def render(script_body):
        return render_trusted_script(
            script_body,
            base_url,
            noc_html_loader,
            token_generator,
            registry,
            expires_at(),
        )

    return render


def main():
    server = create_configured_server(
        TRUSTED_BIND_HOST,
        TRUSTED_PORT,
    )

    server.serve_forever()

def render_discovery_response(script_url, stylesheet_url):
    return json.dumps(
        {
            'assets': {
                'script': script_url,
                'stylesheet': stylesheet_url,
            },
        },
        separators=(',', ':'),
    )

def routeros_script_for_resolution(resolution):
    scripts = {
        'install-routeros-update': 'noc-trusted-action-test',
    }

    return scripts.get(resolution)

def create_configured_server(
    host,
    port,
):
    with open(TRUSTED_SCRIPT_FILE, 'r') as file:
        script_body = file.read()

    with open(TRUSTED_STYLESHEET_FILE, 'r') as file:
        stylesheet_body = file.read()

    capability_registry = CapabilityRegistry(
        time.time,
    )

    action_guard = ActionGuard(
        time.time,
        30,
    )

    def load_noc_html():
        return fetch_noc_html(
            TRUSTED_ALLOWED_ORIGIN,
            urllib.request.urlopen,
        )

    def expires_at():
        return capability_expiry(
            time.time,
            60,
        )

    trusted_script_renderer = create_trusted_script_renderer(
        TRUSTED_BASE_URL,
        load_noc_html,
        create_capability_token,
        capability_registry,
        expires_at,
    )

    credentials = load_routeros_rest_credentials(
        MIKROTIK_REST_CREDENTIALS_FILE,
    )

    routeros_tls_context = (
        create_routeros_rest_tls_context(
            MIKROTIK_REST_CA_FILE,
        )
    )

    routeros_poster = create_routeros_rest_poster(
        credentials,
        routeros_tls_context,
        urllib.request.urlopen,
        post_json,
    )

    routeros_getter = create_routeros_rest_getter(
        credentials,
        routeros_tls_context,
        urllib.request.urlopen,
        get_json,
    )

    def action_validator(resolution):
        if resolution != 'install-routeros-update':
            return False

        return routeros_update_available(
            MIKROTIK_REST_BASE_URL,
            routeros_getter,
        )

    action_executor = create_routeros_action_executor(
        MIKROTIK_REST_BASE_URL,
        routeros_poster,
    )

    server = create_server(
        host,
        port,
        TRUSTED_BASE_URL,
        TRUSTED_ALLOWED_ORIGIN,
        TRUSTED_SCRIPT_PATH,
        script_body,
        TRUSTED_STYLESHEET_PATH,
        stylesheet_body,
        action_executor,
        capability_registry,
        trusted_script_renderer,
        action_validator,
        action_guard,
    )

    context = ssl.SSLContext(
        ssl.PROTOCOL_TLS_SERVER
    )

    context.load_cert_chain(
        TRUSTED_CERT_FILE,
        TRUSTED_KEY_FILE,
    )

    server.tls_context = context

    return server

def create_server(
    host,
    port,
    base_url,
    allowed_origin,
    script_path,
    script_body,
    stylesheet_path,
    stylesheet_body,
    action_executor,
    capability_registry,
    trusted_script_renderer,
    action_validator,
    action_guard,
):
    script_url = base_url + script_path
    stylesheet_url = base_url + stylesheet_path

    response_body = render_discovery_response(
        script_url,
        stylesheet_url,
    ).encode('utf-8')

    class DiscoveryHandler(BaseHTTPRequestHandler):
        def do_GET(self):
            if self.path == script_path:
                rendered_script = script_body
                if trusted_script_renderer is not None:
                    rendered_script = trusted_script_renderer(script_body)
                body = rendered_script.encode('utf-8')

                self.send_response(200)
                self.send_header(
                    'Content-Type',
                    'application/javascript',
                )
                self.send_header(
                    'Cache-Control',
                    'no-store',
                )
                self.send_header(
                    'Content-Length',
                    str(len(body)),
                )
                self.end_headers()
                self.wfile.write(body)
                return

            if self.path == stylesheet_path:
                body = stylesheet_body.encode('utf-8')

                self.send_response(200)
                self.send_header(
                    'Content-Type',
                    'text/css',
                )
                self.send_header(
                    'Cache-Control',
                    'no-store',
                )
                self.send_header(
                    'Content-Length',
                    str(len(body)),
                )
                self.end_headers()
                self.wfile.write(body)
                return

            if self.path != '/probe':
                self.send_error(404)
                return

            self.send_response(200)
            self.send_header(
                'Content-Type',
                'application/json',
            )
            self.send_header(
                'Cache-Control',
                'no-store',
            )
            self.send_header(
                'Access-Control-Allow-Origin',
                allowed_origin,
            )
            self.send_header(
                'Content-Length',
                str(len(response_body)),
            )
            self.end_headers()
            self.wfile.write(response_body)

        def do_POST(self):
            if self.path != '/action':
                self.send_error(404)
                return

            origin = self.headers.get('Origin')

            if origin != allowed_origin:
                self.send_error(403)
                return

            content_length = int(
                self.headers.get(
                    'Content-Length',
                    '0',
                )
            )

            request_body = self.rfile.read(
                content_length
            )

            try:
                request = json.loads(
                    request_body.decode('utf-8')
                )
            except ValueError:
                self._send_action_response(400)
                return

            if not isinstance(request, dict):
                self._send_action_response(400)
                return

            resolution = request.get('resolution')
            token = request.get('token')

            if not isinstance(resolution, str):
                self._send_action_response(400)
                return

            if not isinstance(token, str):
                self._send_action_response(400)
                return

            if capability_registry is None:
                self._send_action_response(500)
                return

            script_name = capability_registry.consume(
                resolution,
                token,
            )

            if script_name is None:
                self._send_action_response(400)
                return

            try:
                valid = action_validator(
                    resolution
                )
            except Exception as error:
                sys.stderr.write(
                    'Trusted action validator failed: '
                    'resolution={!r}, exception={}\n'.format(
                        resolution,
                        type(error).__name__,
                    )
                )

                self._send_action_response(503)
                return

            if not valid:
                self._send_action_response(409)
                return

            if action_executor is None:
                self._send_action_response(500)
                return

            if not action_guard.claim(
                    resolution
            ):
                self._send_action_response(423)
                return

            try:
                succeeded = action_executor(
                    script_name
                )
            except Exception as error:
                sys.stderr.write(
                    'Trusted action executor failed: '
                    'resolution={!r}, script={!r}, exception={}\n'.format(
                        resolution,
                        script_name,
                        type(error).__name__,
                    )
                )

                action_guard.release(
                    resolution
                )

                self._send_action_response(500)
                return

            if not succeeded:
                action_guard.release(
                    resolution
                )

                self._send_action_response(500)
                return

            action_guard.complete(
                resolution
            )

            self._send_action_response(200)

        def _send_action_response(self, status):
            self.send_response(status)
            self.send_header(
                'Access-Control-Allow-Origin',
                allowed_origin,
            )
            self.send_header(
                'Content-Length',
                '0',
            )
            self.end_headers()

        def do_OPTIONS(self):
            if self.path != '/action':
                self.send_error(404)
                return

            origin = self.headers.get('Origin')

            if origin != allowed_origin:
                self.send_error(403)
                return

            self.send_response(204)
            self.send_header(
                'Access-Control-Allow-Origin',
                allowed_origin,
            )
            self.send_header(
                'Access-Control-Allow-Methods',
                'POST',
            )
            self.send_header(
                'Access-Control-Allow-Headers',
                'Content-Type',
            )
            self.send_header(
                'Content-Length',
                '0',
            )
            self.end_headers()

        def log_message(self, format, *args):
            pass

    return TrustedDiscoveryServer(
        (host, port),
        DiscoveryHandler,
    )

if __name__ == '__main__':
    main()
