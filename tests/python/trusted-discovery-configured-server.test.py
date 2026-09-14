import ast
import os
import sys
import tempfile
import threading
import urllib
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'lib'))
from builtins import (callable, isinstance, iter, len, max, next, open, staticmethod)
import testlib
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (configured_server_wiring, load_trusted_discovery, passthrough_tls, probe,
                                       restore_routeros_action_dependencies, server_url, serving,
                                       stubbed_routeros_action_dependencies, temporary_trusted_assets,
                                       TEST_STYLESHEET_BODY, TEST_SCRIPT_BODY)


runner = TestSuiteRunner(
    'trusted-discovery-configured-server'
)

trusted_discovery = load_trusted_discovery()

@runner.test('loads trusted asset bodies from local files')
def configured_server_loads_trusted_asset_bodies_from_local_files():
    paths = iter([
        '/generated-style',
        '/generated-script',
    ])

    with testlib.patched_attribute(
            trusted_discovery,
            'create_asset_path',
            lambda: next(paths),
    ):
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
                        probe(
                            server
                        )

                        stylesheet = urllib.request.urlopen(
                            server_url(
                                server,
                                '/generated-style',
                            )
                        )

                        script = urllib.request.urlopen(
                            server_url(
                                server,
                                '/generated-script',
                            )
                        )

                        stylesheet_body = (
                            stylesheet.read().decode('utf-8')
                        )

                        script_body = (
                            script.read().decode('utf-8')
                        )

    testlib.assert_same(
        TEST_STYLESHEET_BODY,
        stylesheet_body,
    )

    testlib.assert_string_ends_with(
        TEST_SCRIPT_BODY,
        script_body,
    )

@runner.test('creates a server from rendered deployment configuration')
def configured_server_uses_rendered_configuration():
    paths = iter([
        '/configured-style',
        '/configured-script',
    ])

    with testlib.patched_attribute(
            trusted_discovery,
            'create_asset_path',
            lambda: next(paths),
    ):
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
        (
            '{"assets":['
            '"https://bredland.example:8081/configured-style",'
            '"https://bredland.example:8081/configured-script"'
            ']}'
        ),
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
    paths = iter([
        '/generated-style',
        '/generated-script',
    ])

    with testlib.patched_attribute(
            trusted_discovery,
            'create_asset_path',
            lambda: next(paths),
    ):
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
                        probe(
                            server
                        )

                        response = urllib.request.urlopen(
                            server_url(
                                server,
                                '/generated-script',
                            )
                        )

                        body = response.read().decode('utf-8')

    testlib.assert_string_ends_with(
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
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
    with configured_server_wiring(
            trusted_discovery,
    ) as wiring:
        wired, hook_calls = wiring

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

@runner.test('configured server wires RouterOS action validator')
def configured_server_wires_routeros_action_validator():
    with configured_server_wiring(
            trusted_discovery,
    ) as wiring:
        wired, hook_calls = wiring

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

@runner.test('configured server wires configured action hook')
def configured_server_wires_configured_action_hook():
    with configured_server_wiring(
            trusted_discovery,
    ) as wiring:
        wired, hook_calls = wiring

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

@runner.test('configured server wires trusted action availability')
def configured_server_wires_trusted_action_availability():
    with configured_server_wiring(
            trusted_discovery,
    ) as wiring:
        wired, hook_calls = wiring

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

    testlib.assert_true(
        callable(
            wired['current_resolutions']
        )
    )

@runner.test('configured server checks current supported resolutions')
def configured_server_checks_current_supported_resolutions():
    calls = []

    def current_supported_resolutions(
            noc_url,
            open_url,
    ):
        calls.append(
            noc_url
        )

        return [
            'install-routerboot-update',
        ]

    with testlib.patched_attribute(
            trusted_discovery,
            'current_supported_resolutions',
            current_supported_resolutions,
    ):
        with configured_server_wiring(
                trusted_discovery,
        ) as wiring:
            wired, hook_calls = wiring

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
                [
                    'install-routerboot-update',
                ],
                wired['current_resolutions'](),
            )

    testlib.assert_same(
        [
            'https://noc.arcanel.se',
        ],
        calls,
    )

@runner.test('configured server reports no current resolutions when none are supported')
def configured_server_reports_no_trusted_actions_when_none_are_supported():
    def current_supported_resolutions(
            noc_url,
            open_url,
    ):
        return []

    with testlib.patched_attribute(
            trusted_discovery,
            'current_supported_resolutions',
            current_supported_resolutions,
    ):
        with configured_server_wiring(
                trusted_discovery,
        ) as wiring:
            wired, hook_calls = wiring

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
                [],
                wired['current_resolutions'](),
            )

@runner.test('configured discovery omits script without trusted actions')
def configured_discovery_omits_script_without_trusted_actions():
    paths = iter([
        '/configured-style',
    ])

    with testlib.patched_attribute(
            trusted_discovery,
            'current_supported_resolutions',
            lambda noc_url, open_url: [],
    ):
        with testlib.patched_attribute(
                trusted_discovery,
                'create_asset_path',
                lambda: next(paths),
        ):
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
                            body = probe(
                                server
                            )

    testlib.assert_same(
        (
            '{"assets":['
            '"https://bredland.example:8081/configured-style"'
            ']}'
        ),
        body,
    )

runner.finish()