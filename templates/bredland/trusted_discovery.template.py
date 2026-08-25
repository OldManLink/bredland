import json
from http.server import BaseHTTPRequestHandler
from http.server import HTTPServer


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