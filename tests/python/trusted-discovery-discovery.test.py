import ast
import os
import sys
import tempfile
import threading
import urllib.error
import urllib.request
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'lib'))
import testlib
from builtins import (iter, getattr, next)
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (create_test_server, load_trusted_discovery, probe, server_url, serving)


runner = TestSuiteRunner('trusted-discovery-discovery')
trusted_discovery = load_trusted_discovery()

@runner.test('renders the trusted discovery response')
def discovery_response_is_rendered():
    testlib.assert_same(
        (
            '{"assets":['
            '"https://bredland.example/opaque-style",'
            '"https://bredland.example/opaque-script"'
            ']}'
        ),
        trusted_discovery.render_discovery_response(
            'https://bredland.example/opaque-style',
            'https://bredland.example/opaque-script',
        ),
    )

@runner.test('renders stylesheet-only trusted discovery response')
def stylesheet_only_discovery_response_is_rendered():
    testlib.assert_same(
        (
            '{"assets":['
            '"https://bredland.example/opaque-style"'
            ']}'
        ),
        trusted_discovery.render_discovery_response(
            'https://bredland.example/opaque-style',
            None,
        ),
    )

@runner.test('serves the trusted discovery response over HTTP')
def discovery_endpoint_returns_json():
    paths = iter([
        '/style-test',
        '/script-test',
    ])

    server = create_test_server(
        trusted_discovery,
        asset_path_factory=lambda: next(paths),
    )

    with serving(server):
        response = urllib.request.urlopen(
            server_url(
                server,
                '/probe',
            )
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
        (
            '{"assets":['
            '"https://bredland.example/style-test",'
            '"https://bredland.example/script-test"'
            ']}'
        ),
        body,
    )

@runner.test('each discovery generates fresh asset paths')
def each_discovery_generates_fresh_asset_paths():
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

    with serving(
            server,
    ):
        first = probe(
            server
        )

        second = probe(
            server
        )

    testlib.assert_same(
        (
            '{"assets":['
            '"https://bredland.example/style-one",'
            '"https://bredland.example/script-one"'
            ']}'
        ),
        first,
    )

    testlib.assert_same(
        (
            '{"assets":['
            '"https://bredland.example/style-two",'
            '"https://bredland.example/script-two"'
            ']}'
        ),
        second,
    )

@runner.test('serves discovery only on the probe path')
def discovery_endpoint_only_serves_probe_path():
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

        testlib.assert_same(200, response.status)

        with testlib.suppress_stderr():
            testlib.assert_http_error(
                404,
                lambda: urllib.request.urlopen(
                    server_url(
                        server,
                        '/anything',
                    )
                ),
            )

@runner.test('allows the NOC origin to read discovery')
def discovery_endpoint_allows_noc_origin():
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

    testlib.assert_same(
        'https://noc.arcanel.se',
        response.headers.get(
            'Access-Control-Allow-Origin',
        ),
    )

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

@runner.test('discovery omits script without applicable trusted action')
def discovery_omits_script_without_applicable_trusted_action():
    paths = iter([
        '/style-only',
    ])

    server = create_test_server(
        trusted_discovery,
        asset_path_factory=lambda: next(paths),
        # new seam still to introduce:
        has_trusted_actions=lambda: False,
    )

    with serving(
            server,
    ):
        body = probe(
            server
        )

    testlib.assert_same(
        (
            '{"assets":['
            '"https://bredland.example/style-only"'
            ']}'
        ),
        body,
    )

runner.finish()