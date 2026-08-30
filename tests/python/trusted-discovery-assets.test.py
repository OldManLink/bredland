import os
import sys
import tempfile
import threading
import urllib.request

sys.path.insert(
    0,
    os.path.join(
        os.path.dirname(__file__),
        'lib',
    ),
)

from test_suite_runner import TestSuiteRunner
from testlib import assert_same
from trusted_discovery_testlib import load_trusted_discovery
from trusted_discovery_testlib import stub_routeros_action_dependencies
from trusted_discovery_testlib import restore_routeros_action_dependencies


runner = TestSuiteRunner('trusted-discovery-assets')
trusted_discovery = load_trusted_discovery()

@runner.test('serves the configured trusted script')
def trusted_script_is_served():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TRUSTED_MODE = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        None,
        None,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/trusted-script-test'.format(
                server.server_port,
            ),
        )

        body = response.read().decode('utf-8')

        assert_same(200, response.status)
        assert_same(
            'application/javascript',
            response.headers.get_content_type(),
        )
        assert_same(
            'no-store',
            response.headers.get('Cache-Control'),
        )
        assert_same(
            'window.TRUSTED_MODE = true;',
            body,
        )
    finally:
        thread.join()
        server.server_close()

@runner.test('serves the configured trusted stylesheet')
def trusted_stylesheet_is_served():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TRUSTED_MODE = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        None,
        None,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/trusted-style-test'.format(
                server.server_port,
            ),
        )

        body = response.read().decode('utf-8')

        assert_same(200, response.status)
        assert_same(
            'text/css',
            response.headers.get_content_type(),
        )
        assert_same(
            'no-store',
            response.headers.get('Cache-Control'),
        )
        assert_same(
            'html { outline: 1px solid; }',
            body,
        )
    finally:
        thread.join()
        server.server_close()

@runner.test('discovery advertises the served asset paths')
def discovery_urls_match_served_asset_paths():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TRUSTED_MODE = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        None,
        None,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/probe'.format(
                server.server_port,
            ),
        )

        body = response.read().decode('utf-8')

        assert_same(
            '{"assets":{"script":"https://bredland.example/trusted-script-test",'
            '"stylesheet":"https://bredland.example/trusted-style-test"}}',
            body,
        )
    finally:
        thread.join()
        server.server_close()



@runner.test('loads trusted assets from fixed local files')
def configured_server_loads_trusted_assets_from_files():
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
            file.write('window.TRUSTED_MODE = true;')

        with open(stylesheet_file, 'w') as file:
            file.write('html { outline: 1px solid; }')

        original_script_file = trusted_discovery.TRUSTED_SCRIPT_FILE
        original_stylesheet_file = trusted_discovery.TRUSTED_STYLESHEET_FILE
        trusted_discovery.TRUSTED_SCRIPT_FILE = script_file
        trusted_discovery.TRUSTED_STYLESHEET_FILE = stylesheet_file

        class FakeContext:
            def load_cert_chain(self, certfile, keyfile):
                pass

            def wrap_socket(self, socket, server_side):
                return socket


        class FakeSsl:
            PROTOCOL_TLS_SERVER = 'tls-server'

            @staticmethod
            def SSLContext(protocol):
                return FakeContext()


        original_ssl = trusted_discovery.ssl
        trusted_discovery.ssl = FakeSsl

        try:
            routeros_originals = stub_routeros_action_dependencies(
                trusted_discovery
            )
            server = trusted_discovery.create_configured_server(
                '127.0.0.1',
                0,
            )
        finally:
            trusted_discovery.ssl = original_ssl
            restore_routeros_action_dependencies(
                trusted_discovery,
                routeros_originals,
            )

        thread = threading.Thread(
            target=server.serve_forever,
        )
        thread.start()

        try:
            script_response = urllib.request.urlopen(
                'http://127.0.0.1:{}/trusted-script-test'.format(
                    server.server_port,
                ),
            )

            stylesheet_response = urllib.request.urlopen(
                'http://127.0.0.1:{}/trusted-style-test'.format(
                    server.server_port,
                ),
            )

            script_body = script_response.read().decode('utf-8')

            assert_same(
                True,
                'window.TRUSTED_CAPABILITIES = ' in script_body,
            )

            assert_same(
                True,
                script_body.endswith(
                    'window.TRUSTED_MODE = true;'
                ),
            )

            assert_same(
                'html { outline: 1px solid; }',
                stylesheet_response.read().decode('utf-8'),
            )
        finally:
            server.shutdown()
            thread.join()
            server.server_close()
            trusted_discovery.TRUSTED_SCRIPT_FILE = original_script_file
            trusted_discovery.TRUSTED_STYLESHEET_FILE = original_stylesheet_file

runner.finish()