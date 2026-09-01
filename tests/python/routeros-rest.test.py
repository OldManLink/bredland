import json
import os
import subprocess
import sys
import tempfile
import time

sys.path.insert(
    0,
    os.path.join(
        os.path.dirname(__file__),
        'lib',
    ),
)

sys.path.insert(
    0,
    os.path.join(
        os.path.dirname(__file__),
        '..',
        '..',
        'templates',
        'bredland',
    ),
)

from test_suite_runner import TestSuiteRunner
import testlib
import routeros_rest

runner = TestSuiteRunner('routeros-rest')

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

    result = routeros_rest.execute_routeros_script(
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

    result = routeros_rest.post_json(
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
            routeros_rest.load_routeros_rest_credentials(
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
        routeros_rest.routeros_rest_authorization(
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

    original_ssl = routeros_rest.ssl
    routeros_rest.ssl = FakeSsl

    try:
        context = (
            routeros_rest.create_routeros_rest_tls_context(
                '/etc/bredland/mikrotik-rest/ca.pem',
            )
        )
    finally:
        routeros_rest.ssl = original_ssl

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

    poster = routeros_rest.create_routeros_rest_poster(
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

@runner.test('creates RouterOS REST getter')
def creates_routeros_rest_getter():
    calls = []

    def get_json_function(
            url,
            headers,
            context,
            open_request,
    ):
        calls.append(
            {
                'url': url,
                'headers': headers,
                'context': context,
                'open_request': open_request,
            }
        )

        return {
            'status': 'ok',
        }

    open_request = object()

    getter = routeros_rest.create_routeros_rest_getter(
        {
            'username': 'noc-rest-bredland',
            'password': 'test-password',
        },
        'tls-context',
        open_request,
        get_json_function,
    )

    result = getter(
        'https://mikrotik.example/rest/system/package/update'
    )

    testlib.assert_same(
        {
            'status': 'ok',
        },
        result,
    )

    testlib.assert_same(
        [
            {
                'url': 'https://mikrotik.example/rest/system/package/update',
                'headers': {
                    'Authorization':
                        'Basic bm9jLXJlc3QtYnJlZGxhbmQ6dGVzdC1wYXNzd29yZA==',
                },
                'context': 'tls-context',
                'open_request': open_request,
            },
        ],
        calls,
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

    executor = routeros_rest.create_routeros_action_executor(
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

@runner.test('reports RouterOS update available')
def reports_routeros_update_available():
    def get(url):
        testlib.assert_same(
            'https://mikrotik.example/rest/system/package/update',
            url,
        )

        return {
            'installed-version': '7.23.1',
            'latest-version': '7.24.1',
            'status': 'New version is available',
        }

    testlib.assert_true(
        routeros_rest.routeros_update_available(
            'https://mikrotik.example',
            get,
        )
    )

@runner.test('reports no RouterOS update when versions match')
def reports_no_routeros_update_when_versions_match():
    def get(url):
        return {
            'installed-version': '7.24.1',
            'latest-version': '7.24.1',
            'status': 'System is already up to date',
        }

    testlib.assert_false(
        routeros_rest.routeros_update_available(
            'https://mikrotik.example',
            get,
        )
    )

@runner.test('reports no RouterOS update for unexpected status')
def reports_no_routeros_update_for_unexpected_status():
    def get(url):
        return {
            'installed-version': '7.23.1',
            'latest-version': '7.24.1',
            'status': 'Something else',
        }

    testlib.assert_false(
        routeros_rest.routeros_update_available(
            'https://mikrotik.example',
            get,
        )
    )


@runner.test('reports no RouterOS update when installed version is missing')
def reports_no_routeros_update_without_installed_version():
    def get(url):
        return {
            'latest-version': '7.24.1',
            'status': 'New version is available',
        }

    testlib.assert_false(
        routeros_rest.routeros_update_available(
            'https://mikrotik.example',
            get,
        )
    )


@runner.test('reports no RouterOS update when latest version is missing')
def reports_no_routeros_update_without_latest_version():
    def get(url):
        return {
            'installed-version': '7.23.1',
            'status': 'New version is available',
        }

    testlib.assert_false(
        routeros_rest.routeros_update_available(
            'https://mikrotik.example',
            get,
        )
    )

@runner.test('gets JSON from RouterOS REST')
def gets_json_from_routeros_rest():
    calls = []

    class Response:
        def read(self):
            return b'{"installed-version":"7.23.1"}'

    def open_request(request, context=None):
        calls.append(
            {
                'url': request.full_url,
                'method': request.get_method(),
                'authorization': request.get_header(
                    'Authorization'
                ),
                'context': context,
            }
        )

        return Response()

    result = routeros_rest.get_json(
        'https://mikrotik.example/rest/system/package/update',
        {
            'Authorization': 'Basic test',
        },
        'tls-context',
        open_request,
    )

    testlib.assert_same(
        {
            'installed-version': '7.23.1',
        },
        result,
    )

    testlib.assert_same(
        [
            {
                'url': 'https://mikrotik.example/rest/system/package/update',
                'method': 'GET',
                'authorization': 'Basic test',
                'context': 'tls-context',
            },
        ],
        calls,
    )

runner.finish()