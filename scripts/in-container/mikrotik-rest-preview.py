#!/usr/bin/env python3

import json
import sys
import threading
import time

from http.server import BaseHTTPRequestHandler, HTTPServer

class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path == '/rest/execute':
            body = json.dumps(
                {
                    'ret': (
                        'true'
                        if self.server.routeros_staged
                        else 'false'
                    ),
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
            return

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

        if request not in (
                {
                    '.id': 'noc-trusted-action-probe',
                },
                {
                    '.id': 'noc-install-routeros-update',
                },
                {
                    '.id': 'noc-install-routerboot-update',
                },
                {
                    '.id': 'noc-download-routeros-update',
                },
        ):
            self.send_error(400)
            return

        if request['.id'] == 'noc-download-routeros-update':
            time.sleep(
                self.server.download_delay_ms / 1000.0
            )
            self.server.routeros_staged = True

        print(
            'Mock MikroTik ran script: {}'.format(
                request['.id'],
            ),
            flush=True,
        )

        self.send_response(200)
        self.end_headers()

        if request['.id'] in (
                'noc-install-routeros-update',
                'noc-install-routerboot-update',
        ):
            thread = threading.Thread(
                target=self.server.shutdown,
            )

            thread.daemon = True
            thread.start()


    def do_GET(self):
        if self.path == '/rest/system/package/update':
            response = {
                'installed-version': '7.23.1',
                'latest-version': '7.24.1',
                'status': 'New version is available',
            }
        elif self.path == '/rest/system/routerboard':
            response = {
                'current-firmware': '7.23.1',
                'upgrade-firmware': '7.24.2',
            }
        else:
            self.send_error(404)
            return

        body = json.dumps(
            response
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
    download_delay_ms = 0

    if len(sys.argv) > 1:
        download_delay_ms = int(
            sys.argv[1]
        )

    server = HTTPServer(
        ('0.0.0.0', 8082),
        Handler
    )

    server.download_delay_ms = download_delay_ms
    server.routeros_staged = False

    server.serve_forever(
        poll_interval=0.01
    )


if __name__ == '__main__':
    main()