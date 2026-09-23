import json
import os
import sys
import threading
import time
import urllib
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'lib'))
import testlib
from builtins import (RuntimeError, ValueError)
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (action_request, create_test_server, load_trusted_discovery, recording_action_executor,
                                       recording_action_hook, registered_capability_registry, serving)


runner = TestSuiteRunner('trusted-discovery-action-execution')
trusted_discovery = load_trusted_discovery()

@runner.test('action endpoint consumes capability before execution')
def action_endpoint_consumes_capability_before_execution():
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
        with testlib.capture_stderr() as stderr:
            response = urllib.request.urlopen(
                action_request(
                    server,
                )
            )

            testlib.wait_for_stderr(
                stderr,
                'Trusted action executor succeeded'
            )

        testlib.assert_same(
            202,
            response.status,
        )

        testlib.assert_same(
            [
                'noc-trusted-action-test',
            ],
            calls,
        )

        testlib.assert_same(
            None,
            registry.consume(
                'install-routeros-update',
                'test-token',
            ),
        )

@runner.test('action endpoint rejects replayed capability')
def action_endpoint_rejects_replayed_capability():
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

            testlib.assert_http_error(
                400,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                    )
                ),
            )

            testlib.assert_same(
                [
                    'noc-trusted-action-test'
                ],
                calls,
            )

@runner.test('action endpoint rejects second action during cooldown')
def action_endpoint_rejects_second_action_during_cooldown():
    calls, execute = recording_action_executor()

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'first-token',
        'noc-trusted-action-test',
        202,
    )

    registry.register(
        'install-routeros-update',
        'second-token',
        'noc-trusted-action-test',
        202,
    )

    guard = trusted_discovery.ActionGuard(
        lambda: 100,
        30,
    )

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_guard=guard,
    )

    with serving(server):
        with testlib.suppress_stderr():
            response = urllib.request.urlopen(
                action_request(
                    server,
                    token='first-token'
                )
            )

            testlib.assert_same(
                202,
                response.status,
            )

            testlib.assert_http_error(
                423,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                        token='second-token'
                    )
                ),
            )

            testlib.assert_same(
                [
                    'noc-trusted-action-test'
                ],
                calls,
            )

@runner.test('action endpoint rejects action when current state is invalid')
def action_endpoint_rejects_invalid_current_state():
    calls, execute = recording_action_executor()

    registry = registered_capability_registry(
        trusted_discovery,
    )

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_validator=lambda resolution: False
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                409,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                    )
                ),
            )

        testlib.assert_same(
            [],
            calls,
        )

@runner.test('action endpoint executes action when current state is valid')
def action_endpoint_executes_valid_current_state():
    calls, execute = recording_action_executor()

    registry = registered_capability_registry(
        trusted_discovery,
    )

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_validator=lambda resolution: True,
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
            [
                'noc-trusted-action-test',
            ],
            calls,
        )

@runner.test('action endpoint logs successful execution')
def action_endpoint_executes_valid_current_state():
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
        with testlib.capture_stderr() as stderr:
            response = urllib.request.urlopen(
                action_request(
                    server,
                )
            )

            testlib.assert_same(
                202,
                response.status,
            )

            expected = "Trusted action executor succeeded: resolution='install-routeros-update', script='noc-trusted-action-test'\n"
            testlib.wait_for_stderr(stderr, expected)

        testlib.assert_same(
            expected,
            stderr.getvalue(),
        )

@runner.test('action endpoint logs failed execution')
def action_endpoint_logs_failed_execution():
    registry = registered_capability_registry(
        trusted_discovery,
    )

    exception_message = 'RouterOS REST returned HTTP 500: {"detail":"not enough permissions (9)","error":500,"message":"Internal Server Error"}'
    def execute(_):
        raise RuntimeError(exception_message)

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_validator=lambda resolution: True,
    )

    with serving(server):
        with testlib.capture_stderr() as stderr:
            response = urllib.request.urlopen(
                action_request(
                    server,
                )
            )

            testlib.assert_same(
                202,
                response.status,
            )

            testlib.wait_for_stderr(stderr, exception_message)

    testlib.assert_same(
        "Trusted action executor failed: resolution='install-routeros-update', script='noc-trusted-action-test', exception=RuntimeError: " + exception_message + "\n",
        stderr.getvalue(),
    )

@runner.test('action endpoint handles validator exception')
def action_endpoint_handles_validator_exception():
    calls, execute = recording_action_executor()

    registry = registered_capability_registry(
        trusted_discovery,
    )

    def validate(_):
        raise urllib.error.URLError(
            'RouterOS unavailable'
        )

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_validator=validate,
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                503,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                    )
                ),
            )

    testlib.assert_same(
        [],
        calls,
    )

    testlib.assert_same(
        None,
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

@runner.test('action endpoint accepts action despite executor failure')
def action_endpoint_accepts_action_despite_executor_failure():
    registry = registered_capability_registry(
        trusted_discovery,
    )

    def execute(_):
        return False

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

@runner.test('action endpoint accepts action despite executor exception')
def action_endpoint_accepts_action_despite_executor_exception():
    registry = registered_capability_registry(
        trusted_discovery,
    )

    def execute(_):
        raise RuntimeError(
            'Boom!'
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

@runner.test('action endpoint releases claim after executor failure')
def action_endpoint_releases_claim_after_executor_failure():
    import threading

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'first-token',
        'noc-trusted-action-test',
        202,
    )

    registry.register(
        'install-routeros-update',
        'second-token',
        'noc-trusted-action-test',
        202,
    )

    outcomes = [
        False,
        True,
    ]

    def execute(_):
        return outcomes.pop(0)

    action_guard = trusted_discovery.ActionGuard(
        lambda: 100,
        30,
    )

    original_release = action_guard.release
    released = threading.Event()

    def release(resolution):
        original_release(
            resolution
        )
        released.set()

    action_guard.release = release

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_guard=action_guard,
    )

    with serving(server):
        with testlib.suppress_stderr():
            response = urllib.request.urlopen(
                action_request(
                    server,
                    token='first-token',
                )
            )

            testlib.assert_same(
                202,
                response.status,
            )

            testlib.assert_true(
                released.wait(1),
                'Executor failure must release action claim',
            )

            response = urllib.request.urlopen(
                action_request(
                    server,
                    token='second-token',
                )
            )

            testlib.assert_same(
                202,
                response.status,
            )

@runner.test('action endpoint releases claim after executor exception')
def action_endpoint_releases_claim_after_executor_exception():
    import threading

    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'first-token',
        'noc-trusted-action-test',
        202,
    )

    registry.register(
        'install-routeros-update',
        'second-token',
        'noc-trusted-action-test',
        202,
    )

    outcomes = [
        RuntimeError('boom'),
        True,
    ]

    def execute(_):
        outcome = outcomes.pop(0)

        if isinstance(
                outcome,
                Exception,
        ):
            raise outcome

        return outcome

    action_guard = trusted_discovery.ActionGuard(
        lambda: 100,
        30,
    )

    original_release = action_guard.release
    released = threading.Event()

    def release(resolution):
        original_release(
            resolution
        )
        released.set()

    action_guard.release = release

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_guard=action_guard,
    )

    with serving(server):
        with testlib.suppress_stderr():
            response = urllib.request.urlopen(
                action_request(
                    server,
                    token='first-token',
                )
            )

            testlib.assert_same(
                202,
                response.status,
            )

            testlib.assert_true(
                released.wait(1),
                'Executor exception must release action claim',
            )

            response = urllib.request.urlopen(
                action_request(
                    server,
                    token='second-token',
                )
            )

            testlib.assert_same(
                202,
                response.status,
            )

@runner.test('successful executor marks action result succeeded')
def successful_executor_marks_action_result_succeeded():
    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_results = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    _, execute = recording_action_executor(
        True
    )

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

            deadline = time.time() + 1

            while (
                    action_results.get(
                        body['request_id']
                    ) == {
                        'status': 'pending',
                    }
                    and time.time() < deadline
            ):
                time.sleep(0.01)

    testlib.assert_same(
        {
            'status': 'succeeded',
        },
        action_results.get(
            body['request_id'],
        ),
    )

@runner.test('failed executor marks action result failed')
def failed_executor_marks_action_result_failed():
    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_results = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    _, execute = recording_action_executor(
        False
    )

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

            deadline = time.time() + 1

            while (
                    action_results.get(
                        body['request_id']
                    ) == {
                        'status': 'pending',
                    }
                    and time.time() < deadline
            ):
                time.sleep(0.01)

    testlib.assert_same(
        {
            'status': 'failed',
        },
        action_results.get(
            body['request_id'],
        ),
    )

@runner.test('executor exception marks action result failed')
def executor_exception_marks_action_result_failed():
    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_results = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    def execute(_):
        raise RuntimeError(
            'boom'
        )

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

            deadline = time.time() + 1

            while (
                    action_results.get(
                        body['request_id']
                    ) == {
                        'status': 'pending',
                    }
                    and time.time() < deadline
            ):
                time.sleep(0.01)

    testlib.assert_same(
        {
            'status': 'failed',
        },
        action_results.get(
            body['request_id'],
        ),
    )

@runner.test('action endpoint aborts when resolution hook fails')
def action_endpoint_aborts_when_resolution_hook_fails():
    calls, execute = recording_action_executor()
    hook_calls, action_hook = recording_action_hook(
        result=False,
    )

    registry = registered_capability_registry(
        trusted_discovery,
    )

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_hook=action_hook,
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                500,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                    )
                ),
            )

    testlib.assert_same(
        [],
        calls,
    )

    testlib.assert_same(
        ['install-routeros-update'],
        hook_calls,
    )

@runner.test('action endpoint executes after resolution hook succeeds')
def action_endpoint_executes_after_resolution_hook_succeeds():
    events = []

    def action_hook(resolution):
        events.append(
            (
                'hook',
                resolution,
            )
        )
        return True

    def execute(script_name):
        events.append(
            (
                'executor',
                script_name,
            )
        )
        return True

    registry = registered_capability_registry(
        trusted_discovery,
    )

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_hook=action_hook,
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
        [
            (
                'hook',
                'install-routeros-update',
            ),
            (
                'executor',
                'noc-trusted-action-test',
            ),
        ],
        events,
    )

@runner.test('action endpoint handles resolution hook exception')
def action_endpoint_handles_resolution_hook_exception():
    calls, execute = recording_action_executor()

    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_guard = trusted_discovery.ActionGuard(
        lambda: 100,
        30,
    )

    def action_hook(_):
        raise ValueError(
            'Invalid resolutions JSON'
        )

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_hook=action_hook,
        action_guard=action_guard,
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                500,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                    )
                ),
            )

    testlib.assert_same(
        [],
        calls,
    )

    testlib.assert_true(
        action_guard.claim(
            'install-routeros-update'
        ),
        'Action guard should be released after hook failure',
    )

@runner.test('action endpoint aborts when configured RPI hook is unavailable')
def action_endpoint_aborts_when_configured_rpi_hook_is_unavailable():
    calls, execute = recording_action_executor()

    registry = registered_capability_registry(
        trusted_discovery,
    )

    action_guard = trusted_discovery.ActionGuard(
        lambda: 100,
        30,
    )

    def action_hook(_):
        return False

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
        action_hook=action_hook,
        action_guard=action_guard,
    )

    with serving(server):
        with testlib.suppress_stderr():
            testlib.assert_http_error(
                500,
                lambda: urllib.request.urlopen(
                    action_request(
                        server,
                    )
                ),
            )

    testlib.assert_same(
        [],
        calls,
    )

    testlib.assert_true(
        action_guard.claim(
            'install-routeros-update'
        ),
        'Action guard should be released after hook failure',
    )

@runner.test('action response arrives before executor completion')
def action_response_arrives_before_executor_completion():

    executor_started = threading.Event()
    allow_executor_to_finish = threading.Event()
    response_received = threading.Event()

    registry = registered_capability_registry(
        trusted_discovery,
    )

    def execute(_):
        executor_started.set()
        allow_executor_to_finish.wait()
        return True

    server = create_test_server(
        trusted_discovery,
        action_executor=execute,
        registry=registry,
    )

    def request_action():
        with testlib.suppress_stderr():
            urllib.request.urlopen(
                action_request(
                    server,
                )
            )

        response_received.set()

    with serving(server):
        request_thread = threading.Thread(
            target=request_action,
        )
        request_thread.start()

        testlib.assert_true(
            executor_started.wait(1),
            'Executor must start',
        )

        response_arrived = response_received.wait(1)

        allow_executor_to_finish.set()
        request_thread.join()

        testlib.assert_true(
            response_arrived,
            'Response must arrive before executor completion',
        )


runner.finish()
