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
from builtins import (iter, next)
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
        {
            'routeros_staged': True,
        },
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
        {'routeros_staged': True},
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
            state,
    ):
        testlib.assert_same(
            {'routeros_staged': True},
            state,
        )

        return trusted_discovery.render_trusted_script(
            script_body,
            resolutions,
            state,
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
        state_reader=lambda: {'routeros_staged': True},
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
        {
            'routeros_staged': True,
        }
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
                    'button_text': 'Test',
                    'confirmation': 'Perform the test action?',
                    'accepted_message': 'Test action requested',
                    'success_message': 'Test action complete',
                },
            },
    ):
        script = trusted_discovery.render_trusted_script(
            '__TRUSTED_ACTIONS__',
            [
                'test-resolution',
            ],
            {
                'routeros_staged': True,
            },
            'https://bredland.example:8081',
            lambda: 'test-token',
            registry,
            200,
            lambda: 1788345803.417,
        )

    testlib.assert_string_contains(
        "render_trusted_action(\n"
        "    'test-resolution',\n"
        "    'Test',\n"
        "    'Perform the test action?',\n"
        "    'Test action requested',\n"
        "    'Test action complete'\n"
        ");",
        script,
    )

    testlib.assert_string_not_contains(
        '__TRUSTED_ACTIONS__',
        script,
    )

@runner.test('unstaged RouterOS update selects download action')
def unstaged_routeros_update_selects_download_action():
    action = trusted_discovery.trusted_action_for_resolution(
        'install-routeros-update',
        {
            'routeros_staged': False,
        },
    )

    testlib.assert_same(
        'noc-download-routeros-update',
        action['script'],
    )

@runner.test('staged RouterOS update selects install action')
def staged_routeros_update_selects_install_action():
    action = trusted_discovery.trusted_action_for_resolution(
        'install-routeros-update',
        {
            'routeros_staged': True,
        },
    )

    testlib.assert_same(
        'noc-install-routeros-update',
        action['script'],
    )

@runner.test('unstaged RouterOS capability binds download script')
def unstaged_routeros_capability_binds_download_script():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    trusted_discovery.render_trusted_script(
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        [
            'install-routeros-update',
        ],
        {
            'routeros_staged': False,
        },
        'https://bredland.example:8081',
        lambda: 'test-token',
        registry,
        200,
        lambda: 1788345803.417,
    )

    testlib.assert_same(
        'noc-download-routeros-update',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

@runner.test('unstaged RouterOS update presents download action')
def unstaged_routeros_update_presents_download_action():
    action = trusted_discovery.trusted_action_for_resolution(
        'install-routeros-update',
        {
            'routeros_staged': False,
        },
    )

    testlib.assert_same(
        'Download',
        action['button_text'],
    )

    testlib.assert_same(
        'Download the available RouterOS update?',
        action['confirmation'],
    )

    testlib.assert_same(
        'Download requested',
        action['accepted_message'],
    )

    testlib.assert_same(
        'Download complete',
        action['success_message'],
    )

@runner.test('staged RouterOS update presents install action')
def staged_routeros_update_presents_install_action():
    action = trusted_discovery.trusted_action_for_resolution(
        'install-routeros-update',
        {
            'routeros_staged': True,
        },
    )

    testlib.assert_same(
        'Update',
        action['button_text'],
    )

    testlib.assert_same(
        'Install the downloaded RouterOS update and reboot?',
        action['confirmation'],
    )

    testlib.assert_same(
        'Update requested',
        action['accepted_message'],
    )

    testlib.assert_same(
        'Router rebooting',
        action['success_message'],
    )

@runner.test('trusted script renders selected RouterOS action presentation')
def trusted_script_renders_selected_routeros_action_presentation():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    script = trusted_discovery.render_trusted_script(
        '__TRUSTED_ACTIONS__',
        [
            'install-routeros-update',
        ],
        {
            'routeros_staged': False,
        },
        'https://bredland.example:8081',
        lambda: 'test-token',
        registry,
        200,
        lambda: 1788345803.417,
    )

    testlib.assert_string_contains(
        "render_trusted_action(\n"
        "    'install-routeros-update',\n"
        "    'Download',\n"
        "    'Download the available RouterOS update?',\n"
        "    'Download requested',\n"
        "    'Download complete'\n"
        ");",
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
                    'button_text': 'First',
                    'confirmation': 'Perform the first action?',
                    'accepted_message': 'First action requested',
                    'success_message': 'First action complete',
                },
                'second-resolution': {
                    'script': 'second-script',
                    'button_text': 'Second',
                    'confirmation': 'Perform the second action?',
                    'accepted_message': 'Second action requested',
                    'success_message': 'Second action complete',
                },
            },
    ):
        script = trusted_discovery.render_trusted_script(
            '__TRUSTED_ACTIONS__',
            [
                'first-resolution',
                'second-resolution',
            ],
            {
                'routeros_staged': True,
            },
            'https://bredland.example:8081',
            lambda: 'test-token',
            registry,
            200,
            lambda: 1788345803.417,
        )

    expected = (
        "render_trusted_action(\n"
        "    'first-resolution',\n"
        "    'First',\n"
        "    'Perform the first action?',\n"
        "    'First action requested',\n"
        "    'First action complete'\n"
        ");\n\n"
        "render_trusted_action(\n"
        "    'second-resolution',\n"
        "    'Second',\n"
        "    'Perform the second action?',\n"
        "    'Second action requested',\n"
        "    'Second action complete'\n"
        ");"
    )

    testlib.assert_string_contains(
        expected,
        script,
    )

runner.finish()
