import json
import os
import secrets
import socket
import ssl
import sys
import time
import threading
import urllib.error
import urllib.request

from builtins import (bool, BrokenPipeError, ConnectionResetError, dict, isinstance)
from html.parser import HTMLParser
from http.server import BaseHTTPRequestHandler
from http.server import ThreadingHTTPServer
from routeros_rest import (create_routeros_action_executor, create_routeros_rest_poster, create_routeros_rest_tls_context,
                           load_routeros_rest_credentials, post_json, create_routeros_rest_getter, routeros_update_available,
                           routerboot_update_available, get_json)

TRUSTED_BASE_URL = '__BREDLAND_TRUSTED_BASE_URL__'
TRUSTED_ALLOWED_ORIGIN = '__BREDLAND_TRUSTED_ALLOWED_ORIGIN__'
TRUSTED_SCRIPT_FILE = '/usr/local/lib/bredland/static/trusted.js'
TRUSTED_STYLESHEET_FILE = '/usr/local/lib/bredland/static/trusted.css'
TRUSTED_BIND_HOST = '0.0.0.0'
TRUSTED_PORT = 8081
TRUSTED_CERT_FILE = '/etc/bredland/tls/fullchain.pem'
TRUSTED_KEY_FILE = '/etc/bredland/tls/privkey.pem'
MIKROTIK_REST_BASE_URL = '__MIKROTIK_REST_BASE_URL__'
MIKROTIK_REST_CREDENTIALS_FILE = '/etc/bredland/mikrotik-rest/credentials.env'
MIKROTIK_REST_CA_FILE = '/etc/bredland/mikrotik-rest/ca.pem'
RESOLUTIONS_FILE = '/etc/bredland/resolutions.json'
ACTION_RESULT_TTL_SECONDS = 300
TRUSTED_ACTION_DEFINITIONS = {
    'install-routeros-update': {
        'script': 'noc-install-routeros-update',
        'confirmation': 'Install the available RouterOS update?',
    },

    'install-routerboot-update': {
        'script': 'noc-install-routerboot-update',
        'confirmation': 'Install the available RouterBOOT firmware update?',
    },
}

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
        with self.lock:
            expired = [
                existing_token
                for existing_token, capability
                in self.capabilities.items()
                if self.now() >= capability['expires_at']
            ]

            for existing_token in expired:
                del self.capabilities[existing_token]

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

class ActionResultRegistry:
    def __init__(self, now):
        self.now = now
        self.results = {}
        self.lock = threading.Lock()

    def create(
            self,
            request_id,
            expires_at,
    ):
        with self.lock:
            expired = [
                key
                for key, result in self.results.items()
                if self.now() >= result['expires_at']
            ]

            for key in expired:
                del self.results[key]

            self.results[request_id] = {
                'status': 'pending',
                'expires_at': expires_at,
            }

    def get(
            self,
            request_id,
    ):
        with self.lock:
            result = self.results.get(
                request_id
            )

            if result is None:
                return None

            if self.now() >= result['expires_at']:
                del self.results[request_id]
                return None

            return {
                'status': result['status'],
            }

    def succeed(
            self,
            request_id,
    ):
        with self.lock:
            result = self.results.get(
                request_id
            )

            if result is None:
                return False

            result['status'] = 'succeeded'
            return True

    def fail(
            self,
            request_id,
    ):
        with self.lock:
            result = self.results.get(
                request_id
            )

            if result is None:
                return False

            result['status'] = 'failed'
            return True

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

def current_supported_resolutions(
        noc_url,
        open_url,
):
    return supported_rendered_resolutions(
        resolutions_from_noc_html(
            fetch_noc_html(
                noc_url,
                open_url,
            )
        )
    )

def load_resolution_hook(
        path,
        resolution,
):
    if not os.path.exists(path):
        return None

    try:
        with open(path, 'r') as handle:
            hooks = json.load(
                handle
            )
    except ValueError:
        raise ValueError(
            'Invalid resolutions JSON'
        )

    hook = hooks.get(
        resolution
    )

    if hook is None:
        return None

    if (
            not isinstance(hook, dict)
            or 'socket' not in hook
            or 'host' not in hook
            or 'port' not in hook
            or not isinstance(
                hook['socket'],
                str,
            )
            or not isinstance(
                hook['host'],
                str,
            )
            or not isinstance(
                hook['port'],
                int,
            )
            or hook['port'] < 1
            or hook['port'] > 65535
    ):
        raise ValueError(
            'Invalid resolution hook'
        )

    return hook

def execute_resolution_hook(
        hook,
        socket_factory=socket.socket,
):
    connection = socket_factory(
        socket.AF_UNIX,
        socket.SOCK_STREAM,
    )

    try:
        connection.connect(
            hook['socket']
        )

        connection.sendall(
            (
                'start {} {}\n'.format(
                    hook['host'],
                    hook['port'],
                )
            ).encode('utf-8')
        )

        response = connection.recv(
            4096
        ).decode(
            'utf-8'
        ).strip()

        return response == 'ok'
    except OSError:
        return False
    finally:
        connection.close()

def execute_configured_resolution_hook(
        path,
        resolution,
        hook_executor,
        logger=lambda message: None,
):
    try:
        hook = load_resolution_hook(
            path,
            resolution,
        )
    except ValueError:
        logger(
            'Pre-action hook configuration failed for {}'.format(
                resolution
            )
        )
        raise

    if hook is None:
        logger(
            'No pre-action hook configured for {}'.format(
                resolution
            )
        )
        return True

    logger(
        'Pre-action hook configured for {}'.format(
            resolution
        )
    )

    succeeded = hook_executor(
        hook
    )

    if succeeded:
        logger(
            'Pre-action hook succeeded for {}'.format(
                resolution
            )
        )
    else:
        logger(
            'Pre-action hook failed for {}'.format(
                resolution
            )
        )

    return succeeded

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
        resolutions,
        base_url,
        token_generator,
        registry,
        expires_at,
        server_time,
):
    capabilities = issue_capabilities(
        resolutions,
        token_generator,
        registry,
        expires_at,
    )

    capability_json = json.dumps(
        capabilities,
    )

    server_time_millis = int(
        server_time() * 1000
    )

    actions = []

    for resolution in resolutions:
        actions.append(
            "render_trusted_action(\n"
            "    {!r},\n"
            "    {!r}\n"
            ");".format(
                resolution,
                confirmation_for_resolution(resolution),
            )
        )

    trusted_actions_placeholder = (
            '__'
            + 'TRUSTED_ACTIONS'
            + '__'
    )

    script_body = script_body.replace(
        trusted_actions_placeholder,
        '\n\n'.join(actions),
    )

    banner_lines = []
    body_lines = script_body.splitlines(
        True
    )

    while (
            body_lines
            and body_lines[0].startswith('// ')
    ):
        banner_lines.append(
            body_lines.pop(0)
        )

    banner = ''.join(
        banner_lines
    )

    script_body = ''.join(
        body_lines
    )

    if banner:
        return (
            '{}\n'
            'window.TRUSTED_BASE_URL = "{}";\n'
            'window.TRUSTED_CAPABILITIES = {};\n'
            'window.TRUSTED_SERVER_TIME = {};\n{}'
            .format(
                banner,
                base_url,
                capability_json,
                server_time_millis,
                script_body,
            )
        )

    return (
        'window.TRUSTED_BASE_URL = "{}";\n'
        'window.TRUSTED_CAPABILITIES = {};\n'
        'window.TRUSTED_SERVER_TIME = {};\n{}'
        .format(
            base_url,
            capability_json,
            server_time_millis,
            script_body,
        )
    )

def render_trusted_stylesheet(stylesheet_body, has_actions):
    if has_actions:
        return stylesheet_body

    marker = '/* Trusted action styles */'

    return stylesheet_body.split(
        marker,
        1,
    )[0]

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
    token_generator,
    registry,
    expires_at,
    server_time,
):
    def render(script_body, resolutions):
        return render_trusted_script(
            script_body,
            resolutions,
            base_url,
            token_generator,
            registry,
            expires_at(),
            server_time,
        )

    return render

def prune_expired_assets(
        assets,
        now,
):
    expired = [
        path
        for path, asset in assets.items()
        if now >= asset[2]
    ]

    for path in expired:
        del assets[path]

def main():
    server = create_configured_server(
        TRUSTED_BIND_HOST,
        TRUSTED_PORT,
    )

    server.serve_forever()

def render_discovery_response(
        stylesheet_url,
        script_url=None,
):
    assets = [
        stylesheet_url,
    ]

    if script_url is not None:
        assets.append(
            script_url
        )

    return json.dumps(
        {
            'assets': assets,
        },
        separators=(',', ':'),
    )

def create_asset_path():
    return '/' + secrets.token_hex(16)

def create_request_id():
    return secrets.token_hex(16)

def routeros_script_for_resolution(resolution):
    action = TRUSTED_ACTION_DEFINITIONS.get(resolution)

    if action is None:
        return None

    return action['script']

def confirmation_for_resolution(resolution):
    action = TRUSTED_ACTION_DEFINITIONS.get(resolution)

    if action is None:
        return None

    return action['confirmation']

def log_trusted_action_hook(message):
    sys.stderr.write(
        message + '\n'
    )

def parse_action_content_length(value):
    try:
        content_length = int(value)
    except ValueError:
        return None

    if content_length <= 0 or content_length > 4096:
        return None

    return content_length

def send_content_length(handler, body):
    handler.send_header(
        'Content-Length',
        str(len(body))
    )

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
        90,
    )

    def expires_at():
        return capability_expiry(
            time.time,
            60,
        )

    trusted_script_renderer = create_trusted_script_renderer(
        TRUSTED_BASE_URL,
        create_capability_token,
        capability_registry,
        expires_at,
        time.time,
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
        if resolution == 'install-routeros-update':
            return routeros_update_available(
                MIKROTIK_REST_BASE_URL,
                routeros_getter,
            )

        if resolution == 'install-routerboot-update':
            return routerboot_update_available(
                MIKROTIK_REST_BASE_URL,
                routeros_getter,
            )

        return False

    action_executor = create_routeros_action_executor(
        MIKROTIK_REST_BASE_URL,
        routeros_poster,
    )

    def action_hook(resolution):
        return execute_configured_resolution_hook(
            RESOLUTIONS_FILE,
            resolution,
            execute_resolution_hook,
            log_trusted_action_hook,
        )

    def current_resolutions():
        return current_supported_resolutions(
            'https://noc.arcanel.se',
            urllib.request.urlopen,
        )

    action_result_registry = ActionResultRegistry(
        time.time,
    )

    server = create_server(
        host,
        port,
        TRUSTED_BASE_URL,
        TRUSTED_ALLOWED_ORIGIN,
        script_body,
        stylesheet_body,
        action_executor,
        capability_registry,
        action_result_registry,
        trusted_script_renderer,
        action_validator,
        action_guard,
        create_asset_path,
        current_resolutions,
        action_hook,
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
    script_body,
    stylesheet_body,
    action_executor,
    capability_registry,
    action_result_registry,
    trusted_script_renderer,
    action_validator,
    action_guard,
    asset_path_factory,
    current_resolutions,
    action_hook=None,
    asset_now=None,
):
    generated_assets = {}

    if asset_now is None:
        asset_now = time.monotonic

    class DiscoveryHandler(BaseHTTPRequestHandler):
        def do_GET(self):
            asset = generated_assets.pop(
                self.path,
                None,
            )

            if asset is not None:
                asset_type, resolutions, expires_at = asset

                if asset_now() >= expires_at:
                    asset_type = None
            else:
                asset_type = None

            if asset_type == 'script':
                rendered_script = script_body

                if trusted_script_renderer is not None:
                    rendered_script = trusted_script_renderer(
                        script_body,
                        resolutions,
                    )

                body = rendered_script.encode(
                    'utf-8'
                )

                self.send_response(200)
                self.send_header(
                    'Content-Type',
                    'application/javascript',
                )
                self.send_header(
                    'Cache-Control',
                    'no-store',
                )
                send_content_length(
                    self,
                    body,
                )
                self.end_headers()
                self.wfile.write(
                    body
                )
                return

            if asset_type == 'stylesheet':
                body = render_trusted_stylesheet(
                    stylesheet_body,
                    resolutions,
                ).encode('utf-8')

                self.send_response(200)
                self.send_header(
                    'Content-Type',
                    'text/css',
                )
                self.send_header(
                    'Cache-Control',
                    'no-store',
                )
                send_content_length(
                    self,
                    body,
                )
                self.end_headers()
                self.wfile.write(
                    body
                )
                return

            if self.path.startswith('/action/'):
                request_id = self.path[
                    len('/action/'):]
                result = (
                    action_result_registry.get(
                        request_id
                    )
                    if action_result_registry is not None
                    else None
                )

                if result is None:
                    self.send_error(404)
                    return

                body = json.dumps(
                    result,
                    separators=(',', ':'),
                ).encode('utf-8')

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
                send_content_length(
                    self,
                    body,
                )
                self.end_headers()
                self.wfile.write(
                    body
                )
                return

            if self.path == '/probe':
                prune_expired_assets(
                    generated_assets,
                    asset_now(),
                )

                stylesheet_asset_path = (
                    asset_path_factory()
                )

                resolutions = current_resolutions()

                generated_assets[
                    stylesheet_asset_path
                ] = (
                    'stylesheet',
                    resolutions,
                    asset_now() + 10,
                )

                script_asset_path = None

                if resolutions:
                    script_asset_path = (
                        asset_path_factory()
                    )

                    generated_assets[
                        script_asset_path
                    ] = (
                        'script',
                        resolutions,
                        asset_now() + 10,
                    )

                body = render_discovery_response(
                    base_url
                    + stylesheet_asset_path,
                    (
                        base_url
                        + script_asset_path
                        if script_asset_path
                           is not None
                        else None
                    ),
                    ).encode('utf-8')

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
                send_content_length(
                    self,
                    body,
                )
                self.end_headers()
                self.wfile.write(
                    body
                )
                return

            self.send_error(404)

        def do_POST(self):
            if self.path != '/action':
                self.send_error(404)
                return

            origin = self.headers.get('Origin')

            if origin != allowed_origin:
                self.send_error(403)
                return

            content_length = parse_action_content_length(
                self.headers.get(
                    'Content-Length',
                    '0',
                )
            )

            if content_length is None:
                self._send_action_response(400)
                return

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
                    'resolution={!r}, exception={}: {}\n'.format(
                        resolution,
                        type(error).__name__,
                        error,
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

            if not action_guard.claim(resolution):
                self._send_action_response(423)
                return

            if action_hook is not None:
                try:
                    hook_succeeded = action_hook(
                        resolution
                    )
                except Exception as error:
                    sys.stderr.write(
                        'Trusted action hook failed: '
                        'resolution={!r}, exception={}\n'.format(
                            resolution,
                            type(error).__name__,
                        )
                    )

                    action_guard.release(
                        resolution
                    )

                    self._send_action_response(500)
                    return

                if not hook_succeeded:
                    action_guard.release(
                        resolution
                    )

                    self._send_action_response(500)
                    return

            request_id = create_request_id()

            if action_result_registry is not None:
                action_result_registry.create(
                    request_id,
                    time.time() + ACTION_RESULT_TTL_SECONDS,
                    )

            def execute_action():
                try:
                    succeeded = action_executor(
                        script_name
                    )
                except Exception as error:
                    sys.stderr.write(
                        'Trusted action executor failed: '
                        'resolution={!r}, script={!r}, exception={}: {}\n'.format(
                            resolution,
                            script_name,
                            type(error).__name__,
                            str(error),
                        )
                    )

                    if action_result_registry is not None:
                        action_result_registry.fail(
                            request_id
                        )

                    action_guard.release(
                        resolution
                    )
                    return

                if not succeeded:
                    if action_result_registry is not None:
                        action_result_registry.fail(
                            request_id
                        )

                    action_guard.release(
                        resolution
                    )
                    return

                action_guard.complete(
                    resolution
                )

                if action_result_registry is not None:
                    action_result_registry.succeed(
                        request_id
                    )

                sys.stderr.write(
                    'Trusted action executor succeeded: '
                    'resolution={!r}, script={!r}\n'.format(
                        resolution,
                        script_name,
                    )
                )

            threading.Thread(
                target=execute_action,
            ).start()

            self._send_action_response(
                202,
                json.dumps(
                    {
                        'request_id': request_id,
                    },
                    separators=(',', ':'),
                ),
            )

        def _send_action_response(self, status, body=''):
            body = body.encode('utf-8')

            self.send_response(status)
            self.send_header(
                'Access-Control-Allow-Origin',
                allowed_origin,
            )
            send_content_length(self, body)
            self.end_headers()

            if body:
                self.wfile.write(body)

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
            send_content_length(self, '')
            self.end_headers()

        def log_message(self, format, *args):
            pass

    return TrustedDiscoveryServer(
        (host, port),
        DiscoveryHandler,
    )

if __name__ == '__main__':
    main()
