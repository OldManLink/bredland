import json
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
from builtins import (iter, next)
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (create_test_server, load_trusted_discovery, probe, server_url, serving,
                                       TEST_STYLESHEET_BODY, TEST_SCRIPT_BODY)


runner = TestSuiteRunner('trusted-discovery-assets')
trusted_discovery = load_trusted_discovery()

@runner.test('serves a generated trusted script')
def generated_trusted_script_is_served():
    paths = iter([
        '/generated-style',
        '/generated-script',
    ])

    server = create_test_server(
        trusted_discovery,
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
        TEST_SCRIPT_BODY,
        body,
    )

@runner.test('serves a generated trusted stylesheet')
def generated_trusted_stylesheet_is_served():
    paths = iter([
        '/generated-style',
        '/generated-script',
    ])

    server = create_test_server(
        trusted_discovery,
        asset_path_factory=lambda: next(paths),
    )

    with serving(server):
        probe(
            server
        )

        response = urllib.request.urlopen(
            server_url(
                server,
                '/generated-style',
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
        TEST_STYLESHEET_BODY,
        body,
    )

@runner.test('discovery advertises the served asset paths')
def discovery_urls_match_served_asset_paths():
    paths = iter([
        '/generated-style',
        '/generated-script',
    ])

    server = create_test_server(
        trusted_discovery,
        asset_path_factory=lambda: next(paths),
    )

    with serving(server):
        discovery = json.loads(
            probe(
                server
            )
        )

        testlib.assert_same(
            [
                'https://bredland.example/generated-style',
                'https://bredland.example/generated-script',
            ],
            discovery['assets'],
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

        testlib.assert_same(
            TEST_STYLESHEET_BODY,
            stylesheet.read().decode('utf-8'),
        )

        testlib.assert_same(
            TEST_SCRIPT_BODY,
            script.read().decode('utf-8'),
        )

@runner.test('later discovery does not invalidate earlier asset paths')
def later_discovery_does_not_invalidate_earlier_asset_paths():
    paths = iter([
        '/style-one',
        '/script-one',
        '/style-two',
        '/script-two',
    ])

    server = create_test_server(
        trusted_discovery,
        asset_path_factory=lambda: next(paths),
    )

    with serving(server):
        first = json.loads(
            probe(
                server
            )
        )

        second = json.loads(
            probe(
                server
            )
        )

        testlib.assert_same(
            [
                'https://bredland.example/style-one',
                'https://bredland.example/script-one',
            ],
            first['assets'],
        )

        testlib.assert_same(
            [
                'https://bredland.example/style-two',
                'https://bredland.example/script-two',
            ],
            second['assets'],
        )

        first_stylesheet = urllib.request.urlopen(
            server_url(
                server,
                '/style-one',
            )
        )

        first_script = urllib.request.urlopen(
            server_url(
                server,
                '/script-one',
            )
        )

        testlib.assert_same(
            TEST_STYLESHEET_BODY,
            first_stylesheet.read().decode('utf-8'),
        )

        testlib.assert_same(
            TEST_SCRIPT_BODY,
            first_script.read().decode('utf-8'),
        )

runner.finish()