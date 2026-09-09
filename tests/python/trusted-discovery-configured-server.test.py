import ast
import os
import sys
import tempfile
import threading
import urllib
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'lib'))
from builtins import (callable, isinstance, len, max, open, staticmethod)
import testlib
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (load_trusted_discovery, passthrough_tls, restore_routeros_action_dependencies,
                                       server_url, serving, stubbed_routeros_action_dependencies, temporary_trusted_assets)


runner = TestSuiteRunner(
    'trusted-discovery-configured-server'
)

trusted_discovery = load_trusted_discovery()

@runner.test('loads trusted assets from fixed local files')
def configured_server_loads_trusted_assets_from_files():
    with temporary_trusted_assets(
            trusted_discovery,
    ):
        with passthrough_tls(
                trusted_discovery,
        ):
            with stubbed_routeros_action_dependencies(
                    trusted_discovery,
            ):
                server = trusted_discovery.create_configured_server(
                    '127.0.0.1',
                    0,
                )

                with serving(server):
                    script_response = urllib.request.urlopen(
                        server_url(
                            server,
                            '/trusted-script-test',
                        )
                    )

                    stylesheet_response = urllib.request.urlopen(
                        server_url(
                            server,
                            '/trusted-style-test',
                        )
                    )

                    script_body = (
                        script_response
                        .read()
                        .decode('utf-8')
                    )

                    stylesheet_body = (
                        stylesheet_response
                        .read()
                        .decode('utf-8')
                    )

    testlib.assert_string_contains(
        'window.TRUSTED_CAPABILITIES = ',
        script_body,
    )

    testlib.assert_string_ends_with(
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        script_body,
    )

    testlib.assert_same(
        'html { outline: 1px solid; }',
        stylesheet_body,
    )

@runner.test('creates a server from rendered deployment configuration')
def configured_server_uses_rendered_configuration():
    with temporary_trusted_assets(
            trusted_discovery,
    ):
        with passthrough_tls(
                trusted_discovery,
        ):
            with stubbed_routeros_action_dependencies(
                    trusted_discovery,
            ):
                server = trusted_discovery.create_configured_server(
                    '127.0.0.1',
                    0,
                )

                with serving(server):
                    response = urllib.request.urlopen(
                        server_url(
                            server,
                            '/probe',
                        )
                    )

                    body = response.read().decode('utf-8')

    testlib.assert_same(
        '{"assets":{"script":"https://bredland.example:8081/trusted-script-test",'
        '"stylesheet":"https://bredland.example:8081/trusted-style-test"}}',
        body,
    )

    testlib.assert_same(
        'https://noc.arcanel.se',
        response.headers.get(
            'Access-Control-Allow-Origin',
        ),
    )

@runner.test('configured server serves rendered trusted script')
def configured_server_serves_rendered_trusted_script():
    original_renderer_factory = (
        trusted_discovery.create_trusted_script_renderer
    )

    def create_renderer(
            base_url,
            noc_html_loader,
            token_generator,
            registry,
            expires_at,
            server_time,
    ):
        return lambda script_body: (
            'window.CONFIGURED_RENDERER = true;'
        )

    trusted_discovery.create_trusted_script_renderer = (
        create_renderer
    )

    try:
        with temporary_trusted_assets(
                trusted_discovery,
        ):
            with passthrough_tls(
                    trusted_discovery,
            ):
                with stubbed_routeros_action_dependencies(
                        trusted_discovery,
                ):
                    server = (
                        trusted_discovery
                        .create_configured_server(
                            '127.0.0.1',
                            0,
                        )
                    )

                    with serving(server):
                        response = urllib.request.urlopen(
                            server_url(
                                server,
                                '/trusted-script-test',
                            )
                        )

                        body = (
                            response
                            .read()
                            .decode('utf-8')
                        )
    finally:
        trusted_discovery.create_trusted_script_renderer = (
            original_renderer_factory
        )

    testlib.assert_same(
        'window.CONFIGURED_RENDERER = true;',
        body,
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

    testlib.assert_same(
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

    testlib.assert_same(
        1,
        len(main_guard_lines),
        'Expected exactly one __main__ guard',
    )

    testlib.assert_true(main_guard_lines[0] > max(function_lines),
                        '__main__ guard must come after all function definitions',
                        )

@runner.test('configured server wires RouterOS action executor')
def configured_server_wires_routeros_action_executor():
    wired = {}
    hook_calls = []

    class FakeServer:
        tls_context = None

    originals = {
        'create_server':
            trusted_discovery.create_server,
        'load_credentials':
            trusted_discovery.load_routeros_rest_credentials,
        'create_tls_context':
            trusted_discovery.create_routeros_rest_tls_context,
        'create_poster':
            trusted_discovery.create_routeros_rest_poster,
        'create_executor':
            trusted_discovery.create_routeros_action_executor,
        'create_getter':
            trusted_discovery.create_routeros_rest_getter,
        'execute_configured_hook':
            trusted_discovery.execute_configured_resolution_hook,
    }

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
        lambda base_url, post:
        'routeros-action-executor'
    )

    trusted_discovery.create_routeros_rest_getter = (
        lambda credentials, context, open_request, get_json_function:
        lambda url: {
            'installed-version': '7.23.1',
            'latest-version': '7.24.1',
            'status': 'New version is available',
        }
    )

    def execute_configured_resolution_hook(
            path,
            resolution,
            hook_executor,
            logger,
    ):
        hook_calls.append(
            (
                path,
                resolution,
                hook_executor,
                logger,
            )
        )

        return True

    trusted_discovery.execute_configured_resolution_hook = (
        execute_configured_resolution_hook
    )

    def create_server(*args):
        wired['executor'] = args[8]
        wired['validator'] = args[11]
        wired['action_hook'] = args[13]

        return FakeServer()

    trusted_discovery.create_server = create_server

    try:
        with temporary_trusted_assets(
                trusted_discovery,
        ):
            with passthrough_tls(
                    trusted_discovery,
            ):
                trusted_discovery.create_configured_server(
                    '127.0.0.1',
                    8081,
                )

        testlib.assert_same(
            'routeros-action-executor',
            wired['executor'],
        )

        testlib.assert_true(
            wired['validator'](
                'install-routeros-update'
            )
        )

        testlib.assert_false(
            wired['validator'](
                'something-else'
            )
        )

        testlib.assert_true(
            wired['action_hook'](
                'install-routeros-update'
            )
        )

        testlib.assert_same(
            1,
            len(hook_calls),
        )

        testlib.assert_same(
            trusted_discovery.RESOLUTIONS_FILE,
            hook_calls[0][0],
        )

        testlib.assert_same(
            'install-routeros-update',
            hook_calls[0][1],
        )

        testlib.assert_true(
            callable(
                hook_calls[0][3]
            )
        )
    finally:
        trusted_discovery.create_server = (
            originals['create_server']
        )
        trusted_discovery.load_routeros_rest_credentials = (
            originals['load_credentials']
        )
        trusted_discovery.create_routeros_rest_tls_context = (
            originals['create_tls_context']
        )
        trusted_discovery.create_routeros_rest_poster = (
            originals['create_poster']
        )
        trusted_discovery.create_routeros_action_executor = (
            originals['create_executor']
        )
        trusted_discovery.create_routeros_rest_getter = (
            originals['create_getter']
        )
        trusted_discovery.execute_configured_resolution_hook = (
            originals['execute_configured_hook']
        )

runner.finish()