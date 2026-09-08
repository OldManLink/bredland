import importlib.util
import os
import sys

sys.path.insert(
    0,
    os.path.abspath(
        os.path.join(
            os.path.dirname(__file__),
            '..',
            'lib',
        )
    ),
)

from test_suite_runner import TestSuiteRunner
from testlib import (assert_false, assert_same, assert_true, fail)

runner = TestSuiteRunner('routeros-rapid-poll')

repo_root = os.path.abspath(
    os.path.join(
        os.path.dirname(__file__),
        '..',
        '..',
        '..',
    )
)

tool_file = os.path.join(
    repo_root,
    'scripts',
    'tools',
    'rapid-poll-instrumentation.py',
)


def load_tool():
    spec = importlib.util.spec_from_file_location(
        'routeros_rapid_poll',
        tool_file,
    )

    module = importlib.util.module_from_spec(
        spec
    )

    spec.loader.exec_module(
        module
    )

    return module


@runner.test('uses sensible rapid polling defaults')
def uses_sensible_rapid_polling_defaults():
    rapid_poll = load_tool()

    config = rapid_poll.default_config()

    assert config == {
        'poll_interval_ms': 10,
        'connect_timeout_ms': 100,
        'control_socket': '/tmp/rapid-poll-instrumentation.sock',
        'log_file': '/tmp/rapid-poll-instrumentation.log',
    }

@runner.test('loads config overrides')
def loads_config_overrides():
    rapid_poll = load_tool()

    config_file = os.path.join(
        repo_root,
        'build',
        'routeros-rpi-test.conf',
    )

    os.makedirs(
        os.path.dirname(config_file),
        exist_ok=True,
    )

    with open(config_file, 'w') as handle:
        handle.write(
            'poll_interval_ms=25\n'
            'connect_timeout_ms=250\n'
            'control_socket=/tmp/test-rpi.sock\n'
            'log_file=/tmp/test-rpi.log\n'
        )

    try:
        config = rapid_poll.load_config(
            config_file
        )
    finally:
        os.remove(
            config_file
        )

    assert config == {
        'poll_interval_ms': 25,
        'connect_timeout_ms': 250,
        'control_socket': '/tmp/test-rpi.sock',
        'log_file': '/tmp/test-rpi.log',
    }

@runner.test('uses defaults when config file is missing')
def uses_defaults_when_config_file_is_missing():
    rapid_poll = load_tool()

    config = rapid_poll.load_config(
        '/tmp/no-such-routeros-rpi.conf'
    )

    assert config == rapid_poll.default_config()

@runner.test('rejects invalid polling interval')
def rejects_invalid_polling_interval():
    rapid_poll = load_tool()

    config_file = os.path.join(
        repo_root,
        'build',
        'routeros-rpi-invalid.conf',
    )

    os.makedirs(
        os.path.dirname(config_file),
        exist_ok=True,
    )

    with open(config_file, 'w') as handle:
        handle.write(
            'poll_interval_ms=0\n'
        )

    try:
        try:
            rapid_poll.load_config(
                config_file
            )

            assert False
        except ValueError:
            pass
    finally:
        os.remove(
            config_file
        )

@runner.test('rejects invalid connect timeout')
def rejects_invalid_connect_timeout():
    rapid_poll = load_tool()

    config_file = os.path.join(
        repo_root,
        'build',
        'routeros-rpi-invalid-timeout.conf',
    )

    os.makedirs(
        os.path.dirname(config_file),
        exist_ok=True,
    )

    with open(config_file, 'w') as handle:
        handle.write(
            'connect_timeout_ms=0\n'
        )

    try:
        try:
            rapid_poll.load_config(
                config_file
            )

            assert False
        except ValueError:
            pass
    finally:
        os.remove(
            config_file
        )

@runner.test('starts in inert state')
def starts_in_inert_state():
    rapid_poll = load_tool()

    controller = rapid_poll.Controller(
        rapid_poll.default_config()
    )

    assert controller.status() == 'inert'

@runner.test('start moves controller to polling state')
def start_moves_controller_to_polling_state():
    rapid_poll = load_tool()

    controller = rapid_poll.Controller(
        rapid_poll.default_config()
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    assert controller.status() == 'polling'
    assert controller.target == (
        '127.0.0.1',
        8082,
    )

@runner.test('stop returns controller to inert state')
def stop_returns_controller_to_inert_state():
    rapid_poll = load_tool()

    controller = rapid_poll.Controller(
        rapid_poll.default_config()
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.stop()

    assert controller.status() == 'inert'
    assert controller.target is None

@runner.test('stop writes operator-stopped polling report')
def stop_writes_operator_stopped_polling_report():
    rapid_poll = load_tool()

    written_reports = []

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: 1000000000,
        lambda: '2026-09-04T09:30:00.000000Z',
    )

    controller.write_report = lambda: written_reports.append(
        controller.report()
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.stop()

    assert_same(
        1,
        len(written_reports),
    )

    assert_same(
        'operator',
        written_reports[0]['stop_reason'],
    )

    assert_same(
        'inert',
        controller.status(),
    )

@runner.test('successful probe keeps controller polling')
def successful_probe_keeps_controller_polling():
    rapid_poll = load_tool()

    probes = []

    def probe(host, port, timeout_ms):
        probes.append(
            (
                host,
                port,
                timeout_ms,
            )
        )

        return True

    controller = rapid_poll.Controller(
        rapid_poll.default_config()
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll_once(
        probe
    )

    assert controller.status() == 'polling'

    assert probes == [
        (
            '127.0.0.1',
            8082,
            100,
        )
    ]

@runner.test('failed probe returns controller to inert state')
def failed_probe_returns_controller_to_inert_state():
    rapid_poll = load_tool()

    def probe(host, port, timeout_ms):
        return False

    controller = rapid_poll.Controller(
        rapid_poll.default_config()
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll_once(
        probe
    )

    assert controller.status() == 'inert'
    assert controller.target is None

@runner.test('start records polling start time')
def start_records_polling_start_time():
    rapid_poll = load_tool()

    times = iter([
        123456789,
    ])

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: next(times),
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    assert controller.started_at_ns == 123456789

@runner.test('successful probe records last success time')
def successful_probe_records_last_success_time():
    rapid_poll = load_tool()

    times = iter([
        1000000000,
        1010000000,
    ])

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: next(times),
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll_once(
        lambda host, port, timeout_ms: True
    )

    assert controller.last_success_at_ns == 1010000000

@runner.test('failed probe records first failure time')
def failed_probe_records_first_failure_time():
    rapid_poll = load_tool()

    times = iter([
        1000000000,
        1010000000,
    ])

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: next(times),
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll_once(
        lambda host, port, timeout_ms: False
    )

    assert controller.first_failure_at_ns == 1010000000
    assert controller.status() == 'inert'

@runner.test('start clears previous run timestamps')
def start_clears_previous_run_timestamps():
    rapid_poll = load_tool()

    times = iter([
        1000000000,
        1010000000,
        1020000000,
        1030000000,
    ])

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: next(times),
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll_once(
        lambda host, port, timeout_ms: True
    )

    controller.poll_once(
        lambda host, port, timeout_ms: False
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    assert controller.started_at_ns == 1030000000
    assert controller.last_success_at_ns is None
    assert controller.first_failure_at_ns is None

@runner.test('polling loop stops after first failed probe')
def polling_loop_stops_after_first_failed_probe():
    rapid_poll = load_tool()

    probe_results = iter([
        True,
        True,
        False,
    ])

    sleeps = []

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: 1000000000,
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll(
        lambda host, port, timeout_ms: next(
            probe_results
        ),
        lambda seconds: sleeps.append(
            seconds
        ),
    )

    assert controller.status() == 'inert'

    assert sleeps == [
        0.01,
        0.01,
    ]

@runner.test('tcp probe succeeds when connection succeeds')
def tcp_probe_succeeds_when_connection_succeeds():
    rapid_poll = load_tool()

    calls = []

    class Connection:
        def close(self):
            calls.append(
                'closed'
            )

    def create_connection(target, timeout):
        calls.append(
            (
                target,
                timeout,
            )
        )

        return Connection()

    result = rapid_poll.tcp_probe(
        '192.168.88.1',
        443,
        100,
        create_connection,
    )

    assert result is True

    assert calls == [
        (
            (
                '192.168.88.1',
                443,
            ),
            0.1,
        ),
        'closed',
    ]

@runner.test('tcp probe fails when connection fails')
def tcp_probe_fails_when_connection_fails():
    rapid_poll = load_tool()

    def create_connection(target, timeout):
        raise OSError(
            'connection refused'
        )

    result = rapid_poll.tcp_probe(
        '192.168.88.1',
        443,
        100,
        create_connection,
    )

    assert result is False

@runner.test('reports polling run timing')
def reports_polling_run_timing():
    rapid_poll = load_tool()

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: 0,
    )

    controller.started_at_ns = 1000000000
    controller.last_success_at_ns = 2500000000
    controller.first_failure_at_ns = 2510000000

    report = controller.report()

    assert report == {
        'started_at_ns': 1000000000,
        'started_at_wall': None,
        'last_success_at_ns': 2500000000,
        'first_failure_at_ns': 2510000000,
        'first_failure_at_wall': None,
        'elapsed_to_failure_ns': 1510000000,
        'failure_window_ns': 10000000,
        'stop_reason': None
    }

@runner.test('reports incomplete polling run safely')
def reports_incomplete_polling_run_safely():
    rapid_poll = load_tool()

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: 0,
    )

    controller.started_at_ns = 1000000000
    controller.last_success_at_ns = 2500000000
    controller.first_failure_at_ns = None

    report = controller.report()

    assert report == {
        'started_at_ns': 1000000000,
        'started_at_wall': None,
        'last_success_at_ns': 2500000000,
        'first_failure_at_ns': None,
        'first_failure_at_wall': None,
        'elapsed_to_failure_ns': None,
        'failure_window_ns': None,
        'stop_reason': None
    }

@runner.test('formats completed polling report')
def formats_completed_polling_report():
    rapid_poll = load_tool()

    report = {
        'started_at_ns': 1000000000,
        'started_at_wall': '2026-09-02T14:30:00.000000Z',
        'last_success_at_ns': 2500000000,
        'first_failure_at_ns': 2510000000,
        'first_failure_at_wall': '2026-09-02T14:30:01.510000Z',
        'elapsed_to_failure_ns': 1510000000,
        'failure_window_ns': 10000000,
        'stop_reason': 'probe-failure',
    }

    text = rapid_poll.format_report(
        report
    )

    assert text == (
        'started_at_ns=1000000000\n'
        'started_at_wall=2026-09-02T14:30:00.000000Z\n'
        'last_success_at_ns=2500000000\n'
        'first_failure_at_ns=2510000000\n'
        'first_failure_at_wall=2026-09-02T14:30:01.510000Z\n'
        'elapsed_to_failure_ns=1510000000\n'
        'failure_window_ns=10000000\n'
        'stop_reason=probe-failure\n'
    )

@runner.test('start records wall clock time')
def start_records_wall_clock_time():
    rapid_poll = load_tool()

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: 1000000000,
        lambda: '2026-09-02T14:30:00.123456Z',
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    assert controller.started_at_wall == (
        '2026-09-02T14:30:00.123456Z'
    )

@runner.test('failed probe records wall clock time')
def failed_probe_records_wall_clock_time():
    rapid_poll = load_tool()

    monotonic_times = iter([
        1000000000,
        1010000000,
    ])

    wall_times = iter([
        '2026-09-02T14:30:00.000000Z',
        '2026-09-02T14:30:01.010000Z',
    ])

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: next(monotonic_times),
        lambda: next(wall_times),
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll_once(
        lambda host, port, timeout_ms: False
    )

    assert controller.first_failure_at_wall == (
        '2026-09-02T14:30:01.010000Z'
    )

@runner.test('report includes wall clock timestamps')
def report_includes_wall_clock_timestamps():
    rapid_poll = load_tool()

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: 0,
        lambda: None,
    )

    controller.started_at_ns = 1000000000
    controller.started_at_wall = (
        '2026-09-02T14:30:00.000000Z'
    )
    controller.last_success_at_ns = 2500000000
    controller.first_failure_at_ns = 2510000000
    controller.first_failure_at_wall = (
        '2026-09-02T14:30:01.510000Z'
    )

    report = controller.report()

    assert report['started_at_wall'] == (
        '2026-09-02T14:30:00.000000Z'
    )

    assert report['first_failure_at_wall'] == (
        '2026-09-02T14:30:01.510000Z'
    )

@runner.test('writes completed polling report to log file')
def writes_completed_polling_report_to_log_file():
    rapid_poll = load_tool()

    log_file = os.path.join(
        repo_root,
        'build',
        'rapid-poll-instrumentation.log',
    )

    config = rapid_poll.default_config()
    config['log_file'] = log_file

    controller = rapid_poll.Controller(
        config,
        lambda: 0,
        lambda: None,
    )

    controller.started_at_ns = 1000000000
    controller.started_at_wall = (
        '2026-09-02T14:30:00.000000Z'
    )
    controller.last_success_at_ns = 2500000000
    controller.first_failure_at_ns = 2510000000
    controller.first_failure_at_wall = (
        '2026-09-02T14:30:01.510000Z'
    )

    try:
        controller.write_report()

        with open(log_file) as handle:
            contents = handle.read()
    finally:
        if os.path.exists(log_file):
            os.remove(
                log_file
            )

    assert contents == rapid_poll.format_report(
        controller.report()
    )

@runner.test('appends completed polling reports to log file')
def appends_completed_polling_reports_to_log_file():
    rapid_poll = load_tool()

    log_file = os.path.join(
        repo_root,
        'build',
        'rapid-poll-instrumentation.log',
    )

    config = rapid_poll.default_config()
    config['log_file'] = log_file

    controller = rapid_poll.Controller(
        config,
        lambda: 0,
        lambda: None,
    )

    controller.started_at_ns = 1000000000
    controller.started_at_wall = (
        '2026-09-02T14:30:00.000000Z'
    )
    controller.last_success_at_ns = 2500000000
    controller.first_failure_at_ns = 2510000000
    controller.first_failure_at_wall = (
        '2026-09-02T14:30:01.510000Z'
    )

    first_report = rapid_poll.format_report(
        controller.report()
    )

    try:
        controller.write_report()
        controller.write_report()

        with open(log_file) as handle:
            contents = handle.read()
    finally:
        if os.path.exists(log_file):
            os.remove(
                log_file
            )

    assert contents == (
            first_report +
            '\n' +
            first_report
    )

@runner.test('failed probe writes completed report')
def failed_probe_writes_completed_report():
    rapid_poll = load_tool()

    written_reports = []

    controller = rapid_poll.Controller(
        rapid_poll.default_config(),
        lambda: 1000000000,
        lambda: '2026-09-02T14:30:00.000000Z',
    )

    controller.write_report = lambda: written_reports.append(
        controller.report()
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll_once(
        lambda host, port, timeout_ms: False
    )

    assert len(written_reports) == 1
    assert written_reports[0]['first_failure_at_ns'] == 1000000000
    assert controller.status() == 'inert'

@runner.test('handles start command')
def handles_start_command():
    rapid_poll = load_tool()

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

    assert_same('ok', result)
    assert_same([('127.0.0.1', 8082)], calls)

@runner.test('status command reports controller state')
def status_command_reports_controller_state():
    rapid_poll = load_tool()

    class FakeController:
        def status(self):
            return 'inert'

    result = rapid_poll.handle_command(
        FakeController(),
        'status',
    )

    assert_same('inert', result)

@runner.test('stop command is harmless while inert')
def stop_command_is_harmless_while_inert():
    rapid_poll = load_tool()

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

    assert_same('ok', result)
    assert_same(['stop'], calls)
    assert_same('inert', controller.status())

@runner.test('exit command stops controller')
def exit_command_stops_controller():
    rapid_poll = load_tool()

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

    assert_same(
        'ok',
        result,
    )

    assert_same(
        [
            'stop',
        ],
        calls,
    )

@runner.test('rejects unknown command')
def rejects_unknown_command():
    rapid_poll = load_tool()

    class FakeController:
        pass

    result = rapid_poll.handle_command(
        FakeController(),
        'banana',
    )

    assert_same(
        'error: unknown command',
        result,
    )

@runner.test('rejects malformed start command')
def rejects_malformed_start_command():
    rapid_poll = load_tool()

    class FakeController:
        def start(self, host, port):
            fail(
                'Controller must not be started'
            )

    try:
        result = rapid_poll.handle_command(
            FakeController(),
            'start 127.0.0.1',
        )
    except Exception as error:
        fail(
            'Unexpected exception: {}'.format(
                error
            )
        )

    assert_same(
        'error: malformed start command',
        result,
    )

@runner.test('rejects non-numeric start port')
def rejects_non_numeric_start_port():
    rapid_poll = load_tool()

    class FakeController:
        def start(self, host, port):
            fail(
                'Controller must not be started'
            )

    try:
        result = rapid_poll.handle_command(
            FakeController(),
            'start 127.0.0.1 banana',
        )
    except Exception as error:
        fail(
            'Unexpected exception: {}'.format(
                error
            )
        )

    assert_same(
        'error: malformed start command',
        result,
    )

@runner.test('command loop handles commands while inert')
def command_loop_handles_commands_while_inert():
    rapid_poll = load_tool()

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
        lambda response: responses.append(
            response
        ),
        2,
        lambda host, port, timeout_ms: True,
        lambda seconds: None,
    )

    assert_same(
        [
            'inert',
            'error: unknown command',
        ],
        responses,
    )

@runner.test('start command runs polling before next command')
def start_command_runs_polling_before_next_command():
    rapid_poll = load_tool()

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

    controller = FakeController()

    assert_true(
        hasattr(
            rapid_poll,
            'command_loop',
        ),
        'Rapid poll tool must provide command_loop',
    )

    rapid_poll.command_loop(
        controller,
        lambda: next(commands),
        lambda response: responses.append(
            response
        ),
        2,
        lambda host, port, timeout_ms: True,
        lambda seconds: None,
    )

    assert_same(
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

    assert_same(
        [
            'ok',
            'inert',
        ],
        responses,
    )

@runner.test('serves one command over a connection')
def serves_one_command_over_a_connection():
    rapid_poll = load_tool()

    sent = []

    class FakeConnection:
        def recv(self, size):
            assert_same(
                4096,
                size,
            )

            return b'status\n'

        def sendall(self, data):
            sent.append(
                data
            )

    class FakeController:
        def status(self):
            return 'inert'

    rapid_poll.serve_connection(
        FakeConnection(),
        FakeController(),
        lambda host, port, timeout_ms: True,
        lambda seconds: None,
        lambda controller, probe, sleep: None,
    )

    assert_same(
        [
            b'inert\n',
        ],
        sent,
    )

@runner.test('serve forever stops after exit command')
def serve_forever_stops_after_exit_command():
    rapid_poll = load_tool()

    events = []

    class FakeConnection:
        def recv(self, size):
            return b'exit\n'

        def sendall(self, data):
            events.append(
                (
                    'send',
                    data,
                )
            )

        def close(self):
            events.append(
                'close'
            )

    class FakeListener:
        def __init__(self):
            self.calls = 0

        def accept(self):
            self.calls += 1

            if self.calls == 1:
                return (
                    FakeConnection(),
                    'client',
                )

            fail(
                'serve_forever must stop after exit'
            )

    class FakeController:
        def stop(self):
            events.append(
                'stop'
            )

    rapid_poll.serve_forever(
        FakeListener(),
        FakeController(),
        'probe',
        'sleep',
    )

    assert_same(
        [
            'stop',
            (
                'send',
                b'ok\n',
            ),
            'close',
        ],
        events,
    )

@runner.test('start command delegates polling to poll runner')
def start_command_delegates_polling_to_poll_runner():
    rapid_poll = load_tool()

    events = []
    sent = []

    class FakeConnection:
        def recv(self, size):
            return b'start 127.0.0.1 8082\n'

        def sendall(self, data):
            sent.append(
                data
            )

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
            fail(
                'serve_connection must not poll synchronously'
            )

    controller = FakeController()

    def poll_runner(
            controller,
            probe,
            sleep,
    ):
        events.append(
            'poll-runner'
        )

    rapid_poll.serve_connection(
        FakeConnection(),
        controller,
        lambda host, port, timeout_ms: True,
        lambda seconds: None,
        poll_runner,
    )

    assert_same(
        [
            (
                'start',
                '127.0.0.1',
                8082,
            ),
            'poll-runner',
        ],
        events,
    )

    assert_same(
        [
            b'ok\n',
        ],
        sent,
    )

@runner.test('poll runner starts polling in background thread')
def poll_runner_starts_polling_in_background_thread():
    rapid_poll = load_tool()

    events = []

    class FakeController:
        def poll(self, probe, sleep):
            events.append(
                'poll'
            )

    class FakeThread:
        def __init__(self, target):
            events.append(
                'thread-created'
            )
            self.target = target

        def start(self):
            events.append(
                'thread-started'
            )

    rapid_poll.start_polling(
        FakeController(),
        lambda host, port, timeout_ms: True,
        lambda seconds: None,
        FakeThread,
    )

    assert_same(
        [
            'thread-created',
            'thread-started',
        ],
        events,
    )

@runner.test('creates unix control socket')
def creates_unix_control_socket():
    rapid_poll = load_tool()

    calls = []

    class FakeSocket:
        def bind(self, path):
            calls.append(
                (
                    'bind',
                    path,
                )
            )

        def listen(self):
            calls.append(
                (
                    'listen',
                )
            )

    def socket_factory(family, socket_type):
        calls.append(
            (
                'socket',
                family,
                socket_type,
            )
        )

        return FakeSocket()

    result = rapid_poll.create_control_socket(
        '/tmp/test-rpi.sock',
        socket_factory,
    )

    assert_same(
        [
            (
                'socket',
                rapid_poll.socket.AF_UNIX,
                rapid_poll.socket.SOCK_STREAM,
            ),
            (
                'bind',
                '/tmp/test-rpi.sock',
            ),
            (
                'listen',
            ),
        ],
        calls,
    )

    assert_true(
        result is not None
    )

@runner.test('removes stale control socket before binding')
def removes_stale_control_socket_before_binding():
    rapid_poll = load_tool()

    calls = []

    class FakeSocket:
        def bind(self, path):
            calls.append(
                (
                    'bind',
                    path,
                )
            )

        def listen(self):
            calls.append(
                (
                    'listen',
                )
            )

    def socket_factory(family, socket_type):
        return FakeSocket()

    def path_exists(path):
        calls.append(
            (
                'exists',
                path,
            )
        )

        return True

    def remove(path):
        calls.append(
            (
                'remove',
                path,
            )
        )

    rapid_poll.create_control_socket(
        '/tmp/test-rpi.sock',
        socket_factory,
        path_exists,
        remove,
    )

    assert_same(
        [
            (
                'exists',
                '/tmp/test-rpi.sock',
            ),
            (
                'remove',
                '/tmp/test-rpi.sock',
            ),
            (
                'bind',
                '/tmp/test-rpi.sock',
            ),
            (
                'listen',
            ),
        ],
        calls,
    )

@runner.test('serve forever accepts and serves connections')
def serve_forever_accepts_and_serves_connections():
    rapid_poll = load_tool()

    events = []

    class FakeConnection:
        def close(self):
            events.append(
                'close'
            )

    class FakeListener:
        def __init__(self):
            self.calls = 0

        def accept(self):
            self.calls += 1

            if self.calls == 1:
                return (
                    FakeConnection(),
                    'client',
                )

            raise StopIteration()

    listener = FakeListener()

    class FakeController:
        pass

    original_serve_connection = rapid_poll.serve_connection

    def fake_serve_connection(
            connection,
            controller,
            probe,
            sleep,
            poll_runner,
    ):
        events.append(
            (
                'serve',
                connection,
                controller,
                probe,
                sleep,
                poll_runner,
            )
        )

    rapid_poll.serve_connection = fake_serve_connection

    controller = FakeController()

    try:
        try:
            rapid_poll.serve_forever(
                listener,
                controller,
                'probe',
                'sleep',
            )
        except StopIteration:
            pass
    finally:
        rapid_poll.serve_connection = (
            original_serve_connection
        )

    assert_same(
        2,
        listener.calls,
    )

    assert_same(
        'serve',
        events[0][0],
    )

    assert_same(
        controller,
        events[0][2],
    )

    assert_same(
        'probe',
        events[0][3],
    )

    assert_same(
        'sleep',
        events[0][4],
    )

    assert_true(
        callable(
            events[0][5]
        )
    )

    assert_same(
        'close',
        events[1],
    )

@runner.test('stop command is accepted while polling')
def stop_command_is_accepted_while_polling():
    rapid_poll = load_tool()

    events = []

    class FakeConnection:
        def __init__(self, command):
            self.command = command
            self.sent = []

        def recv(self, size):
            return (
                    self.command + '\n'
            ).encode('utf-8')

        def sendall(self, data):
            self.sent.append(
                data
            )

        def close(self):
            pass

    start_connection = FakeConnection(
        'start 127.0.0.1 8082'
    )

    stop_connection = FakeConnection(
        'stop'
    )

    class FakeListener:
        def __init__(self):
            self.connections = iter([
                start_connection,
                stop_connection,
            ])

        def accept(self):
            try:
                return (
                    next(self.connections),
                    'client',
                )
            except StopIteration:
                raise StopIteration()

    class FakeController:
        def __init__(self):
            self.state = 'inert'

        def start(self, host, port):
            self.state = 'polling'
            events.append(
                'start'
            )

        def stop(self):
            events.append(
                'stop'
            )
            self.state = 'inert'

        def poll(self, probe, sleep):
            events.append(
                'poll'
            )

        def status(self):
            return self.state

    controller = FakeController()

    try:
        rapid_poll.serve_forever(
            FakeListener(),
            controller,
            'probe',
            'sleep',
        )
    except StopIteration:
        pass

    assert_same(
        [
            b'ok\n',
        ],
        start_connection.sent,
    )

    assert_same(
        [
            b'ok\n',
        ],
        stop_connection.sent,
    )

    assert_true(
        'stop' in events
    )

    assert_same(
        'inert',
        controller.status(),
    )

@runner.test('runs rapid polling service from config')
def runs_rapid_polling_service_from_config():
    rapid_poll = load_tool()

    events = []

    config = rapid_poll.default_config()

    class FakeController:
        pass

    def controller_factory(
            actual_config,
            monotonic_ns,
            wall_time,
    ):
        assert_same(
            config,
            actual_config,
        )

        events.append(
            'controller'
        )

        return FakeController()

    def create_control_socket(path):
        assert_same(
            '/tmp/rapid-poll-instrumentation.sock',
            path,
        )

        events.append(
            'listener'
        )

        return 'listener'

    def serve_forever(
            listener,
            controller,
            probe,
            sleep,
    ):
        assert_same(
            'listener',
            listener,
        )

        assert_true(
            isinstance(
                controller,
                FakeController,
            )
        )

        assert_same(
            rapid_poll.tcp_probe,
            probe,
        )

        events.append(
            'serve'
        )

    rapid_poll.run(
        config,
        controller_factory,
        create_control_socket,
        serve_forever,
        lambda: 123,
        lambda: 'wall-time',
        lambda seconds: None,
    )

    assert_same(
        [
            'controller',
            'listener',
            'serve',
        ],
        events,
    )

@runner.test('formats current UTC wall clock time')
def formats_current_utc_wall_clock_time():
    rapid_poll = load_tool()

    class FakeDateTime:
        @classmethod
        def now(cls, timezone):
            assert_same(
                rapid_poll.datetime.timezone.utc,
                timezone,
            )

            return cls()

        def isoformat(self, timespec):
            assert_same(
                'microseconds',
                timespec,
            )

            return '2026-09-03T22:42:17.123456+00:00'

    actual = rapid_poll.utc_wall_time(
        FakeDateTime
    )

    assert_same(
        '2026-09-03T22:42:17.123456Z',
        actual,
    )

@runner.test('main loads config and runs service')
def main_loads_config_and_runs_service():
    rapid_poll = load_tool()

    events = []

    config = rapid_poll.default_config()

    def load_config(config_file):
        assert_same(
            '/tmp/test-rpi.conf',
            config_file,
        )

        events.append(
            'load'
        )

        return config

    def run(
            actual_config,
            controller_factory,
            control_socket_factory,
            serve_forever_function,
            monotonic_ns,
            wall_time,
            sleep,
    ):
        assert_same(
            config,
            actual_config,
        )

        events.append(
            'run'
        )

    rapid_poll.main(
        '/tmp/test-rpi.conf',
        load_config,
        run,
    )

    assert_same(
        [
            'load',
            'run',
        ],
        events,
    )

runner.finish()
