import os
import socket
import sys
import tempfile
import threading

sys.path.insert(
    0,
    os.path.join(
        os.path.dirname(__file__),
        'lib',
    ),
)

import testlib
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import load_trusted_discovery
from trusted_discovery_testlib import stub_routeros_action_dependencies
from trusted_discovery_testlib import restore_routeros_action_dependencies


runner = TestSuiteRunner('trusted-discovery-tls')
trusted_discovery = load_trusted_discovery()

@runner.test('suppresses expected client disconnect errors')
def expected_client_disconnect_errors_are_suppressed():
    server = object.__new__(
        trusted_discovery.TrustedDiscoveryServer
    )

    calls = []

    class FakeParentServer:
        @staticmethod
        def handle_error(
                server,
                request,
                client_address,
        ):
            calls.append(
                (
                    request,
                    client_address,
                )
            )

    original_parent = trusted_discovery.ThreadingHTTPServer
    original_exc_info = trusted_discovery.sys.exc_info

    trusted_discovery.ThreadingHTTPServer = FakeParentServer

    try:
        trusted_discovery.sys.exc_info = lambda: (
            BrokenPipeError,
            BrokenPipeError(),
            None,
        )

        server.handle_error(
            'request',
            ('127.0.0.1', 12345),
        )

        testlib.assert_same(
            [],
            calls,
        )
    finally:
        trusted_discovery.ThreadingHTTPServer = original_parent
        trusted_discovery.sys.exc_info = original_exc_info

@runner.test('delegates unexpected server errors')
def unexpected_server_errors_are_delegated():
    server = object.__new__(
        trusted_discovery.TrustedDiscoveryServer
    )

    calls = []

    class FakeParentServer:
        @staticmethod
        def handle_error(
                server,
                request,
                client_address,
        ):
            calls.append(
                (
                    request,
                    client_address,
                )
            )

    original_parent = trusted_discovery.ThreadingHTTPServer
    original_exc_info = trusted_discovery.sys.exc_info

    trusted_discovery.ThreadingHTTPServer = FakeParentServer

    try:
        trusted_discovery.sys.exc_info = lambda: (
            RuntimeError,
            RuntimeError('boom'),
            None,
        )

        server.handle_error(
            'request',
            ('127.0.0.1', 12345),
        )

        testlib.assert_same(
            [
                (
                    'request',
                    ('127.0.0.1', 12345),
                )
            ],
            calls,
        )
    finally:
        trusted_discovery.ThreadingHTTPServer = original_parent
        trusted_discovery.sys.exc_info = original_exc_info

@runner.test('does not let a slow client block another probe')
def slow_client_does_not_block_probe():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        None,
        None,
        None,
        lambda resolution: True,
        trusted_discovery.ActionGuard(
            lambda: 100,
            30,
        ),
    )

    thread = threading.Thread(
        target=server.serve_forever,
    )
    thread.start()

    slow_client = socket.create_connection(
        ('127.0.0.1', server.server_port)
    )

    fast_client = None
    response = None

    try:
        slow_client.sendall(
            b'GET /probe HTTP/1.1\r\n'
            b'Host: bredland.example\r\n'
        )

        fast_client = socket.create_connection(
            ('127.0.0.1', server.server_port)
        )

        fast_client.settimeout(0.5)

        fast_client.sendall(
            b'GET /probe HTTP/1.1\r\n'
            b'Host: bredland.example\r\n'
            b'Connection: close\r\n'
            b'\r\n'
        )

        try:
            response = fast_client.recv(4096)
        except socket.timeout:
            pass
    finally:
        slow_client.close()

        if fast_client is not None:
            if response is None:
                fast_client.settimeout(2)

                try:
                    fast_client.recv(4096)
                except Exception:
                    pass

            fast_client.close()

        server.shutdown()
        thread.join()
        server.server_close()

    testlib.assert_true(
    response is not None and b'200 OK' in response,
    'Expected a slow client not to block another probe',
)

@runner.test('keeps TLS handshake off the listening socket')
def configured_server_uses_tls():
    calls = []

    class FakeContext:
        def load_cert_chain(self, certfile, keyfile):
            calls.append(
                ('load_cert_chain', certfile, keyfile)
            )

        def wrap_socket(self, socket, server_side):
            calls.append(
                ('wrap_socket', socket, server_side)
            )
            return 'tls-socket'

    class FakeSsl:
        PROTOCOL_TLS_SERVER = 'tls-server'

        @staticmethod
        def SSLContext(protocol):
            calls.append(
                ('SSLContext', protocol)
            )
            return FakeContext()

    class FakeServer:
        def __init__(self):
            self.socket = 'plain-socket'

    with tempfile.TemporaryDirectory() as tmpdir:
        script_file = os.path.join(
            tmpdir,
            'trusted.js',
        )

        stylesheet_file = os.path.join(
            tmpdir,
            'trusted.css',
        )

        with open(script_file, 'w') as file:
            file.write('window.TEST_TRUSTED_ASSET_LOADED = true;')

        with open(stylesheet_file, 'w') as file:
            file.write('html { outline: 1px solid; }')

        original_ssl = trusted_discovery.ssl
        original_create_server = trusted_discovery.create_server
        original_script_file = trusted_discovery.TRUSTED_SCRIPT_FILE
        original_stylesheet_file = trusted_discovery.TRUSTED_STYLESHEET_FILE

        trusted_discovery.ssl = FakeSsl
        trusted_discovery.create_server = lambda *args: FakeServer()
        trusted_discovery.TRUSTED_SCRIPT_FILE = script_file
        trusted_discovery.TRUSTED_STYLESHEET_FILE = stylesheet_file

        try:
            routeros_originals = stub_routeros_action_dependencies(
                trusted_discovery
            )
            server = trusted_discovery.create_configured_server(
                '127.0.0.1',
                8081,
            )
        finally:
            trusted_discovery.ssl = original_ssl
            trusted_discovery.create_server = original_create_server
            trusted_discovery.TRUSTED_SCRIPT_FILE = original_script_file
            trusted_discovery.TRUSTED_STYLESHEET_FILE = original_stylesheet_file
            restore_routeros_action_dependencies(
                trusted_discovery,
                routeros_originals,
            )

    testlib.assert_same(
        [
            ('SSLContext', 'tls-server'),
            (
                'load_cert_chain',
                '/etc/bredland/tls/fullchain.pem',
                '/etc/bredland/tls/privkey.pem',
            ),
        ],
        calls,
    )

    testlib.assert_same(
        'plain-socket',
        server.socket,
    )

@runner.test('wraps each client connection in TLS')
def worker_wraps_client_connection_in_tls():
    calls = []

    class FakeContext:
        def wrap_socket(self, socket, server_side):
            calls.append(
                ('wrap_socket', socket, server_side)
            )
            return 'tls-client-socket'

    class FakeParentServer:
        @staticmethod
        def process_request_thread(
                server,
                request,
                client_address,
        ):
            calls.append(
                (
                    'process_request_thread',
                    request,
                    client_address,
                )
            )

    server = object.__new__(
        trusted_discovery.TrustedDiscoveryServer
    )

    server.tls_context = FakeContext()

    original = trusted_discovery.ThreadingHTTPServer
    trusted_discovery.ThreadingHTTPServer = FakeParentServer

    try:
        server.process_request_thread(
            'plain-client-socket',
            ('127.0.0.1', 12345),
        )
    finally:
        trusted_discovery.ThreadingHTTPServer = original

    testlib.assert_same(
        [
            (
                'wrap_socket',
                'plain-client-socket',
                True,
            ),
            (
                'process_request_thread',
                'tls-client-socket',
                ('127.0.0.1', 12345),
            ),
        ],
        calls,
    )

runner.finish()