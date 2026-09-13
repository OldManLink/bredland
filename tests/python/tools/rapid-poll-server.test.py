import os
import sys
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'lib')))
import testlib
from test_suite_runner import TestSuiteRunner
from rapid_poll_testlib import load_rapid_poll


runner = TestSuiteRunner(
    'rapid-poll-server'
)

rapid_poll = load_rapid_poll()

@runner.test('serves one command over a connection')
def serves_one_command_over_a_connection():
    sent = []

    class FakeConnection:
        def recv(self, size):
            testlib.assert_same(
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

    testlib.assert_same(
        [
            b'inert\n',
        ],
        sent,
    )

@runner.test('serve forever stops after exit command')
def serve_forever_stops_after_exit_command():
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

            testlib.fail(
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

    testlib.assert_same(
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
            testlib.fail(
                'serve_connection must not poll synchronously'
            )

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
        FakeController(),
        lambda host, port, timeout_ms: True,
        lambda seconds: None,
        poll_runner,
    )

    testlib.assert_same(
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

    testlib.assert_same(
        [
            b'ok\n',
        ],
        sent,
    )

@runner.test('poll runner starts polling in background thread')
def poll_runner_starts_polling_in_background_thread():
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

    testlib.assert_same(
        [
            'thread-created',
            'thread-started',
        ],
        events,
    )

@runner.test('creates unix control socket')
def creates_unix_control_socket():
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

    testlib.assert_same(
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

    testlib.assert_true(
        result is not None
    )

@runner.test('removes stale control socket before binding')
def removes_stale_control_socket_before_binding():
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

    testlib.assert_same(
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

    testlib.assert_same(
        2,
        listener.calls,
    )

    testlib.assert_same(
        'serve',
        events[0][0],
    )

    testlib.assert_same(
        controller,
        events[0][2],
    )

    testlib.assert_same(
        'probe',
        events[0][3],
    )

    testlib.assert_same(
        'sleep',
        events[0][4],
    )

    testlib.assert_true(
        callable(
            events[0][5]
        )
    )

    testlib.assert_same(
        'close',
        events[1],
    )

@runner.test('stop command is accepted while polling')
def stop_command_is_accepted_while_polling():
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

    testlib.assert_same(
        [
            b'ok\n',
        ],
        start_connection.sent,
    )

    testlib.assert_same(
        [
            b'ok\n',
        ],
        stop_connection.sent,
    )

    testlib.assert_true(
        'stop' in events
    )

    testlib.assert_same(
        'inert',
        controller.status(),
    )



runner.finish()