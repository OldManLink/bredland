import os
import sys
import threading
import urllib

sys.path.insert(
    0,
    os.path.join(
        os.path.dirname(__file__),
        'lib',
    ),
)

import testlib
from builtins import (iter)
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (create_test_server, load_trusted_discovery, probe, fixture_loader, serving, server_url)


runner = TestSuiteRunner('trusted-discovery-script')

trusted_discovery = load_trusted_discovery()

@runner.test('trusted script includes capability for rendered resolution')
def trusted_script_includes_capability_for_rendered_resolution():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    script = trusted_discovery.render_trusted_script(
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        [
            'install-routeros-update',
        ],
        'https://bredland.example:8081',
        lambda: 'test-token',
        registry,
        200,
        lambda: 1788345803.417,
    )

    testlib.assert_string_contains(
        '"install-routeros-update": "test-token"',
        script,
    )

    testlib.assert_string_contains('window.TRUSTED_BASE_URL = "https://bredland.example:8081";', script)

    testlib.assert_string_contains(
        'window.TRUSTED_SERVER_TIME = 1788345803417;',
        script,
    )

@runner.test('trusted script preserves static banner first')
def trusted_script_preserves_static_banner_first():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    script = trusted_discovery.render_trusted_script(
        '// Bredland trusted-mode JavaScript\n'
        '// Served only from the trusted network\n'
        '// BRD-030 trusted discovery asset\n'
        '\n'
        'console.log("trusted");',
        ['install-routeros-update'],
        'https://bredland.example:8081',
        lambda: 'test-token',
        registry,
        200,
        lambda: 1788345803.417,
    )

    testlib.assert_same(
        '// Bredland trusted-mode JavaScript\n'
        '// Served only from the trusted network\n'
        '// BRD-030 trusted discovery asset',
        '\n'.join(
            script.splitlines()[:3]
        ),
    )

@runner.test('trusted script GET renders current capabilities')
def trusted_script_get_renders_current_capabilities():
    paths = iter([
        '/generated-style',
        '/generated-script',
    ])

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    def render(
            script_body,
            resolutions,
    ):
        return trusted_discovery.render_trusted_script(
            script_body,
            resolutions,
            'https://bredland.example:8081',
            lambda: 'test-token',
            registry,
            200,
            lambda: 1788345803.417,
        )

    server = create_test_server(
        trusted_discovery,
        registry=registry,
        script_renderer=render,
        asset_path_factory=lambda: next(paths),
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

        script = response.read().decode('utf-8')

        testlib.assert_string_contains(
            '"install-routeros-update": "test-token"',
            script,
        )

@runner.test('creates trusted script renderer')
def creates_trusted_script_renderer():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    renderer = trusted_discovery.create_trusted_script_renderer(
        'https://bredland.example:8081',
        lambda: 'test-token',
        registry,
        lambda: 200,
        lambda: 1788345803.417,
    )

    script = renderer(
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        ['install-routeros-update'],
    )

    testlib.assert_string_contains('"install-routeros-update": "test-token"', script)
    testlib.assert_same(
        'noc-install-routeros-update',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )
    testlib.assert_string_contains(
        'window.TRUSTED_SERVER_TIME = 1788345803417;',
        script,
    )

@runner.test('trusted script renders action placeholder')
def trusted_script_renders_action_placeholder():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    with testlib.patched_attribute(
            trusted_discovery,
            'TRUSTED_ACTION_DEFINITIONS',
            {
                'test-resolution': {
                    'script': 'test-script',
                    'confirmation': 'Perform the test action?',
                },
            },
    ):
        script = trusted_discovery.render_trusted_script(
            '__TRUSTED_ACTIONS__',
            [
                'test-resolution',
            ],
            'https://bredland.example:8081',
            lambda: 'test-token',
            registry,
            200,
            lambda: 1788345803.417,
        )

    testlib.assert_string_contains(
        "render_trusted_action(\n"
        "    'test-resolution',\n"
        "    'Perform the test action?'\n"
        ");",
        script,
    )

    testlib.assert_string_not_contains(
        '__TRUSTED_ACTIONS__',
        script,
    )

@runner.test('maps trusted resolution to confirmation')
def maps_trusted_resolution_to_confirmation():
    with testlib.patched_attribute(
            trusted_discovery,
            'TRUSTED_ACTION_DEFINITIONS',
            {
                'test-resolution': {
                    'script': 'test-script',
                    'confirmation': 'Perform the test action?',
                },
            },
    ):
        testlib.assert_same(
            'Perform the test action?',
            trusted_discovery.confirmation_for_resolution(
                'test-resolution'
            ),
        )

@runner.test('trusted script renders multiple actions in order')
def trusted_script_renders_multiple_actions_in_order():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    with testlib.patched_attribute(
            trusted_discovery,
            'TRUSTED_ACTION_DEFINITIONS',
            {
                'first-resolution': {
                    'script': 'first-script',
                    'confirmation': 'Perform the first action?',
                },
                'second-resolution': {
                    'script': 'second-script',
                    'confirmation': 'Perform the second action?',
                },
            },
    ):
        script = trusted_discovery.render_trusted_script(
            '__TRUSTED_ACTIONS__',
            [
                'first-resolution',
                'second-resolution',
            ],
            'https://bredland.example:8081',
            lambda: 'test-token',
            registry,
            200,
            lambda: 1788345803.417,
        )

    expected = (
        "render_trusted_action(\n"
        "    'first-resolution',\n"
        "    'Perform the first action?'\n"
        ");\n\n"
        "render_trusted_action(\n"
        "    'second-resolution',\n"
        "    'Perform the second action?'\n"
        ");"
    )

    testlib.assert_string_contains(
        expected,
        script,
    )

runner.finish()
