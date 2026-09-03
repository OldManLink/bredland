#!/usr/bin/env python3

import json
import sys
import threading
import time

from http.server import BaseHTTPRequestHandler, HTTPServer

def shutdown_after_delay(
        server,
        delay_ms,
):
    time.sleep(
        delay_ms / 1000.0
    )

    server.shutdown()

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

        if self.server.shutdown_delay_ms is not None:
            thread = threading.Thread(
                target=shutdown_after_delay,
                args=(
                    self.server,
                    self.server.shutdown_delay_ms,
                ),
            )

            thread.daemon = True
            thread.start()


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
    shutdown_delay_ms = None

    if len(sys.argv) > 1:
        shutdown_delay_ms = int(
            sys.argv[1]
        )

    server = HTTPServer(
        ('0.0.0.0', 8082),
        Handler
    )

    server.shutdown_delay_ms = shutdown_delay_ms

    server.serve_forever(
        poll_interval=0.01
    )


if __name__ == '__main__':
    main()