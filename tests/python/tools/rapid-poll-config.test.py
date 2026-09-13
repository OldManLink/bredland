import os
import sys
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'lib')))
import testlib
from builtins import ValueError
from test_suite_runner import TestSuiteRunner
from rapid_poll_testlib import (load_rapid_poll, temporary_config)


runner = TestSuiteRunner(
    'rapid-poll-config'
)

rapid_poll = load_rapid_poll()

@runner.test('uses sensible rapid polling defaults')
def uses_sensible_rapid_polling_defaults():
    config = rapid_poll.default_config()

    testlib.assert_same(
        {
            'poll_interval_ms': 10,
            'connect_timeout_ms': 100,
            'control_socket': '/tmp/rapid-poll-instrumentation.sock',
            'log_file': '/tmp/rapid-poll-instrumentation.log',
        },
        config
    )

@runner.test('loads config overrides')
def loads_config_overrides():
    with temporary_config(
            'poll_interval_ms=25\n'
            'connect_timeout_ms=250\n'
            'control_socket=/tmp/test-rpi.sock\n'
            'log_file=/tmp/test-rpi.log\n'
    ) as config_file:
        config = rapid_poll.load_config(
            config_file
        )

    testlib.assert_same(
        {
            'poll_interval_ms': 25,
            'connect_timeout_ms': 250,
            'control_socket': '/tmp/test-rpi.sock',
            'log_file': '/tmp/test-rpi.log',
        },
        config,
    )

@runner.test('uses defaults when config file is missing')
def uses_defaults_when_config_file_is_missing():
    config = rapid_poll.load_config(
        '/tmp/no-such-routeros-rpi.conf'
    )

    testlib.assert_same(rapid_poll.default_config(), config)

@runner.test('rejects invalid polling interval')
def rejects_invalid_polling_interval():
    with temporary_config(
            'poll_interval_ms=0\n'
    ) as config_file:
        testlib.assert_throws(
            ValueError,
            'poll_interval_ms must be positive',
            lambda: rapid_poll.load_config(
                config_file
            ),
        )

@runner.test('rejects invalid connect timeout')
def rejects_invalid_connect_timeout():
    with temporary_config(
            'connect_timeout_ms=0\n'
    ) as config_file:
        testlib.assert_throws(
            ValueError,
            'connect_timeout_ms must be positive',
            lambda: rapid_poll.load_config(
                config_file
            ),
        )



runner.finish()