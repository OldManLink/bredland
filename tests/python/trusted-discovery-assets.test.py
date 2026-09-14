import json
import os
import sys
import tempfile
import threading
from urllib import (parse, request)

sys.path.insert(
    0,
    os.path.join(
        os.path.dirname(__file__),
        'lib',
    ),
)

import testlib
from builtins import (iter, len, next)
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (create_test_server, load_trusted_discovery, probe, server_url, serving,
                                       TEST_STYLESHEET_BODY, TEST_SCRIPT_BODY)

TRUSTED_STYLESHEET_WITH_ACTIONS = '''
html::after {
    border: 1px solid #777;
}

/* Trusted action styles */

.trusted-action-button {
    cursor: pointer;
}
'''

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

        response = request.urlopen(
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

        response = request.urlopen(
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

        stylesheet = request.urlopen(
            server_url(
                server,
                '/generated-style',
            )
        )

        script = request.urlopen(
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

        first_stylesheet = request.urlopen(
            server_url(
                server,
                '/style-one',
            )
        )

        first_script = request.urlopen(
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

@runner.test('trusted stylesheet without actions contains only trusted-mode chrome')
def trusted_stylesheet_without_actions_contains_only_chrome():
    stylesheet = trusted_discovery.render_trusted_stylesheet(
        TRUSTED_STYLESHEET_WITH_ACTIONS,
        False,
    )

    testlib.assert_string_contains(
        'html::after',
        stylesheet,
    )

    testlib.assert_string_not_contains(
        '.trusted-action-button',
        stylesheet,
    )

@runner.test('trusted stylesheet with actions preserves action styles')
def trusted_stylesheet_with_actions_preserves_action_styles():
    stylesheet = trusted_discovery.render_trusted_stylesheet(
        TRUSTED_STYLESHEET_WITH_ACTIONS,
        True,
    )

    testlib.assert_same(
        TRUSTED_STYLESHEET_WITH_ACTIONS,
        stylesheet,
    )

@runner.test('serves border-only stylesheet without trusted actions')
def serves_border_only_stylesheet_without_trusted_actions():
    stylesheet_body = TRUSTED_STYLESHEET_WITH_ACTIONS

    server = create_test_server(
        trusted_discovery,
        stylesheet_body=stylesheet_body,
        current_resolutions=lambda: [],
    )

    with serving(
            server
    ):
        discovery = json.loads(
            probe(
                server
            )
        )

        asset_path = parse.urlparse(
            discovery['assets'][0]
        ).path

        stylesheet = request.urlopen(
            server_url(server, asset_path)
        ).read().decode('utf-8')

    testlib.assert_string_contains(
        'html::after',
        stylesheet,
    )

    testlib.assert_string_not_contains(
        '.trusted-action-button',
        stylesheet,
    )

@runner.test('generated stylesheet reuses resolutions from discovery')
def generated_stylesheet_reuses_resolutions_from_discovery():
    calls = []

    def current_resolutions():
        calls.append(
            True
        )

        return [
            'install-routeros-update',
        ]

    server = create_test_server(
        trusted_discovery,
        stylesheet_body=TRUSTED_STYLESHEET_WITH_ACTIONS,
        current_resolutions=current_resolutions,
    )

    with serving(
            server
    ):
        discovery = json.loads(
            probe(
                server
            )
        )

        asset_path = parse.urlparse(
            discovery['assets'][0]
        ).path

        request.urlopen(
            server_url(
                server,
                asset_path,
            )
        ).read()

    testlib.assert_same(
        1,
        len(calls),
    )

runner.finish()