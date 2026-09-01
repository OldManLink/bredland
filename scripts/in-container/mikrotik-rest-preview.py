#!/usr/bin/env python3

import json

from http.server import BaseHTTPRequestHandler, HTTPServer


class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path != '/rest/system/script/run':
            self.send_error(404)
            return

        content_length = int(
            self.headers.get(
                'Content-Length',
                '0',
            )
        )

        body = self.rfile.read(
            content_length
        )

        try:
            request = json.loads(
                body.decode('utf-8')
            )
        except ValueError:
            self.send_error(400)
            return

        if request != {
            '.id': 'noc-trusted-action-test',
        }:
            self.send_error(400)
            return

        print(
            'Mock MikroTik ran script: {}'.format(
                request['.id'],
            ),
            flush=True,
        )

        self.send_response(200)
        self.end_headers()

    def do_GET(self):
        if self.path != '/rest/system/package/update':
            self.send_error(404)
            return

        body = json.dumps(
            {
                'installed-version': '7.23.1',
                'latest-version': '7.24.1',
                'status': 'New version is available',
            }
        ).encode('utf-8')

        self.send_response(200)
        self.send_header(
            'Content-Type',
            'application/json',
        )
        self.send_header(
            'Content-Length',
            str(len(body)),
        )
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format, *args):
        pass


def main():
    server = HTTPServer(
        ('0.0.0.0', 8082),
        Handler
    )

    server.serve_forever()


if __name__ == '__main__':
    main()