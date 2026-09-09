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

import testlib
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (create_test_server, load_trusted_discovery, server_url, serving, stubbed_routeros_action_dependencies)


runner = TestSuiteRunner('trusted-discovery-assets')
trusted_discovery = load_trusted_discovery()

@runner.test('serves the configured trusted script')
def trusted_script_is_served():
    server = create_test_server(
        trusted_discovery,
    )

    with serving(server):
        response = urllib.request.urlopen(
            server_url(
                server,
                '/trusted-script-test',
            )
        )

        body = response.read().decode('utf-8')

    testlib.assert_same(
        200,
        response.status,
    )

    testlib.assert_same(
        'application/javascript',
        response.headers.get_content_type(),
    )

    testlib.assert_same(
        'no-store',
        response.headers.get(
            'Cache-Control',
        ),
    )

    testlib.assert_same(
        'window.TEST_TRUSTED_ASSET_LOADED = true;',
        body,
    )

@runner.test('serves the configured trusted stylesheet')
def trusted_stylesheet_is_served():
    server = create_test_server(
        trusted_discovery,
    )

    with serving(server):
        response = urllib.request.urlopen(
            server_url(
                server,
                '/trusted-style-test',
            )
        )

        body = response.read().decode('utf-8')

    testlib.assert_same(
        200,
        response.status,
    )

    testlib.assert_same(
        'text/css',
        response.headers.get_content_type(),
    )

    testlib.assert_same(
        'no-store',
        response.headers.get(
            'Cache-Control',
        ),
    )

    testlib.assert_same(
        'html { outline: 1px solid; }',
        body,
    )

@runner.test('discovery advertises the served asset paths')
def discovery_urls_match_served_asset_paths():
    server = create_test_server(
        trusted_discovery,
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
        '{"assets":{"script":"https://bredland.example/trusted-script-test",'
        '"stylesheet":"https://bredland.example/trusted-style-test"}}',
        body,
    )

runner.finish()