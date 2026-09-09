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
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (create_test_server, load_trusted_discovery, fixture_loader, serving, server_url)


runner = TestSuiteRunner('trusted-discovery-script')

trusted_discovery = load_trusted_discovery()

@runner.test('trusted script includes capability for rendered resolution')
def trusted_script_includes_capability_for_rendered_resolution():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    load_noc_html = fixture_loader(
        'noc-with-resolution.html'
    )

    script = trusted_discovery.render_trusted_script(
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        'https://bredland.example:8081',
        load_noc_html,
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
        'https://bredland.example:8081',
        lambda: '<div data-resolution="install-routeros-update"></div>',
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

@runner.test('trusted script has no capabilities without rendered resolution')
def trusted_script_has_no_capabilities_without_rendered_resolution():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    load_noc_html = fixture_loader(
        'noc-without-resolution.html'
    )

    script = trusted_discovery.render_trusted_script(
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        'https://bredland.example:8081',
        load_noc_html,
        lambda: 'test-token',
        registry,
        200,
        lambda: 1788345803.417,
    )

    testlib.assert_string_contains('window.TRUSTED_CAPABILITIES = {};', script)
    testlib.assert_string_ends_with('window.TEST_TRUSTED_ASSET_LOADED = true;', script)


@runner.test('trusted script GET renders current capabilities')
def trusted_script_get_renders_current_capabilities():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    load_noc_html = fixture_loader(
        'noc-with-resolution.html'
    )

    def render(script_body):
        return trusted_discovery.render_trusted_script(
            script_body,
            'https://bredland.example:8081',
            load_noc_html,
            lambda: 'test-token',
            registry,
            200,
            lambda: 1788345803.417,
        )

    server = create_test_server(
        trusted_discovery,
        registry=registry,
        script_renderer=render,
    )

    with serving(server):
        response = urllib.request.urlopen(
            server_url(
                server,
                '/trusted-script-test',
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

    load_noc_html = fixture_loader(
        'noc-with-resolution.html'
    )

    renderer = trusted_discovery.create_trusted_script_renderer(
        'https://bredland.example:8081',
        load_noc_html,
        lambda: 'test-token',
        registry,
        lambda: 200,
        lambda: 1788345803.417,
    )

    script = renderer(
        'window.TEST_TRUSTED_ASSET_LOADED = true;'
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

runner.finish()
