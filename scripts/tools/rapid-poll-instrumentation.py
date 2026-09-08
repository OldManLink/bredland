import datetime
import os
import socket
import time
import threading

class Controller:
    def __init__(
            self,
            config,
            monotonic_ns=time.monotonic_ns,
            wall_time=lambda: None,
    ):
        self.config = config
        self.monotonic_ns = monotonic_ns
        self.wall_time = wall_time
        self.target = None
        self.state = 'inert'
        self.started_at_ns = None
        self.started_at_wall = None
        self.last_success_at_ns = None
        self.first_failure_at_ns = None
        self.first_failure_at_wall = None
        self.stop_reason = None

    def status(self):
        return self.state

    def start(self, host, port):
        self.started_at_ns = self.monotonic_ns()
        self.last_success_at_ns = None
        self.first_failure_at_ns = None
        self.started_at_wall = self.wall_time()
        self.first_failure_at_wall = None
        self.stop_reason = None

        self.target = (
            host,
            port,
        )

        self.state = 'polling'

    def stop(self):
        if self.state != 'polling':
            return

        self.finish(
            'operator'
        )

    def finish(self, stop_reason):
        self.stop_reason = stop_reason
        self.write_report()

        self.target = None
        self.state = 'inert'

    def poll(self, probe, sleep):
        while self.status() == 'polling':
            self.poll_once(
                probe
            )

            if self.status() != 'polling':
                return

            sleep(
                self.config['poll_interval_ms']
                / 1000.0
            )

    def poll_once(self, probe):
        host, port = self.target

        if probe(
                host,
                port,
                self.config['connect_timeout_ms'],
        ):
            self.last_success_at_ns = self.monotonic_ns()
            return

        self.first_failure_at_ns = self.monotonic_ns()
        self.first_failure_at_wall = self.wall_time()
        self.finish(
            'probe-failure'
        )

    def report(self):
        elapsed_to_failure_ns = None
        failure_window_ns = None

        if self.first_failure_at_ns is not None:
            elapsed_to_failure_ns = (
                self.first_failure_at_ns
                - self.started_at_ns
            )

            if self.last_success_at_ns is not None:
                failure_window_ns = (
                    self.first_failure_at_ns
                    - self.last_success_at_ns
                )

        return {
            'started_at_ns': self.started_at_ns,
            'started_at_wall': self.started_at_wall,
            'last_success_at_ns': self.last_success_at_ns,
            'first_failure_at_ns': self.first_failure_at_ns,
            'first_failure_at_wall': self.first_failure_at_wall,
            'elapsed_to_failure_ns': elapsed_to_failure_ns,
            'failure_window_ns': failure_window_ns,
            'stop_reason': self.stop_reason,
        }

    def write_report(self):
        log_file = self.config['log_file']

        separator = ''

        if os.path.exists(log_file):
            separator = '\n'

        with open(
            log_file,
            'a',
        ) as handle:
            handle.write(
                separator
            )

            handle.write(
                format_report(
                    self.report()
                )
            )

def default_config():
    return {
        'poll_interval_ms': 10,
        'connect_timeout_ms': 100,
        'control_socket': '/tmp/rapid-poll-instrumentation.sock',
        'log_file': '/tmp/rapid-poll-instrumentation.log',
    }

def load_config(config_file):
    if not os.path.exists(config_file):
        return default_config()

    config = default_config()

    with open(config_file) as handle:
        for line in handle:
            line = line.strip()

            if not line:
                continue

            key, value = line.split(
                '=',
                1,
            )

            if key in (
                    'poll_interval_ms',
                    'connect_timeout_ms',
            ):
                value = int(
                    value
                )
            
                if value <= 0:
                    raise ValueError(
                        '{} must be positive'.format(
                            key
                        )
                    )

            config[key] = value

    return config

def start_polling(
        controller,
        probe,
        sleep,
        thread_factory,
):
    thread = thread_factory(
        target=lambda: controller.poll(
            probe,
            sleep,
        )
    )

    thread.start()

def tcp_probe(
        host,
        port,
        timeout_ms,
        create_connection=socket.create_connection,
):
    try:
        connection = create_connection(
            (
                host,
                port,
            ),
            timeout_ms / 1000.0,
            )

        connection.close()
        return True
    except OSError:
        return False

def format_report(report):
    return (
        'started_at_ns={}\n'
        'started_at_wall={}\n'
        'last_success_at_ns={}\n'
        'first_failure_at_ns={}\n'
        'first_failure_at_wall={}\n'
        'elapsed_to_failure_ns={}\n'
        'failure_window_ns={}\n'
        'stop_reason={}\n'
        .format(
            report['started_at_ns'],
            report['started_at_wall'],
            report['last_success_at_ns'],
            report['first_failure_at_ns'],
            report['first_failure_at_wall'],
            report['elapsed_to_failure_ns'],
            report['failure_window_ns'],
            report['stop_reason'],
        )
    )

def utc_wall_time(
        datetime_class=datetime.datetime,
):
    value = datetime_class.now(
        datetime.timezone.utc
    ).isoformat(
        timespec='microseconds'
    )

    return value.replace(
        '+00:00',
        'Z',
    )

def handle_command(controller, command):
    parts = command.split()

    if parts[0] == 'start':
        if len(parts) != 3:
            return 'error: malformed start command'

        try:
            port = int(
                parts[2]
            )
        except ValueError:
            return 'error: malformed start command'

        controller.start(
            parts[1],
            port,
        )

        return 'ok'

    if parts[0] == 'status':
        return controller.status()

    if command == 'stop':
        controller.stop()
        return 'ok'

    if command == 'exit':
        controller.stop()
        return 'ok'

    return 'error: unknown command'

def command_loop(
        controller,
        read_command,
        write_response,
        command_count,
        probe,
        sleep,
):
    for _ in range(command_count):
        command = read_command()

        response = handle_command(
            controller,
            command,
        )

        write_response(
            response
        )

        if (
                command.startswith('start ')
                and response == 'ok'
        ):
            controller.poll(
                probe,
                sleep,
            )

def serve_connection(
        connection,
        controller,
        probe,
        sleep,
        poll_runner,
):
    command = connection.recv(
        4096
    ).decode(
        'utf-8'
    ).strip()

    response = handle_command(
        controller,
        command,
    )

    connection.sendall(
        (
                response + '\n'
        ).encode(
            'utf-8'
        )
    )

    if (
            command.startswith('start ')
            and response == 'ok'
    ):
        poll_runner(
            controller,
            probe,
            sleep,
        )

    return command == 'exit'

def create_control_socket(
        path,
        socket_factory=socket.socket,
        path_exists=os.path.exists,
        remove=os.remove,
):
    if path_exists(
            path
    ):
        remove(
            path
        )

    control_socket = socket_factory(
        socket.AF_UNIX,
        socket.SOCK_STREAM,
    )

    control_socket.bind(path)
    control_socket.listen()
    return control_socket


def serve_forever(
        listener,
        controller,
        probe,
        sleep,
):
    while True:
        connection, _ = listener.accept()

        try:
            should_exit = serve_connection(
                connection,
                controller,
                probe,
                sleep,
                lambda controller, probe, sleep: start_polling(
                    controller,
                    probe,
                    sleep,
                    threading.Thread,
                ),
            )
        finally:
            connection.close()

        if should_exit:
            return


def run(
        config,
        controller_factory,
        control_socket_factory,
        serve_forever_function,
        monotonic_ns,
        wall_time,
        sleep,
):
    controller = controller_factory(
        config,
        monotonic_ns,
        wall_time,
    )

    listener = control_socket_factory(
        config['control_socket']
    )

    serve_forever_function(
        listener,
        controller,
        tcp_probe,
        sleep,
    )


def main(
        config_file,
        load_config_function=load_config,
        run_function=run,
):
    config = load_config_function(
        config_file
    )

    run_function(
        config,
        Controller,
        create_control_socket,
        serve_forever,
        time.monotonic_ns,
        utc_wall_time,
        time.sleep,
    )

if __name__ == '__main__':
    config_file = os.path.join(
        os.path.dirname(__file__),
        'rapid-poll-instrumentation.conf',
    )

    main(
        config_file
    )
