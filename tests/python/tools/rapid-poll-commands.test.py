import os
import sys
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'lib')))
import testlib
from test_suite_runner import TestSuiteRunner
from rapid_poll_testlib import load_rapid_poll


runner = TestSuiteRunner(
    'rapid-poll-commands'
)

rapid_poll = load_rapid_poll()

@runner.test('handles start command')
def handles_start_command():
    calls = []

    class FakeController:
        def start(self, host, port):
            calls.append(
                (
                    host,
                    port,
                )
            )

    result = rapid_poll.handle_command(
        FakeController(),
        'start 127.0.0.1 8082',
    )

    testlib.assert_same('ok', result)
    testlib.assert_same([('127.0.0.1', 8082)], calls)

@runner.test('status command reports controller state')
def status_command_reports_controller_state():
    class FakeController:
        def status(self):
            return 'polling'

    result = rapid_poll.handle_command(
        FakeController(),
        'status',
    )

    testlib.assert_same('polling', result)

@runner.test('stop command is harmless while inert')
def stop_command_is_harmless_while_inert():
    calls = []

    class FakeController:
        def stop(self):
            calls.append('stop')

        def status(self):
            return 'inert'

    controller = FakeController()

    result = rapid_poll.handle_command(
        controller,
        'stop',
    )

    testlib.assert_same('ok', result)
    testlib.assert_same(['stop'], calls)
    testlib.assert_same('inert', controller.status())

@runner.test('exit command stops controller')
def exit_command_stops_controller():
    calls = []

    class FakeController:
        def stop(self):
            calls.append(
                'stop'
            )

    result = rapid_poll.handle_command(
        FakeController(),
        'exit',
    )

    testlib.assert_same('ok', result)
    testlib.assert_same(['stop'], calls)

@runner.test('rejects unknown command')
def rejects_unknown_command():
    class FakeController:
        pass

    result = rapid_poll.handle_command(
        FakeController(),
        'banana',
    )

    testlib.assert_same(
        'error: unknown command',
        result,
    )

@runner.test('rejects malformed start command')
def rejects_malformed_start_command():
    class FakeController:
        def start(self, host, port):
            testlib.fail(
                'Controller must not be started'
            )

    result = rapid_poll.handle_command(
        FakeController(),
        'start 127.0.0.1',
    )

    testlib.assert_same(
        'error: malformed start command',
        result,
    )

@runner.test('rejects non-numeric start port')
def rejects_non_numeric_start_port():
    class FakeController:
        def start(self, host, port):
            testlib.fail(
                'Controller must not be started'
            )

    result = rapid_poll.handle_command(
        FakeController(),
        'start 127.0.0.1 banana',
    )

    testlib.assert_same(
        'error: malformed start command',
        result,
    )

@runner.test('command loop handles commands while inert')
def command_loop_handles_commands_while_inert():
    commands = iter([
        'status',
        'banana',
    ])

    responses = []

    class FakeController:
        def status(self):
            return 'inert'

    rapid_poll.command_loop(
        FakeController(),
        lambda: next(commands),
        responses.append,
        2,
        lambda host, port, timeout_ms: True,
        lambda seconds: None,
    )

    testlib.assert_same(
        [
            'inert',
            'error: unknown command',
        ],
        responses,
    )

@runner.test('start command runs polling before next command')
def start_command_runs_polling_before_next_command():
    commands = iter([
        'start 127.0.0.1 8082',
        'status',
    ])

    responses = []
    events = []

    class FakeController:
        def start(self, host, port):
            events.append(
                (
                    'start',
                    host,
                    port,
                )
            )

        def poll(self, probe, sleep):
            events.append(
                'poll'
            )

        def status(self):
            return 'inert'

    rapid_poll.command_loop(
        FakeController(),
        lambda: next(commands),
        responses.append,
        2,
        lambda host, port, timeout_ms: True,
        lambda seconds: None,
    )

    testlib.assert_same(
        [
            (
                'start',
                '127.0.0.1',
                8082,
            ),
            'poll',
        ],
        events,
    )

    testlib.assert_same(
        [
            'ok',
            'inert',
        ],
        responses,
    )




runner.finish()