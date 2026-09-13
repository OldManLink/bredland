import io
import json
import os
import sys
import tempfile
import time
import threading
from builtins import ValueError
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'lib'))
import testlib
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (load_trusted_discovery, stub_routeros_action_dependencies, restore_routeros_action_dependencies, temporary_resolutions_file)

runner = TestSuiteRunner('trusted-discovery-resolution-hooks')

repo_root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))

trusted_discovery = load_trusted_discovery()
routeros_rest = sys.modules['routeros_rest']

@runner.test('missing resolutions file returns no action hook')
def missing_resolutions_file_returns_no_action_hook():
    trusted_discovery = load_trusted_discovery()

    result = trusted_discovery.load_resolution_hook(
        '/tmp/does-not-exist-resolutions.json',
        'install-routeros-update',
    )

    testlib.assert_same(
        None,
        result,
    )

@runner.test('missing resolution returns no action hook')
def missing_resolution_returns_no_action_hook():
    with temporary_resolutions_file(
            {
                'some-other-resolution': {
                    'socket': '/tmp/example.sock',
                    'host': '127.0.0.1',
                    'port': 8082,
                },
            },
    ) as path:
        result = trusted_discovery.load_resolution_hook(
            path,
            'install-routeros-update',
        )

    testlib.assert_same(
        None,
        result,
    )

@runner.test('matching resolution returns action hook')
def matching_resolution_returns_action_hook():
    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'socket': '/tmp/example.sock',
                    'host': '192.168.88.1',
                    'port': 443,
                },
            },
    ) as path:
        result = trusted_discovery.load_resolution_hook(
            path,
            'install-routeros-update',
        )

    testlib.assert_same(
        {
            'socket': '/tmp/example.sock',
            'host': '192.168.88.1',
            'port': 443,
        },
        result,
    )

@runner.test('rejects matching hook without socket')
def rejects_matching_hook_without_socket():
    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'host': '192.168.88.1',
                    'port': 443,
                },
            },
    ) as path:
        operation = lambda: trusted_discovery.load_resolution_hook(
            path,
            'install-routeros-update',
        )

        testlib.assert_throws(
            ValueError,
            'Invalid resolution hook',
            operation,
        )

@runner.test('rejects matching hook without host')
def rejects_matching_hook_without_host():
    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'socket': '/tmp/example.sock',
                    'port': 443,
                },
            },
    ) as path:
        operation = lambda: trusted_discovery.load_resolution_hook(
            path,
            'install-routeros-update',
        )

        testlib.assert_throws(
            ValueError,
            'Invalid resolution hook',
            operation,
        )

@runner.test('rejects matching hook without port')
def rejects_matching_hook_without_port():
    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'socket': '/tmp/example.sock',
                    'host': '192.168.88.1',
                },
            },
    ) as path:
        operation = lambda: trusted_discovery.load_resolution_hook(
            path,
            'install-routeros-update',
        )

        testlib.assert_throws(
            ValueError,
            'Invalid resolution hook',
            operation,
        )

@runner.test('rejects matching hook with invalid socket')
def rejects_matching_hook_with_invalid_socket():
    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'socket': 42,
                    'host': '192.168.88.1',
                    'port': 443,
                },
            },
    ) as path:
        operation = lambda: trusted_discovery.load_resolution_hook(
            path,
            'install-routeros-update',
        )

        testlib.assert_throws(
            ValueError,
            'Invalid resolution hook',
            operation,
        )

@runner.test('rejects matching hook with invalid host')
def rejects_matching_hook_with_invalid_host():
    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'socket': '/tmp/example.sock',
                    'host': 123,
                    'port': 443,
                },
            },
    ) as path:
        operation = lambda: trusted_discovery.load_resolution_hook(
            path,
            'install-routeros-update',
        )

        testlib.assert_throws(
            ValueError,
            'Invalid resolution hook',
            operation,
        )

@runner.test('rejects matching hook with invalid port')
def rejects_matching_hook_with_invalid_port():
    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'socket': '/tmp/example.sock',
                    'host': '192.168.88.1',
                    'port': '443',
                },
            },
    ) as path:
        operation = lambda: trusted_discovery.load_resolution_hook(
            path,
            'install-routeros-update',
        )

        testlib.assert_throws(
            ValueError,
            'Invalid resolution hook',
            operation,
        )

@runner.test('rejects matching hook with out-of-range port')
def rejects_matching_hook_with_out_of_range_port():
    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'socket': '/tmp/example.sock',
                    'host': '192.168.88.1',
                    'port': 0,
                },
            },
    ) as path:
        operation = lambda: trusted_discovery.load_resolution_hook(
            path,
            'install-routeros-update',
        )

        testlib.assert_throws(
            ValueError,
            'Invalid resolution hook',
            operation,
        )

@runner.test('rejects malformed resolutions json')
def rejects_malformed_resolutions_json():
    with testlib.temporary_text_file(
            '{not-json',
            suffix='.json',
    ) as path:
        operation = lambda: trusted_discovery.load_resolution_hook(
            path,
            'install-routeros-update',
        )

        testlib.assert_throws(
            ValueError,
            'Invalid resolutions JSON',
            operation,
        )

@runner.test('resolution hook sends RPI start command')
def resolution_hook_sends_rpi_start_command():
    events = []

    class FakeSocket:
        def connect(self, path):
            events.append(
                (
                    'connect',
                    path,
                )
            )

        def sendall(self, data):
            events.append(
                (
                    'send',
                    data,
                )
            )

        def recv(self, size):
            events.append(
                (
                    'recv',
                    size,
                )
            )

            return b'ok\n'

        def close(self):
            events.append(
                'close'
            )

    def socket_factory(family, socket_type):
        events.append(
            (
                'socket',
                family,
                socket_type,
            )
        )

        return FakeSocket()

    result = trusted_discovery.execute_resolution_hook(
        {
            'socket': '/tmp/rapid-poll-instrumentation.sock',
            'host': '192.168.88.1',
            'port': 443,
        },
        socket_factory,
    )

    testlib.assert_same(
        True,
        result,
    )

    testlib.assert_same(
        [
            (
                'socket',
                trusted_discovery.socket.AF_UNIX,
                trusted_discovery.socket.SOCK_STREAM,
            ),
            (
                'connect',
                '/tmp/rapid-poll-instrumentation.sock',
            ),
            (
                'send',
                b'start 192.168.88.1 443\n',
            ),
            (
                'recv',
                4096,
            ),
            'close',
        ],
        events,
    )

@runner.test('resolution hook rejects non-ok response')
def resolution_hook_rejects_non_ok_response():
    class FakeSocket:
        def connect(self, path):
            pass

        def sendall(self, data):
            pass

        def recv(self, size):
            return b'error\n'

        def close(self):
            pass

    def socket_factory(family, socket_type):
        return FakeSocket()

    result = trusted_discovery.execute_resolution_hook(
        {
            'socket': '/tmp/rapid-poll-instrumentation.sock',
            'host': '192.168.88.1',
            'port': 443,
        },
        socket_factory,
    )

    testlib.assert_same(
        False,
        result,
    )

@runner.test('resolution hook fails when RPI is unavailable')
def resolution_hook_fails_when_rpi_is_unavailable():
    class FakeSocket:
        def connect(self, path):
            raise OSError(
                'No such file or directory'
            )

        def close(self):
            pass

    def socket_factory(family, socket_type):
        return FakeSocket()

    result = trusted_discovery.execute_resolution_hook(
        {
            'socket': '/tmp/rapid-poll-instrumentation.sock',
            'host': '192.168.88.1',
            'port': 443,
        },
        socket_factory,
    )

    testlib.assert_same(
        False,
        result,
    )

@runner.test('configured action hook allows action when resolutions file is missing')
def configured_action_hook_allows_action_when_resolutions_file_is_missing():
    trusted_discovery = load_trusted_discovery()

    result = trusted_discovery.execute_configured_resolution_hook(
        '/tmp/does-not-exist-resolutions.json',
        'install-routeros-update',
        lambda hook: testlib.fail(
            'Hook executor must not be called'
        ),
    )

    testlib.assert_same(
        True,
        result,
    )

@runner.test('configured action hook executes matching hook')
def configured_action_hook_executes_matching_hook():
    trusted_discovery = load_trusted_discovery()

    path = os.path.join(
        repo_root,
        'build',
        'resolutions-test.json',
    )

    calls = []

    try:
        with open(path, 'w') as handle:
            handle.write(
                json.dumps(
                    {
                        'install-routeros-update': {
                            'socket': '/tmp/example.sock',
                            'host': '192.168.88.1',
                            'port': 443,
                        }
                    }
                )
            )

        def execute_hook(hook):
            calls.append(
                hook
            )
            return False

        result = trusted_discovery.execute_configured_resolution_hook(
            path,
            'install-routeros-update',
            execute_hook,
        )
    finally:
        if os.path.exists(path):
            os.remove(path)

    testlib.assert_same(
        [
            {
                'socket': '/tmp/example.sock',
                'host': '192.168.88.1',
                'port': 443,
            },
        ],
        calls,
    )

    testlib.assert_same(
        False,
        result,
    )

@runner.test('configured action hook logs when no hook is configured')
def configured_action_hook_logs_when_no_hook_is_configured():
    messages = []

    result = trusted_discovery.execute_configured_resolution_hook(
        '/tmp/does-not-exist-resolutions.json',
        'install-routeros-update',
        lambda hook: testlib.fail(
            'Hook executor must not be called'
        ),
        messages.append,
    )

    testlib.assert_true(result)

    testlib.assert_same(
        [
            (
                'No pre-action hook configured for '
                'install-routeros-update'
            ),
        ],
        messages,
    )

@runner.test('configured action hook logs before execution')
def configured_action_hook_logs_before_execution():
    events = []

    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'socket': '/tmp/example.sock',
                    'host': '192.168.88.1',
                    'port': 443,
                },
            },
    ) as path:
        def execute_hook(hook):
            events.append(
                'execute'
            )
            return True

        trusted_discovery.execute_configured_resolution_hook(
            path,
            'install-routeros-update',
            execute_hook,
            events.append,
        )

    testlib.assert_same(
        [
            'Pre-action hook configured for install-routeros-update',
            'execute',
            'Pre-action hook succeeded for install-routeros-update',
        ],
        events,
    )

@runner.test('configured action hook logs successful execution')
def configured_action_hook_logs_successful_execution():
    messages = []

    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'socket': '/tmp/example.sock',
                    'host': '192.168.88.1',
                    'port': 443,
                },
            },
    ) as path:
        result = trusted_discovery.execute_configured_resolution_hook(
            path,
            'install-routeros-update',
            lambda hook: True,
            messages.append,
        )

    testlib.assert_true(
        result,
    )

    testlib.assert_same(
        [
            'Pre-action hook configured for install-routeros-update',
            'Pre-action hook succeeded for install-routeros-update',
        ],
        messages,
    )

@runner.test('configured action hook logs failed execution')
def configured_action_hook_logs_failed_execution():
    messages = []

    with temporary_resolutions_file(
            {
                'install-routeros-update': {
                    'socket': '/tmp/example.sock',
                    'host': '192.168.88.1',
                    'port': 443,
                },
            },
    ) as path:
        result = trusted_discovery.execute_configured_resolution_hook(
            path,
            'install-routeros-update',
            lambda hook: False,
            messages.append,
        )

    testlib.assert_false(
        result,
    )

    testlib.assert_same(
        [
            'Pre-action hook configured for install-routeros-update',
            'Pre-action hook failed for install-routeros-update',
        ],
        messages,
    )

@runner.test('configured action hook logs invalid resolutions json')
def configured_action_hook_logs_invalid_resolutions_json():
    messages = []

    with testlib.temporary_text_file(
            '{not-json',
            suffix='.json',
    ) as path:
        operation = lambda: (
            trusted_discovery.execute_configured_resolution_hook(
                path,
                'install-routeros-update',
                lambda hook: testlib.fail(
                    'Hook executor must not be called'
                ),
                messages.append,
            )
        )

        testlib.assert_throws(
            ValueError,
            'Invalid resolutions JSON',
            operation,
        )

    testlib.assert_same(
        [
            (
                'Pre-action hook configuration failed for '
                'install-routeros-update'
            ),
        ],
        messages,
    )

runner.finish()