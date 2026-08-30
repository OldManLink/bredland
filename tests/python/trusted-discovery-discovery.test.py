import ast
import os
import sys
import tempfile
import threading
import urllib.error
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


runner = TestSuiteRunner('trusted-discovery-discovery')
trusted_discovery = load_trusted_discovery()

@runner.test('renders the trusted discovery response')
def discovery_response_is_rendered():
    assert_same(
        '{"assets":{"script":"https://bredland.example/opaque-script",'
        '"stylesheet":"https://bredland.example/opaque-style"}}',
        trusted_discovery.render_discovery_response(
            'https://bredland.example/opaque-script',
            'https://bredland.example/opaque-style',
        ),
    )

@runner.test('serves the trusted discovery response over HTTP')
def discovery_endpoint_returns_json():
    create_server = getattr(
        trusted_discovery,
        'create_server',
        None,
    )

    assert_same(
        True,
        callable(create_server),
        'Expected trusted discovery to provide create_server()',
    )

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

        assert_same(200, response.status)
        assert_same(
            'application/json',
            response.headers.get_content_type(),
        )
        assert_same(
            'no-store',
            response.headers.get('Cache-Control'),
        )
        assert_same(
            '{"assets":{"script":"https://bredland.example/trusted-script-test",'
            '"stylesheet":"https://bredland.example/trusted-style-test"}}',
            body,
        )
    finally:
        thread.join()
        server.server_close()

@runner.test('serves discovery only on the probe path')
def discovery_endpoint_only_serves_probe_path():
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
        target=server.serve_forever,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/probe'.format(
                server.server_port,
            ),
        )

        assert_same(200, response.status)

        try:
            urllib.request.urlopen(
                'http://127.0.0.1:{}/anything'.format(
                    server.server_port,
                ),
            )
        except urllib.error.HTTPError as error:
            assert_same(404, error.code)
        else:
            assert_same(
                True,
                False,
                'Expected unrelated path to return 404',
            )
    finally:
        server.shutdown()
        thread.join()
        server.server_close()

@runner.test('allows the NOC origin to read discovery')
def discovery_endpoint_allows_noc_origin():
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

        assert_same(
            'https://noc.arcanel.se',
            response.headers.get(
                'Access-Control-Allow-Origin',
            ),
        )
    finally:
        thread.join()
        server.server_close()

@runner.test('uses rendered deployment configuration')
def deployment_configuration_is_rendered():
    assert_same(
        'https://bredland.example:8081',
        getattr(
            trusted_discovery,
            'TRUSTED_BASE_URL',
            None,
        ),
    )

    assert_same(
        'https://noc.arcanel.se',
        getattr(
            trusted_discovery,
            'TRUSTED_ALLOWED_ORIGIN',
            None,
        ),
    )

    assert_same(
        '/trusted-script-test',
        getattr(
            trusted_discovery,
            'TRUSTED_SCRIPT_PATH',
            None,
        ),
    )

    assert_same(
        '/trusted-style-test',
        getattr(
            trusted_discovery,
            'TRUSTED_STYLESHEET_PATH',
            None,
        ),
    )

@runner.test('creates a server from rendered deployment configuration')
def configured_server_uses_rendered_configuration():
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
                '{"assets":{"script":"https://bredland.example:8081/trusted-script-test",'
                '"stylesheet":"https://bredland.example:8081/trusted-style-test"}}',
                body,
            )

            assert_same(
                'https://noc.arcanel.se',
                response.headers.get(
                    'Access-Control-Allow-Origin',
                ),
            )
        finally:
            thread.join()
            server.server_close()
            trusted_discovery.TRUSTED_SCRIPT_FILE = original_script_file
            trusted_discovery.TRUSTED_STYLESHEET_FILE = original_stylesheet_file

@runner.test('configured server serves rendered trusted script')
def configured_server_serves_rendered_trusted_script():
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
        original_renderer_factory = (
            trusted_discovery.create_trusted_script_renderer
        )
        original_ssl = trusted_discovery.ssl

        trusted_discovery.TRUSTED_SCRIPT_FILE = script_file
        trusted_discovery.TRUSTED_STYLESHEET_FILE = stylesheet_file

        def create_renderer(
            base_url,
            noc_html_loader,
            token_generator,
            registry,
            expires_at,
        ):
            return lambda script_body: (
                'window.CONFIGURED_RENDERER = true;'
            )

        trusted_discovery.create_trusted_script_renderer = (
            create_renderer
        )

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

        trusted_discovery.ssl = FakeSsl

        try:
            routeros_originals = stub_routeros_action_dependencies(
                trusted_discovery
            )
            server = trusted_discovery.create_configured_server(
                '127.0.0.1',
                0,
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

                assert_same(
                    'window.CONFIGURED_RENDERER = true;',
                    body,
                )
            finally:
                thread.join()
                server.server_close()
        finally:
            trusted_discovery.TRUSTED_SCRIPT_FILE = (
                original_script_file
            )
            trusted_discovery.TRUSTED_STYLESHEET_FILE = (
                original_stylesheet_file
            )
            trusted_discovery.create_trusted_script_renderer = (
                original_renderer_factory
            )
            trusted_discovery.ssl = original_ssl
            restore_routeros_action_dependencies(
                trusted_discovery,
                routeros_originals,
            )

@runner.test('runs the configured trusted discovery server')
def main_runs_configured_server():
    calls = []

    class FakeServer:
        def serve_forever(self):
            calls.append('serve_forever')

    def fake_create_configured_server(host, port):
        calls.append((host, port))
        return FakeServer()

    original = trusted_discovery.create_configured_server
    trusted_discovery.create_configured_server = fake_create_configured_server

    try:
        trusted_discovery.main()
    finally:
        trusted_discovery.create_configured_server = original

    assert_same(
        [
            ('0.0.0.0', 8081),
            'serve_forever',
        ],
        calls,
    )

@runner.test('places the main guard after all function definitions')
def main_guard_comes_after_function_definitions():
    with open(
            'templates/bredland/trusted_discovery.template.py',
            'r',
    ) as file:
        tree = ast.parse(file.read())

    function_lines = [
        node.lineno
        for node in tree.body
        if isinstance(node, ast.FunctionDef)
    ]

    main_guard_lines = [
        node.lineno
        for node in tree.body
        if (
                isinstance(node, ast.If)
                and isinstance(node.test, ast.Compare)
                and isinstance(node.test.left, ast.Name)
                and node.test.left.id == '__name__'
        )
    ]

    assert_same(
        1,
        len(main_guard_lines),
        'Expected exactly one __main__ guard',
    )

    assert_same(
        True,
        main_guard_lines[0] > max(function_lines),
        '__main__ guard must come after all function definitions',
        )

@runner.test('configured server wires RouterOS action executor')
def configured_server_wires_routeros_action_executor():
    calls = []

    class FakeServer:
        tls_context = None

    class FakeContext:
        def load_cert_chain(self, certfile, keyfile):
            pass

    class FakeSsl:
        PROTOCOL_TLS_SERVER = 'tls-server'

        @staticmethod
        def SSLContext(protocol):
            return FakeContext()

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
        original_ssl = trusted_discovery.ssl
        original_create_server = trusted_discovery.create_server
        original_load_credentials = (
            trusted_discovery.load_routeros_rest_credentials
        )
        original_create_tls_context = (
            trusted_discovery.create_routeros_rest_tls_context
        )
        original_create_poster = (
            trusted_discovery.create_routeros_rest_poster
        )
        original_create_executor = (
            trusted_discovery.create_routeros_action_executor
        )

        trusted_discovery.TRUSTED_SCRIPT_FILE = script_file
        trusted_discovery.TRUSTED_STYLESHEET_FILE = stylesheet_file
        trusted_discovery.ssl = FakeSsl

        trusted_discovery.load_routeros_rest_credentials = (
            lambda credentials_file: {
                'username': 'test-user',
                'password': 'test-password',
            }
        )

        trusted_discovery.create_routeros_rest_tls_context = (
            lambda ca_file: 'routeros-tls-context'
        )

        trusted_discovery.create_routeros_rest_poster = (
            lambda credentials, context, open_request, post_json_function:
            'routeros-poster'
        )

        trusted_discovery.create_routeros_action_executor = (
            lambda base_url, post: 'routeros-action-executor'
        )

        def create_server(*args):
            calls.append(
                args[8]
            )

            return FakeServer()

        trusted_discovery.create_server = create_server

        try:
            trusted_discovery.create_configured_server(
                '127.0.0.1',
                8081,
            )
        finally:
            trusted_discovery.TRUSTED_SCRIPT_FILE = original_script_file
            trusted_discovery.TRUSTED_STYLESHEET_FILE = original_stylesheet_file
            trusted_discovery.ssl = original_ssl
            trusted_discovery.create_server = original_create_server
            trusted_discovery.load_routeros_rest_credentials = (
                original_load_credentials
            )
            trusted_discovery.create_routeros_rest_tls_context = (
                original_create_tls_context
            )
            trusted_discovery.create_routeros_rest_poster = (
                original_create_poster
            )
            trusted_discovery.create_routeros_action_executor = (
                original_create_executor
            )

    assert_same(
        ['routeros-action-executor'],
        calls,
    )

runner.finish()