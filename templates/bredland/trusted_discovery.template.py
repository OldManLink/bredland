import base64
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

def execute_routeros_script(
        base_url,
        script_name,
        post,
):
    return post(
        base_url + '/rest/system/script/run',
        {
            '.id': script_name,
        },
        )

def post_json(
        url,
        body,
        headers,
        context,
        open_request,
):
    request_headers = dict(headers)
    request_headers['Content-Type'] = 'application/json'

    request = urllib.request.Request(
        url,
        data=json.dumps(
            body,
            separators=(',', ':'),
        ).encode('utf-8'),
        headers=request_headers,
        method='POST',
    )

    response = open_request(
        request,
        context=context,
    )

    return response.status == 200

def load_routeros_rest_credentials(
        credentials_file,
):
    values = {}

    with open(credentials_file, 'r') as file:
        for line in file:
            line = line.strip()

            if not line:
                continue

            key, value = line.split('=', 1)
            values[key] = value

    return {
        'username': values[
            'MIKROTIK_REST_USER'
        ],
        'password': values[
            'MIKROTIK_REST_PASSWORD'
        ],
    }

def routeros_rest_authorization(
        username,
        password,
):
    credentials = '{}:{}'.format(
        username,
        password,
    ).encode('utf-8')

    encoded = base64.b64encode(
        credentials
    ).decode('ascii')

    return 'Basic {}'.format(
        encoded
    )

def create_routeros_rest_tls_context(
        ca_file,
):
    return ssl.create_default_context(
        cafile=ca_file,
    )

def create_routeros_rest_poster(
        credentials,
        context,
        open_request,
        post_json_function,
):
    authorization = routeros_rest_authorization(
        credentials['username'],
        credentials['password'],
    )

    def post(
            url,
            body,
    ):
        return post_json_function(
            url,
            body,
            {
                'Authorization': authorization,
            },
            context,
            open_request,
        )

    return post

def create_routeros_action_executor(
        base_url,
        post,
):
    def execute(script_name):
        return execute_routeros_script(
            base_url,
            script_name,
            post,
        )

    return execute

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
                    rendered_script = trusted_script_renderer(
                        script_body
                    )
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
                self.send_response(400)
                self.send_header(
                    'Access-Control-Allow-Origin',
                    allowed_origin,
                )
                self.send_header(
                    'Content-Length',
                    '0',
                )
                self.end_headers()
                return

            if not isinstance(request, dict):
                self.send_response(400)
                self.send_header(
                    'Access-Control-Allow-Origin',
                    allowed_origin,
                )
                self.send_header(
                    'Content-Length',
                    '0',
                )
                self.end_headers()
                return

            resolution = request.get('resolution')
            token = request.get('token')

            if not isinstance(resolution, str):
                self.send_response(400)
                self.send_header(
                    'Access-Control-Allow-Origin',
                    allowed_origin,
                )
                self.send_header(
                    'Content-Length',
                    '0',
                )
                self.end_headers()
                return

            if not isinstance(token, str):
                self.send_response(400)
                self.send_header(
                    'Access-Control-Allow-Origin',
                    allowed_origin,
                )
                self.send_header(
                    'Content-Length',
                    '0',
                )
                self.end_headers()
                return

            if capability_registry is None:
                self.send_response(500)
                self.send_header(
                    'Access-Control-Allow-Origin',
                    allowed_origin,
                )
                self.send_header(
                    'Content-Length',
                    '0',
                )
                self.end_headers()
                return

            script_name = capability_registry.consume(
                resolution,
                token,
            )

            if script_name is None:
                self.send_response(400)
                self.send_header(
                    'Access-Control-Allow-Origin',
                    allowed_origin,
                )
                self.send_header(
                    'Content-Length',
                    '0',
                )
                self.end_headers()
                return

            if action_executor is None:
                self.send_response(500)
                self.send_header(
                    'Access-Control-Allow-Origin',
                    allowed_origin,
                )
                self.send_header(
                    'Content-Length',
                    '0',
                )
                self.end_headers()
                return

            try:
                succeeded = action_executor(
                    script_name
                )
            except Exception:
                self.send_response(500)
                self.send_header(
                    'Access-Control-Allow-Origin',
                    allowed_origin,
                )
                self.send_header(
                    'Content-Length',
                    '0',
                )
                self.end_headers()
                return
                
            if not succeeded:
                self.send_response(500)
                self.send_header(
                    'Access-Control-Allow-Origin',
                    allowed_origin,
                )
                self.send_header(
                    'Content-Length',
                    '0',
                )
                self.end_headers()
                return

            self.send_response(200)
            self.send_header(
                'Content-Length',
                '0',
            )
            self.send_header(
                'Access-Control-Allow-Origin',
                allowed_origin,
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
