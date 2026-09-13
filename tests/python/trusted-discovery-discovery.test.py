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
from trusted_discovery_testlib import (create_test_server, load_trusted_discovery, server_url, serving)


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