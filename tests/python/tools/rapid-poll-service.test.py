import os
import sys
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'lib')))
import testlib
from test_suite_runner import TestSuiteRunner
from rapid_poll_testlib import load_rapid_poll


runner = TestSuiteRunner(
    'rapid-poll-service'
)

rapid_poll = load_rapid_poll()

@runner.test('runs rapid polling service from config')
def runs_rapid_polling_service_from_config():
    events = []

    config = rapid_poll.default_config()

    class FakeController:
        pass

    def controller_factory(
            actual_config,
            monotonic_ns,
            wall_time,
    ):
        testlib.assert_same(
            config,
            actual_config,
        )

        events.append(
            'controller'
        )

        return FakeController()

    def create_control_socket(path):
        testlib.assert_same(
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
        testlib.assert_same(
            'listener',
            listener,
        )

        testlib.assert_true(
            isinstance(
                controller,
                FakeController,
            )
        )

        testlib.assert_same(
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

    testlib.assert_same(
        [
            'controller',
            'listener',
            'serve',
        ],
        events,
    )


@runner.test('formats current UTC wall clock time')
def formats_current_utc_wall_clock_time():
    class FakeDateTime:
        @classmethod
        def now(cls, timezone):
            testlib.assert_same(
                rapid_poll.datetime.timezone.utc,
                timezone,
            )

            return cls()

        def isoformat(self, timespec):
            testlib.assert_same(
                'microseconds',
                timespec,
            )

            return '2026-09-03T22:42:17.123456+00:00'

    actual = rapid_poll.utc_wall_time(
        FakeDateTime
    )

    testlib.assert_same(
        '2026-09-03T22:42:17.123456Z',
        actual,
    )

@runner.test('main loads config and runs service')
def main_loads_config_and_runs_service():
    events = []

    config = rapid_poll.default_config()

    def load_config(config_file):
        testlib.assert_same(
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
        testlib.assert_same(
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

    testlib.assert_same(
        [
            'load',
            'run',
        ],
        events,
    )

runner.finish()