import json
from http.server import BaseHTTPRequestHandler
from http.server import HTTPServer

TRUSTED_BASE_URL = '__BREDLAND_TRUSTED_BASE_URL__'
TRUSTED_ALLOWED_ORIGIN = '__BREDLAND_TRUSTED_ALLOWED_ORIGIN__'
TRUSTED_SCRIPT_PATH = '__BREDLAND_TRUSTED_SCRIPT_PATH__'
TRUSTED_STYLESHEET_PATH = '__BREDLAND_TRUSTED_STYLESHEET_PATH__'
TRUSTED_SCRIPT_FILE = '/usr/local/lib/bredland/static/trusted.js'
TRUSTED_STYLESHEET_FILE = '/usr/local/lib/bredland/static/trusted.css'

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

def create_configured_server(
        host,
        port,
):
    with open(TRUSTED_SCRIPT_FILE, 'r') as file:
        script_body = file.read()

    with open(TRUSTED_STYLESHEET_FILE, 'r') as file:
        stylesheet_body = file.read()

    return create_server(
        host,
        port,
        TRUSTED_BASE_URL,
        TRUSTED_ALLOWED_ORIGIN,
        TRUSTED_SCRIPT_PATH,
        script_body,
        TRUSTED_STYLESHEET_PATH,
        stylesheet_body,
    )

def create_server(
    host,
    port,
    base_url,
    allowed_origin,
    script_path,
    script_body,
    stylesheet_path,
    stylesheet_body,
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
                body = script_body.encode('utf-8')

                self.send_response(200)
                self.send_header(
                    'Content-Type',
                    'application/javascript',
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
                'Access-Control-Allow-Origin',
                allowed_origin,
            )
            self.end_headers()
            self.wfile.write(response_body)

        def log_message(self, format, *args):
            pass

    return HTTPServer(
        (host, port),
        DiscoveryHandler,
    )