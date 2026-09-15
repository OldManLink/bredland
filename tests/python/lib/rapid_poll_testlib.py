import importlib.util
import os

import testlib


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


def load_rapid_poll():
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


def new_controller(
        rapid_poll,
        config=None,
        monotonic_ns=None,
        wall_time=None,
):
    if config is None:
        config = rapid_poll.default_config()

    if monotonic_ns is None:
        monotonic_ns = lambda: 0

    if wall_time is None:
        wall_time = lambda: None

    return rapid_poll.Controller(
        config,
        monotonic_ns,
        wall_time,
    )


def started_controller(
        rapid_poll,
        host='127.0.0.1',
        port=8082,
        config=None,
        monotonic_ns=None,
        wall_time=None,
):
    controller = new_controller(
        rapid_poll,
        config,
        monotonic_ns,
        wall_time,
    )

    controller.start(
        host,
        port,
    )

    return controller


def completed_controller(
        rapid_poll,
        config=None,
):
    controller = new_controller(
        rapid_poll,
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

    return controller


def completed_report():
    return {
        'started_at_ns': 1000000000,
        'started_at_wall': '2026-09-02T14:30:00.000000Z',
        'last_success_at_ns': 2500000000,
        'first_failure_at_ns': 2510000000,
        'first_failure_at_wall': '2026-09-02T14:30:01.510000Z',
        'elapsed_to_failure_ns': 1510000000,
        'failure_window_ns': 10000000,
        'stop_reason': None,
    }


def temporary_config(contents):
    return testlib.temporary_text_file(
        contents,
        suffix='.conf',
    )
