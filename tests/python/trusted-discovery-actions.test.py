import json
import os
import sys
import tempfile
import time
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

import testlib
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import load_trusted_discovery
from trusted_discovery_testlib import stub_routeros_action_dependencies
from trusted_discovery_testlib import restore_routeros_action_dependencies

runner = TestSuiteRunner('trusted-discovery-actions')
trusted_discovery = load_trusted_discovery()

@runner.test('maps supported resolution to RouterOS script')
def supported_resolution_maps_to_routeros_script():
    testlib.assert_same(
        'noc-trusted-action-test',
        trusted_discovery.routeros_script_for_resolution(
            'install-routeros-update',
        ),
    )

@runner.test('maps unsupported resolution to nothing')
def unsupported_resolution_maps_to_nothing():
    testlib.assert_same(
        None,
        trusted_discovery.routeros_script_for_resolution(
            'launch-missiles',
        ),
    )

@runner.test('action endpoint executes supported action resolution')
def action_endpoint_executes_supported_resolution():
    calls = []

    def execute(script_name):
        calls.append(script_name)
        return True

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        execute,
        registry,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=json.dumps(
                {
                    'resolution': 'install-routeros-update',
                    'token': 'test-token',
                }
            ).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        response = urllib.request.urlopen(request)

        testlib.assert_same(200, response.status)
        testlib.assert_same(
            'https://noc.arcanel.se',
            response.headers.get(
                'Access-Control-Allow-Origin',
            ),
        )
        testlib.assert_same(
            ['noc-trusted-action-test'],
            calls,
        )
    finally:
        thread.join()
        server.server_close()

@runner.test('action endpoint rejects unsupported action resolution')
def action_endpoint_rejects_unsupported_resolution():
    calls = []

    def execute(script_name):
        calls.append(script_name)
        return True

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        execute,
        registry,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=json.dumps(
                {
                    'resolution': 'launch-missiles',
                    'token': 'test-token',
                }
            ).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            testlib.assert_same(400, error.code)
        else:
            testlib.fail('Expected unsupported resolution to return 400')

        testlib.assert_same([], calls)
    finally:
        thread.join()
        server.server_close()

@runner.test('rejects trusted action from wrong origin')
def action_endpoint_rejects_wrong_origin():
    calls = []

    def execute(script_name):
        calls.append(script_name)
        return True

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        execute,
        None,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=json.dumps(
                {
                    'resolution': 'install-routeros-update',
                }
            ).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://evil.example',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            testlib.assert_same(403, error.code)
        else:
            testlib.fail('Expected wrong origin to return 403')

        testlib.assert_same([], calls)
    finally:
        thread.join()
        server.server_close()

@runner.test('allows trusted action CORS preflight')
def action_endpoint_allows_preflight_from_noc_origin():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        lambda script_name: True,
        None,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            headers={
                'Origin': 'https://noc.arcanel.se',
                'Access-Control-Request-Method': 'POST',
                'Access-Control-Request-Headers': 'Content-Type',
            },
            method='OPTIONS',
        )

        response = urllib.request.urlopen(request)

        testlib.assert_same(204, response.status)
        testlib.assert_same(
            'https://noc.arcanel.se',
            response.headers.get(
                'Access-Control-Allow-Origin',
            ),
        )
        testlib.assert_same(
            'POST',
            response.headers.get(
                'Access-Control-Allow-Methods',
            ),
        )
        testlib.assert_same(
            'Content-Type',
            response.headers.get(
                'Access-Control-Allow-Headers',
            ),
        )
    finally:
        thread.join()
        server.server_close()

@runner.test('action endpoint rejects malformed JSON')
def action_endpoint_rejects_malformed_json():
    calls = []

    def execute(script_name):
        calls.append(script_name)
        return True

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        execute,
        None,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=b'{ definitely-not-json',
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            testlib.assert_same(400, error.code)
        else:
            testlib.fail('Expected malformed JSON to return 400')

        testlib.assert_same([], calls)
    finally:
        thread.join()
        server.server_close()

@runner.test('action endpoint rejects non-object JSON')
def action_endpoint_rejects_non_object_json():
    calls = []

    def execute(script_name):
        calls.append(script_name)
        return True

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        execute,
        None,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=b'[]',
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            testlib.assert_same(
                400,
                error.code,
            )
        else:
            testlib.fail('Expected non-object JSON to return 400')

        testlib.assert_same(
            [],
            calls,
        )
    finally:
        thread.join()
        server.server_close()

@runner.test('action endpoint rejects non-string fields')
def action_endpoint_rejects_non_string_fields():
    calls = []

    def execute(script_name):
        calls.append(script_name)
        return True

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        execute,
        registry,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=json.dumps(
                {
                    'resolution': 123,
                    'token': [],
                }
            ).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            testlib.assert_same(
                400,
                error.code,
            )
        else:
            testlib.fail('Expected non-string fields to return 400')

        testlib.assert_same(
            [],
            calls,
        )
    finally:
        thread.join()
        server.server_close()

@runner.test('action endpoint rejects missing resolution')
def action_endpoint_rejects_missing_resolution():
    calls = []

    def execute(script_name):
        calls.append(script_name)
        return True

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        execute,
        registry,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=json.dumps({ 'token': 'test-token', }).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            testlib.assert_same(400, error.code)
        else:
            testlib.fail('Expected missing resolution to return 400')

        testlib.assert_same([], calls)
    finally:
        thread.join()
        server.server_close()

@runner.test('discovers rendered resolution from NOC HTML')
def discovers_rendered_resolution_from_noc_html():
    fixture_path = os.path.join(
        os.path.dirname(__file__),
        'fixtures',
        'trusted-discovery',
        'noc-with-resolution.html',
    )

    with open(fixture_path, 'r') as fixture:
        html = fixture.read()

    testlib.assert_same(
        ['install-routeros-update'],
        trusted_discovery.resolutions_from_noc_html(html),
    )

@runner.test('discovers no resolution from NOC HTML without one')
def discovers_no_resolution_from_noc_html():
    fixture_path = os.path.join(
        os.path.dirname(__file__),
        'fixtures',
        'trusted-discovery',
        'noc-without-resolution.html',
    )

    with open(fixture_path, 'r') as fixture:
        html = fixture.read()

    testlib.assert_same(
        [],
        trusted_discovery.resolutions_from_noc_html(html),
    )

@runner.test('keeps only supported rendered resolutions')
def keeps_only_supported_rendered_resolutions():
    testlib.assert_same(
        ['install-routeros-update'],
        trusted_discovery.supported_rendered_resolutions(
            [
                'install-routeros-update',
                'future-resolution',
            ]
        ),
    )

@runner.test('issues capability for supported rendered resolution')
def issues_capability_for_supported_rendered_resolution():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    capabilities = trusted_discovery.issue_capabilities(
        ['install-routeros-update'],
        lambda: 'test-token',
        registry,
        200,
    )

    testlib.assert_same(
        {
            'install-routeros-update': 'test-token',
        },
        capabilities,
    )

@runner.test('consumes capability only once')
def consumes_capability_only_once():
    registry = trusted_discovery.CapabilityRegistry(
    lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

    testlib.assert_same(
        'noc-trusted-action-test',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

    testlib.assert_same(
        None,
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

@runner.test('consumes capability atomically across threads')
def consumes_capability_atomically_across_threads():
    results = []
    errors = []

    class SlowCapabilities(dict):
        def get(self, token):
            capability = dict.get(
                self,
                token,
            )

            if capability is not None:
                time.sleep(0.05)

            return capability

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.capabilities = SlowCapabilities()

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

    def consume():
        try:
            results.append(
                registry.consume(
                    'install-routeros-update',
                    'test-token',
                )
            )
        except Exception as error:
            errors.append(
                error.__class__.__name__
            )

    threads = [
        threading.Thread(
            target=consume,
        ),
        threading.Thread(
            target=consume,
        ),
    ]

    for thread in threads:
        thread.start()

    for thread in threads:
        thread.join()

    testlib.assert_same(
        [],
        errors,
    )

    testlib.assert_same(
        2,
        len(results),
    )

    testlib.assert_same(
        1,
        results.count(
            'noc-trusted-action-test',
        ),
    )

    testlib.assert_same(
        1,
        results.count(None),
    )

@runner.test('does not consume capability for wrong resolution')
def does_not_consume_capability_for_wrong_resolution():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

    testlib.assert_same(
        None,
        registry.consume(
            'different-resolution',
            'test-token',
        ),
    )

    testlib.assert_same(
        'noc-trusted-action-test',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

@runner.test('rejects expired capability')
def rejects_expired_capability():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        110,
    )

    testlib.assert_same(
        'noc-trusted-action-test',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

    registry.register(
        'install-routeros-update',
        'expired-token',
        'noc-trusted-action-test',
        90,
    )

    testlib.assert_same(
        None,
        registry.consume(
            'install-routeros-update',
            'expired-token',
        ),
    )

@runner.test('removes expired capability when consumed')
def removes_expired_capability_when_consumed():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'expired-token',
        'noc-trusted-action-test',
        90,
    )

    testlib.assert_same(
        None,
        registry.consume(
            'install-routeros-update',
            'expired-token',
        ),
    )

    testlib.assert_same(
        None,
        registry.consume(
            'install-routeros-update',
            'expired-token',
        ),
    )

@runner.test('action endpoint consumes capability before execution')
def action_endpoint_consumes_capability_before_execution():
    calls = []

    def execute(script_name):
        calls.append(script_name)
        return True

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        execute,
        registry,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=json.dumps(
                {
                    'resolution': 'install-routeros-update',
                    'token': 'test-token',
                }
            ).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        response = urllib.request.urlopen(request)

        testlib.assert_same(200, response.status)
        testlib.assert_same(
            ['noc-trusted-action-test'],
            calls,
        )

        testlib.assert_same(
            None,
            registry.consume(
                'install-routeros-update',
                'test-token',
            ),
        )
    finally:
        thread.join()
        server.server_close()

@runner.test('action endpoint rejects replayed capability')
def action_endpoint_rejects_replayed_capability():
    calls = []

    def execute(script_name):
        calls.append(script_name)
        return True

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        execute,
        registry,
        None,
    )

    thread = threading.Thread(
        target=lambda: (
            server.handle_request(),
            server.handle_request(),
        ),
    )
    thread.start()

    try:
        def request():
            return urllib.request.Request(
                'http://127.0.0.1:{}/action'.format(
                    server.server_port,
                ),
                data=json.dumps(
                    {
                        'resolution': 'install-routeros-update',
                        'token': 'test-token',
                    }
                ).encode('utf-8'),
                headers={
                    'Content-Type': 'application/json',
                    'Origin': 'https://noc.arcanel.se',
                },
                method='POST',
            )

        first_response = urllib.request.urlopen(
            request()
        )
        testlib.assert_same(
            200,
            first_response.status,
        )

        try:
            urllib.request.urlopen(
                request()
            )
        except urllib.error.HTTPError as error:
            testlib.assert_same(
                400,
                error.code,
            )
            testlib.assert_same(
                'https://noc.arcanel.se',
                error.headers.get(
                    'Access-Control-Allow-Origin',
                ),
            )
        else:
            testlib.fail('Expected replayed capability to be rejected')

        testlib.assert_same(
            ['noc-trusted-action-test'],
            calls,
        )
    finally:
        thread.join()
        server.server_close()

@runner.test('registers issued capability')
def registers_issued_capability():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    capabilities = trusted_discovery.issue_capabilities(
        ['install-routeros-update'],
        lambda: 'test-token',
        registry,
        200,
    )

    testlib.assert_same(
        {
            'install-routeros-update': 'test-token',
        },
        capabilities,
    )

    testlib.assert_same(
        'noc-trusted-action-test',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

@runner.test('trusted script includes capability for rendered resolution')
def trusted_script_includes_capability_for_rendered_resolution():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    fixture_path = os.path.join(
        os.path.dirname(__file__),
        'fixtures',
        'trusted-discovery',
        'noc-with-resolution.html',
    )

    def load_noc_html():
        with open(fixture_path, 'r') as fixture:
            return fixture.read()

    script = trusted_discovery.render_trusted_script(
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        'https://bredland.example:8081',
        load_noc_html,
        lambda: 'test-token',
        registry,
        200,
    )

    testlib.assert_string_contains(
        '"install-routeros-update": "test-token"',
        script,
    )

    testlib.assert_string_contains('window.TRUSTED_BASE_URL = "https://bredland.example:8081";', script)

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

    fixture_path = os.path.join(
        os.path.dirname(__file__),
        'fixtures',
        'trusted-discovery',
        'noc-without-resolution.html',
    )

    def load_noc_html():
        with open(fixture_path, 'r') as fixture:
            return fixture.read()

    script = trusted_discovery.render_trusted_script(
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        'https://bredland.example:8081',
        load_noc_html,
        lambda: 'test-token',
        registry,
        200,
    )

    testlib.assert_string_contains('window.TRUSTED_CAPABILITIES = {};', script)
    testlib.assert_string_ends_with('window.TEST_TRUSTED_ASSET_LOADED = true;', script)


@runner.test('trusted script GET renders current capabilities')
def trusted_script_get_renders_current_capabilities():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    fixture_path = os.path.join(
        os.path.dirname(__file__),
        'fixtures',
        'trusted-discovery',
        'noc-with-resolution.html',
    )

    def load_noc_html():
        with open(fixture_path, 'r') as fixture:
            return fixture.read()

    def render(script_body):
        return trusted_discovery.render_trusted_script(
            script_body,
            'https://bredland.example:8081',
            load_noc_html,
            lambda: 'test-token',
            registry,
            200,
        )

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        lambda script_name: True,
        registry,
        render,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/trusted-script-test'.format(
                server.server_port,
            )
        )

        script = response.read().decode('utf-8')

        testlib.assert_string_contains('"install-routeros-update": "test-token"', script)
    finally:
        thread.join()
        server.server_close()

@runner.test('fetches current NOC HTML')
def fetches_current_noc_html():
    requested_urls = []

    class Response:
        def read(self):
            return b'<html>current NOC</html>'

    def open_url(url):
        requested_urls.append(url)
        return Response()

    html = trusted_discovery.fetch_noc_html(
        'https://noc.arcanel.se',
        open_url,
    )

    testlib.assert_same(
        ['https://noc.arcanel.se/'],
        requested_urls,
    )

    testlib.assert_same(
        '<html>current NOC</html>',
        html,
    )

@runner.test('generates cryptographically random capability token')
def generates_cryptographically_random_capability_token():
    tokens = iter([
        b'\x01\x02\x03\x04',
    ])

    def token_bytes(length):
        testlib.assert_same(32, length)
        return next(tokens)

    testlib.assert_same(
        '01020304',
        trusted_discovery.generate_capability_token(
            token_bytes,
        ),
    )

@runner.test('creates capability token')
def creates_capability_token():
    token = trusted_discovery.create_capability_token()

    testlib.assert_same(
        64,
        len(token),
    )

@runner.test('calculates capability expiry')
def calculates_capability_expiry():
    testlib.assert_same(
        130,
        trusted_discovery.capability_expiry(
            lambda: 100,
            30,
        ),
    )

@runner.test('creates trusted script renderer')
def creates_trusted_script_renderer():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    fixture_path = os.path.join(
        os.path.dirname(__file__),
        'fixtures',
        'trusted-discovery',
        'noc-with-resolution.html',
    )

    def load_noc_html():
        with open(fixture_path, 'r') as fixture:
            return fixture.read()

    renderer = trusted_discovery.create_trusted_script_renderer(
        'https://bredland.example:8081',
        load_noc_html,
        lambda: 'test-token',
        registry,
        lambda: 200,
    )

    script = renderer(
        'window.TEST_TRUSTED_ASSET_LOADED = true;'
    )

    testlib.assert_string_contains('"install-routeros-update": "test-token"', script)
    testlib.assert_same(
        'noc-trusted-action-test',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

@runner.test('executes RouterOS script through REST')
def executes_routeros_script_through_rest():
    calls = []

    def post(url, body):
        calls.append(
            (
                url,
                body,
            )
        )

        return True

    result = trusted_discovery.execute_routeros_script(
        'https://192.168.88.1',
        'noc-trusted-action-test',
        post,
    )

    testlib.assert_same(
        [
            (
                'https://192.168.88.1/rest/system/script/run',
                {
                    '.id': 'noc-trusted-action-test',
                },
            )
        ],
        calls,
    )

    testlib.assert_true(result)

@runner.test('posts JSON to RouterOS REST')
def posts_json_to_routeros_rest():
    calls = []

    class Response:
        status = 200

    def open_request(request, context=None):
        calls.append(
            (
                request.full_url,
                request.get_method(),
                request.data,
                request.headers,
                context,
            )
        )

        return Response()

    result = trusted_discovery.post_json(
        'https://192.168.88.1/rest/system/script/run',
        {
            '.id': 'noc-trusted-action-test',
        },
        {
            'Authorization': 'Basic test',
        },
        'test-context',
        open_request,
    )

    testlib.assert_true(result)

    testlib.assert_same(
        'https://192.168.88.1/rest/system/script/run',
        calls[0][0],
    )

    testlib.assert_same(
        'POST',
        calls[0][1],
    )

    testlib.assert_same(
        b'{".id":"noc-trusted-action-test"}',
        calls[0][2],
    )

    testlib.assert_same(
        'Basic test',
        calls[0][3].get(
            'Authorization',
        ),
    )

    testlib.assert_same(
        'application/json',
        calls[0][3].get(
            'Content-type',
        ),
    )

    testlib.assert_same(
        'test-context',
        calls[0][4],
    )

@runner.test('loads RouterOS REST credentials')
def loads_routeros_rest_credentials():
    with tempfile.TemporaryDirectory() as tmpdir:
        credentials_file = os.path.join(
            tmpdir,
            'credentials.env',
        )

        with open(credentials_file, 'w') as file:
            file.write(
                'MIKROTIK_REST_USER=noc-rest-bredland\n'
                'MIKROTIK_REST_PASSWORD=test-password\n'
            )

        credentials = (
            trusted_discovery.load_routeros_rest_credentials(
                credentials_file,
            )
        )

        testlib.assert_same(
            {
                'username': 'noc-rest-bredland',
                'password': 'test-password',
            },
            credentials,
        )

@runner.test('builds RouterOS REST authorization header')
def builds_routeros_rest_authorization_header():
    testlib.assert_same(
        'Basic bm9jLXJlc3QtYnJlZGxhbmQ6dGVzdC1wYXNzd29yZA==',
        trusted_discovery.routeros_rest_authorization(
            'noc-rest-bredland',
            'test-password',
        ),
    )

@runner.test('creates RouterOS REST TLS context')
def creates_routeros_rest_tls_context():
    calls = []

    class FakeSsl:
        @staticmethod
        def create_default_context(cafile=None):
            calls.append(
                cafile
            )

            return 'tls-context'

    original_ssl = trusted_discovery.ssl
    trusted_discovery.ssl = FakeSsl

    try:
        context = (
            trusted_discovery.create_routeros_rest_tls_context(
                '/etc/bredland/mikrotik-rest/ca.pem',
            )
        )
    finally:
        trusted_discovery.ssl = original_ssl

    testlib.assert_same(
        [
            '/etc/bredland/mikrotik-rest/ca.pem',
        ],
        calls,
    )

    testlib.assert_same(
        'tls-context',
        context,
    )

@runner.test('creates authenticated RouterOS REST poster')
def creates_authenticated_routeros_rest_poster():
    calls = []

    def fake_post_json(
            url,
            body,
            headers,
            context,
            open_request,
    ):
        calls.append(
            (
                url,
                body,
                headers,
                context,
                open_request,
            )
        )

        return True

    credentials = {
        'username': 'noc-rest-bredland',
        'password': 'test-password',
    }

    poster = trusted_discovery.create_routeros_rest_poster(
        credentials,
        'tls-context',
        'open-request',
        fake_post_json,
    )

    result = poster(
        'https://192.168.88.1/rest/system/script/run',
        {
            '.id': 'noc-trusted-action-test',
        },
    )

    testlib.assert_true(result)

    testlib.assert_same(
        'Basic bm9jLXJlc3QtYnJlZGxhbmQ6dGVzdC1wYXNzd29yZA==',
        calls[0][2].get(
            'Authorization',
        ),
    )

    testlib.assert_same(
        'tls-context',
        calls[0][3],
    )

    testlib.assert_same(
        'open-request',
        calls[0][4],
    )

@runner.test('creates RouterOS action executor')
def creates_routeros_action_executor():
    calls = []

    def post(url, body):
        calls.append(
            (
                url,
                body,
            )
        )

        return True

    executor = trusted_discovery.create_routeros_action_executor(
        'https://192.168.88.1',
        post,
    )

    result = executor(
        'noc-trusted-action-test',
    )

    testlib.assert_true(result)

    testlib.assert_same(
        [
            (
                'https://192.168.88.1/rest/system/script/run',
                {
                    '.id': 'noc-trusted-action-test',
                },
            )
        ],
        calls,
    )

@runner.test('action endpoint reports executor failure')
def action_endpoint_reports_executor_failure():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        lambda script_name: False,
        registry,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=json.dumps(
                {
                    'resolution': 'install-routeros-update',
                    'token': 'test-token',
                }
            ).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            testlib.assert_same(
                500,
                error.code,
            )
        else:
            testlib.fail('Expected executor failure to return 500')
    finally:
        thread.join()
        server.server_close()

@runner.test('action endpoint handles executor exception')
def action_endpoint_handles_executor_exception():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

    def execute(script_name):
        raise RuntimeError(
            'RouterOS unavailable'
        )

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        execute,
        registry,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=json.dumps(
                {
                    'resolution': 'install-routeros-update',
                    'token': 'test-token',
                }
            ).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            testlib.assert_same(
                500,
                error.code,
            )
        else:
            testlib.fail('Expected executor exception to return 500')
    finally:
        thread.join()
        server.server_close()

@runner.test('action endpoint reports missing executor with CORS')
def action_endpoint_reports_missing_executor_with_cors():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

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
        registry,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=json.dumps(
                {
                    'resolution': 'install-routeros-update',
                    'token': 'test-token',
                }
            ).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            testlib.assert_same(
                500,
                error.code,
            )

            testlib.assert_same(
                'https://noc.arcanel.se',
                error.headers.get(
                    'Access-Control-Allow-Origin',
                ),
            )
        else:
            testlib.fail('Expected missing executor to return 500')
    finally:
        thread.join()
        server.server_close()

@runner.test('action endpoint reports missing registry with CORS')
def action_endpoint_reports_missing_registry_with_cors():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
        lambda script_name: True,
        None,
        None,
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:{}/action'.format(
                server.server_port,
            ),
            data=json.dumps(
                {
                    'resolution': 'install-routeros-update',
                    'token': 'test-token',
                }
            ).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
                'Origin': 'https://noc.arcanel.se',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(request)
        except urllib.error.HTTPError as error:
            testlib.assert_same(
                500,
                error.code,
            )

            testlib.assert_same(
                'https://noc.arcanel.se',
                error.headers.get(
                    'Access-Control-Allow-Origin',
                ),
            )
        else:
            testlib.fail('Expected missing registry to return 500')
    finally:
        thread.join()
        server.server_close()

runner.finish()