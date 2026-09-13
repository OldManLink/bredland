import os
import sys
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'lib')))
import testlib
from builtins import (OSError, iter, len, next, open)
from test_suite_runner import TestSuiteRunner
from rapid_poll_testlib import (completed_report, load_rapid_poll, new_controller, started_controller)


runner = TestSuiteRunner('rapid-poll-controller')

rapid_poll = load_rapid_poll()

@runner.test('starts in inert state')
def starts_in_inert_state():
    controller = new_controller(rapid_poll)

    testlib.assert_same('inert', controller.status())

@runner.test('start moves controller to polling state')
def start_moves_controller_to_polling_state():
    controller = new_controller(rapid_poll)

    controller.start( '127.0.0.1', 8082)

    testlib.assert_same( 'polling', controller.status())
    testlib.assert_same(('127.0.0.1', 8082, ), controller.target)

@runner.test('stop returns controller to inert state')
def stop_returns_controller_to_inert_state():
    controller = started_controller(
        rapid_poll,
        '127.0.0.1',
        8082,
    )

    controller.stop()

    testlib.assert_same('inert', controller.status())
    testlib.assert_same(None, controller.target)

@runner.test('stop writes operator-stopped polling report')
def stop_writes_operator_stopped_polling_report():
    written_reports = []

    controller = started_controller(
        rapid_poll,
        '127.0.0.1',
        8082,
        monotonic_ns=lambda: 1000000000,
        wall_time=lambda: '2026-09-04T09:30:00.000000Z',
    )

    controller.write_report = lambda: written_reports.append(
        controller.report()
    )

    controller.stop()

    testlib.assert_same(1, len(written_reports))

    testlib.assert_same(
        'operator',
        written_reports[0]['stop_reason'],
    )

@runner.test('successful probe keeps controller polling')
def successful_probe_keeps_controller_polling():
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

    controller = started_controller(
        rapid_poll,
        '127.0.0.1',
        8082,
    )

    controller.poll_once(
        probe
    )

    testlib.assert_same(
        'polling',
        controller.status(),
    )

    testlib.assert_same(
        [
            (
                '127.0.0.1',
                8082,
                100,
            ),
        ],
        probes,
    )

@runner.test('failed probe returns controller to inert state')
def failed_probe_returns_controller_to_inert_state():
    controller = started_controller(
        rapid_poll,
        '127.0.0.1',
        8082,
    )

    controller.poll_once(
        lambda host, port, timeout_ms: False
    )

    testlib.assert_same(
        'inert',
        controller.status(),
    )

    testlib.assert_same(
        None,
        controller.target,
    )

@runner.test('start records polling start time')
def start_records_polling_start_time():
    controller = new_controller(
        rapid_poll,
        monotonic_ns=lambda: 123456789,
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    testlib.assert_same(
        123456789,
        controller.started_at_ns,
    )

@runner.test('successful probe records last success time')
def successful_probe_records_last_success_time():
    times = iter([
        1000000000,
        1010000000,
    ])

    controller = new_controller(
        rapid_poll,
        monotonic_ns=lambda: next(times),
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll_once(lambda host, port, timeout_ms: True)

    testlib.assert_same(1010000000, controller.last_success_at_ns)

@runner.test('failed probe records first failure time')
def failed_probe_records_first_failure_time():
    times = iter([
        1000000000,
        1010000000,
        1020000000,
    ])

    controller = new_controller(
        rapid_poll,
        monotonic_ns=lambda: next(times),
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll_once(lambda host, port, timeout_ms: True )
    controller.poll_once(lambda host, port, timeout_ms: False)

    testlib.assert_same(
        1020000000,
        controller.first_failure_at_ns,
    )

    testlib.assert_same('inert', controller.status())

@runner.test('start clears previous run timestamps')
def start_clears_previous_run_timestamps():
    controller = new_controller(
        rapid_poll,
        monotonic_ns=lambda: 2000000000,
    )

    controller.started_at_ns = 1000000000
    controller.last_success_at_ns = 1010000000
    controller.first_failure_at_ns = 1020000000

    controller.start(
        '127.0.0.1',
        8082,
    )

    testlib.assert_same(
        2000000000,
        controller.started_at_ns,
    )

    testlib.assert_same(
        None,
        controller.last_success_at_ns,
    )

    testlib.assert_same(
        None,
        controller.first_failure_at_ns,
    )

@runner.test('polling loop stops after first failed probe')
def polling_loop_stops_after_first_failed_probe():
    probe_results = iter([
        True,
        True,
        False,
    ])

    sleeps = []

    controller = started_controller(
        rapid_poll,
        '127.0.0.1',
        8082,
        monotonic_ns=lambda: 1000000000,
    )

    controller.poll(
        lambda host, port, timeout_ms: next(
            probe_results
        ),
        sleeps.append,
    )

    testlib.assert_same(
        'inert',
        controller.status(),
    )

    testlib.assert_same(
        [
            0.01,
            0.01,
        ],
        sleeps,
    )

@runner.test('tcp probe succeeds when connection succeeds')
def tcp_probe_succeeds_when_connection_succeeds():
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

    testlib.assert_true(
        result,
    )

    testlib.assert_same(
        [
            (
                (
                    '192.168.88.1',
                    443,
                ),
                0.1,
            ),
            'closed',
        ],
        calls,
    )

@runner.test('tcp probe fails when connection fails')
def tcp_probe_fails_when_connection_fails():
    def create_connection(_, __):
        raise OSError(
            'connection refused'
        )

    result = rapid_poll.tcp_probe(
        '192.168.88.1',
        443,
        100,
        create_connection,
    )

    testlib.assert_false(
        result,
    )

@runner.test('reports polling run timing')
def reports_polling_run_timing():
    controller = new_controller(
        rapid_poll
    )

    controller.started_at_ns = 1000000000
    controller.last_success_at_ns = 2500000000
    controller.first_failure_at_ns = 2510000000

    report = controller.report()

    testlib.assert_same(
        {
            'started_at_ns': 1000000000,
            'started_at_wall': None,
            'last_success_at_ns': 2500000000,
            'first_failure_at_ns': 2510000000,
            'first_failure_at_wall': None,
            'elapsed_to_failure_ns': 1510000000,
            'failure_window_ns': 10000000,
            'stop_reason': None,
        },
        report,
    )

@runner.test('reports incomplete polling run safely')
def reports_incomplete_polling_run_safely():
    controller = new_controller(
        rapid_poll
    )

    controller.started_at_ns = 1000000000
    controller.last_success_at_ns = 2500000000
    controller.first_failure_at_ns = None

    report = controller.report()

    testlib.assert_same(
        {
            'started_at_ns': 1000000000,
            'started_at_wall': None,
            'last_success_at_ns': 2500000000,
            'first_failure_at_ns': None,
            'first_failure_at_wall': None,
            'elapsed_to_failure_ns': None,
            'failure_window_ns': None,
            'stop_reason': None,
        },
        report,
    )

@runner.test('formats completed polling report')
def formats_completed_polling_report():
    report = completed_report()
    report['stop_reason'] = 'probe-failure'

    text = rapid_poll.format_report(
        report
    )

    testlib.assert_same(
        (
            'started_at_ns=1000000000\n'
            'started_at_wall=2026-09-02T14:30:00.000000Z\n'
            'last_success_at_ns=2500000000\n'
            'first_failure_at_ns=2510000000\n'
            'first_failure_at_wall=2026-09-02T14:30:01.510000Z\n'
            'elapsed_to_failure_ns=1510000000\n'
            'failure_window_ns=10000000\n'
            'stop_reason=probe-failure\n'
        ),
        text,
    )

@runner.test('start records wall clock time')
def start_records_wall_clock_time():
    controller = new_controller(
        rapid_poll,
        wall_time=lambda: '2026-09-02T14:30:00.123456Z',
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    testlib.assert_same(
        '2026-09-02T14:30:00.123456Z',
        controller.started_at_wall,
    )

@runner.test('failed probe records wall clock time')
def failed_probe_records_wall_clock_time():
    wall_times = iter([
        '2026-09-02T14:30:00.123456Z',
        '2026-09-02T14:30:01.654321Z',
    ])

    controller = new_controller(
        rapid_poll,
        wall_time=lambda: next(wall_times),
    )

    controller.start(
        '127.0.0.1',
        8082,
    )

    controller.poll_once(
        lambda host, port, timeout_ms: False
    )

    testlib.assert_same(
        '2026-09-02T14:30:01.654321Z',
        controller.first_failure_at_wall,
    )

@runner.test('report includes wall clock times')
def report_includes_wall_clock_times():
    controller = new_controller(
        rapid_poll
    )

    controller.started_at_ns = 1000000000
    controller.started_at_wall = '2026-09-02T14:30:00.123456Z'
    controller.last_success_at_ns = 2500000000
    controller.first_failure_at_ns = 2510000000
    controller.first_failure_at_wall = '2026-09-02T14:30:01.654321Z'

    report = controller.report()

    testlib.assert_same(
        '2026-09-02T14:30:00.123456Z',
        report['started_at_wall'],
    )

    testlib.assert_same(
        '2026-09-02T14:30:01.654321Z',
        report['first_failure_at_wall'],
    )

@runner.test('writes completed polling report to log file')
def writes_completed_polling_report_to_log_file():
    with testlib.temporary_text_file() as log_file:
        os.remove(
            log_file
        )

        config = rapid_poll.default_config()
        config['log_file'] = log_file

        controller = new_controller(
            rapid_poll,
            config=config,
        )

        controller.started_at_ns = 1000000000
        controller.started_at_wall = '2026-09-02T14:30:00.000000Z'
        controller.last_success_at_ns = 2500000000
        controller.first_failure_at_ns = 2510000000
        controller.first_failure_at_wall = '2026-09-02T14:30:01.510000Z'

        controller.write_report()

        with open(log_file) as handle:
            contents = handle.read()

    testlib.assert_same(
        rapid_poll.format_report(
            controller.report()
        ),
        contents,
    )

@runner.test('appends completed polling reports to log file')
def appends_completed_polling_reports_to_log_file():
    with testlib.temporary_text_file() as log_file:
        config = rapid_poll.default_config()
        config['log_file'] = log_file

        controller = new_controller(
            rapid_poll,
            config=config,
        )

        controller.started_at_ns = 1000000000
        controller.started_at_wall = '2026-09-02T14:30:00.000000Z'
        controller.last_success_at_ns = 2500000000
        controller.first_failure_at_ns = 2510000000
        controller.first_failure_at_wall = '2026-09-02T14:30:01.510000Z'
        report = rapid_poll.format_report(controller.report())
        controller.write_report()
        controller.write_report()

        with open(log_file) as handle:
            contents = handle.read()

    testlib.assert_same(
        '\n' + report + '\n' + report,
        contents,
    )

@runner.test('failed probe writes completed report')
def failed_probe_writes_completed_report():
    written_reports = []

    controller = new_controller(
        rapid_poll,
        monotonic_ns=lambda: 1000000000,
        wall_time=lambda: '2026-09-02T14:30:00.000000Z',
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

    testlib.assert_same(1, len(written_reports))
    testlib.assert_same(1000000000, written_reports[0]['first_failure_at_ns'])
    testlib.assert_same('inert', controller.status())



runner.finish()