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

import testlib
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import load_trusted_discovery
from trusted_discovery_testlib import stub_routeros_action_dependencies
from trusted_discovery_testlib import restore_routeros_action_dependencies


runner = TestSuiteRunner('trusted-discovery-discovery')
trusted_discovery = load_trusted_discovery()

@runner.test('renders the trusted discovery response')
def discovery_response_is_rendered():
    testlib.assert_same(
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

    testlib.assert_true(callable(create_server),
        'Expected trusted discovery to provide create_server()',
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
        None,
        None,
        lambda resolution: True,
        trusted_discovery.ActionGuard(
            lambda: 100,
            30,
        ),
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

        testlib.assert_same(200, response.status)
        testlib.assert_same(
            'application/json',
            response.headers.get_content_type(),
        )
        testlib.assert_same(
            'no-store',
            response.headers.get('Cache-Control'),
        )
        testlib.assert_same(
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

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/probe'.format(
                server.server_port,
            ),
        )

        testlib.assert_same(200, response.status)

        try:
            urllib.request.urlopen(
                'http://127.0.0.1:{}/anything'.format(
                    server.server_port,
                ),
            )
        except urllib.error.HTTPError as error:
            testlib.assert_same(404, error.code)
        else:
            testlib.fail('Expected unrelated path to return 404')
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
        target=server.handle_request,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/probe'.format(
                server.server_port,
            ),
        )

        testlib.assert_same(
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
    testlib.assert_same(
        'https://bredland.example:8081',
        getattr(
            trusted_discovery,
            'TRUSTED_BASE_URL',
            None,
        ),
    )

    testlib.assert_same(
        'https://noc.arcanel.se',
        getattr(
            trusted_discovery,
            'TRUSTED_ALLOWED_ORIGIN',
            None,
        ),
    )

    testlib.assert_same(
        '/trusted-script-test',
        getattr(
            trusted_discovery,
            'TRUSTED_SCRIPT_PATH',
            None,
        ),
    )

    testlib.assert_same(
        '/trusted-style-test',
        getattr(
            trusted_discovery,
            'TRUSTED_STYLESHEET_PATH',
            None,
        ),
    )

runner.finish()