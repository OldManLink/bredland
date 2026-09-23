import json
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
from trusted_discovery_testlib import (action_preflight_request, action_request, action_status_request, create_test_server,
                                       load_trusted_discovery, registered_capability_registry, recording_action_executor,
                                       serving)

runner = TestSuiteRunner('trusted-discovery-action-endpoint')
trusted_discovery = load_trusted_discovery()

@runner.test('action endpoint executes supported action resolution')
def action_endpoint_executes_supported_resolution():
    calls, execute = recording_action_executor()

    registry = registered_capability_registry(
        trusted_discovery,
    )

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
    )

    with serving(server):
        with testlib.suppress_stderr():
            response = urllib.request.urlopen(
                action_request(
                    server,
                )
            )

        testlib.assert_same(
            202,
            response.status,
        )

        testlib.assert_same(
            'https://noc.arcanel.se',
            response.headers.get(
                'Access-Control-Allow-Origin',
            ),
        )

        testlib.assert_same(
            [
                'noc-trusted-action-test',
            ],
            calls,
        )

@runner.test('action endpoint rejects unsupported action resolution')
def action_endpoint_rejects_unsupported_resolution():
    calls, execute = recording_action_executor()

    registry = registered_capability_registry(
        trusted_discovery,
    )

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                400,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                        resolution='launch-missiles',
                    )
                ),
            )

    testlib.assert_same(
        [],
        calls,
    )

@runner.test('rejects trusted action from wrong origin')
def action_endpoint_rejects_wrong_origin():
    calls, execute = recording_action_executor()

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                403,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                        origin='https://evil.example',
                    )
                ),
            )

    testlib.assert_same(
        [],
        calls,
    )

@runner.test('allows trusted action CORS preflight')
def action_endpoint_allows_preflight_from_noc_origin():
    server = create_test_server(
        trusted_discovery,
    )

    with serving(server):
        with testlib.suppress_stderr():
            response = urllib.request.urlopen(
                action_preflight_request(server)
            )

        testlib.assert_same(
            204,
            response.status,
        )

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

@runner.test('action endpoint rejects malformed JSON')
def action_endpoint_rejects_malformed_json():
    calls, execute = recording_action_executor()

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                400,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                        raw_body=b'{ definitely-not-json',
                    )
                ),
            )

    testlib.assert_same(
        [],
        calls,
    )

@runner.test('action endpoint rejects non-object JSON')
def action_endpoint_rejects_non_object_json():
    calls, execute = recording_action_executor()

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                400,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                        payload=[],
                    )
                ),
            )

    testlib.assert_same(
        [],
        calls,
    )

@runner.test('action endpoint rejects non-string fields')
def action_endpoint_rejects_non_string_fields():
    calls, execute = recording_action_executor()

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                400,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                        payload={
                            'resolution': 123,
                            'token': [],
                        },
                    )
                ),
            )

    testlib.assert_same(
        [],
        calls,
    )

@runner.test('action endpoint rejects missing resolution')
def action_endpoint_rejects_missing_resolution():
    calls, execute = recording_action_executor()

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                400,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                        payload={
                            'token': 'test-token',
                        },
                    )
                ),
            )

@runner.test('action endpoint accepts valid action content length')
def accepts_valid_action_content_length():
    testlib.assert_same(
        123,
        trusted_discovery.parse_action_content_length('123'),
    )

@runner.test('action endpoint rejects malformed action content length')
def rejects_malformed_action_content_length():
    testlib.assert_same(
        None,
        trusted_discovery.parse_action_content_length('banana'),
    )

@runner.test('action endpoint rejects non-positive action content length')
def rejects_non_positive_action_content_length():
    testlib.assert_same(
        None,
        trusted_discovery.parse_action_content_length('0'),
    )

@runner.test('action endpoint rejects negative action content length')
def rejects_negative_action_content_length():
    testlib.assert_same(
        None,
        trusted_discovery.parse_action_content_length('-1'),
    )

@runner.test('action endpoint rejects oversized action content length')
def rejects_oversized_action_content_length():
    testlib.assert_same(
        None,
        trusted_discovery.parse_action_content_length('4097'),
    )

@runner.test('action endpoint reports missing executor with CORS')
def action_endpoint_reports_missing_executor_with_cors():
    server = create_test_server(
        trusted_discovery,
        action_executor=None,
    )

    with serving(server):
        with testlib.suppress_stderr():
            error = testlib.assert_http_error(
                500,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                    )
                ),
            )

    testlib.assert_same(
        'https://noc.arcanel.se',
        error.headers.get(
            'Access-Control-Allow-Origin',
        ),
    )

@runner.test('action endpoint reports missing registry with CORS')
def action_endpoint_reports_missing_registry_with_cors():
    server = create_test_server(
        trusted_discovery,
        registry=None,
    )

    with serving(server):
        with testlib.suppress_stderr():
            error = testlib.assert_http_error(
                500,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                    )
                ),
            )

    testlib.assert_same(
        'https://noc.arcanel.se',
        error.headers.get(
            'Access-Control-Allow-Origin',
        ),
    )

@runner.test('accepted action creates pending result')
def accepted_action_creates_pending_result():
    executor_started = threading.Event()
    allow_executor_to_finish = threading.Event()

    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_results = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    def execute(_):
        executor_started.set()
        allow_executor_to_finish.wait()
        return True

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_result_registry=action_results,
    )

    with serving(server):
        with testlib.suppress_stderr():
            response = urllib.request.urlopen(
                action_request(
                    server,
                )
            )

            body = json.loads(
                response.read().decode('utf-8')
            )

            testlib.assert_true(
                executor_started.wait(1),
                'Executor must start',
            )

            testlib.assert_same(
                {
                    'status': 'pending',
                },
                action_results.get(
                    body['request_id'],
                ),
            )

            allow_executor_to_finish.set()

@runner.test('accepted action returns request id')
def accepted_action_returns_request_id():
    _, execute = recording_action_executor()

    registry = registered_capability_registry(
        trusted_discovery,
    )

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
    )

    with serving(server):
        with testlib.suppress_stderr():
            response = urllib.request.urlopen(
                action_request(
                    server,
                )
            )

        body = json.loads(
            response.read().decode('utf-8')
        )

        testlib.assert_same(
            202,
            response.status,
        )

        testlib.assert_true(
            isinstance(
                body.get('request_id'),
                str,
            ),
            'Accepted action must return a string request_id',
        )

        testlib.assert_true(
            body['request_id'] != '',
            'Accepted action request_id must not be empty',
            )

@runner.test('action status returns pending result')
def action_status_returns_pending_result():
    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_results = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    action_results.create(
        'request-123',
        200,
    )

    server = create_test_server(
        trusted_discovery,
        registry=registry,
        action_result_registry=action_results,
    )

    with serving(server):
        response = urllib.request.urlopen(
            action_status_request(
                server,
                'request-123',
            )
        )

        body = json.loads(
            response.read().decode('utf-8')
        )

    testlib.assert_same(
        200,
        response.status,
    )

    testlib.assert_same(
        {
            'status': 'pending',
        },
        body,
    )

@runner.test('action status returns succeeded result')
def action_status_returns_succeeded_result():
    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_results = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    action_results.create(
        'request-123',
        200,
    )

    action_results.succeed(
        'request-123',
    )

    server = create_test_server(
        trusted_discovery,
        registry=registry,
        action_result_registry=action_results,
    )

    with serving(server):
        response = urllib.request.urlopen(
            action_status_request(
                server,
                'request-123',
            )
        )

        body = json.loads(
            response.read().decode('utf-8')
        )

    testlib.assert_same(
        200,
        response.status,
    )

    testlib.assert_same(
        {
            'status': 'succeeded',
        },
        body,
    )

@runner.test('action status returns failed result')
def action_status_returns_failed_result():
    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_results = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    action_results.create(
        'request-123',
        200,
    )

    action_results.fail(
        'request-123',
    )

    server = create_test_server(
        trusted_discovery,
        registry=registry,
        action_result_registry=action_results,
    )

    with serving(server):
        response = urllib.request.urlopen(
            action_status_request(
                server,
                'request-123',
            )
        )

        body = json.loads(
            response.read().decode('utf-8')
        )

    testlib.assert_same(
        200,
        response.status,
    )

    testlib.assert_same(
        {
            'status': 'failed',
        },
        body,
    )

@runner.test('unknown action status returns 404')
def unknown_action_status_returns_404():
    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_results = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    server = create_test_server(
        trusted_discovery,
        registry=registry,
        action_result_registry=action_results,
    )

    with serving(server):
        testlib.assert_http_error(
            404,
            lambda: urllib.request.urlopen(
                action_status_request(
                    server,
                    'no-such-request',
                )
            ),
        )

@runner.test('expired action status returns 404')
def expired_action_status_returns_404():
    now = [100]

    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_results = trusted_discovery.ActionResultRegistry(
        lambda: now[0],
    )

    action_results.create(
        'request-123',
        200,
    )

    now[0] = 200

    server = create_test_server(
        trusted_discovery,
        registry=registry,
        action_result_registry=action_results,
    )

    with serving(server):
        testlib.assert_http_error(
            404,
            lambda: urllib.request.urlopen(
                action_status_request(
                    server,
                    'request-123',
                )
            ),
        )

runner.finish()
