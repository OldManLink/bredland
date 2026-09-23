import os
import sys

sys.path.insert(
    0,
    os.path.join(
        os.path.dirname(__file__),
        'lib',
    ),
)

import testlib
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import load_trusted_discovery


runner = TestSuiteRunner(
    'trusted-action-result-registry'
)

trusted_discovery = load_trusted_discovery()


@runner.test('new action result is pending')
def new_action_result_is_pending():
    registry = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    registry.create(
        'request-123',
        200,
    )

    result = registry.get(
        'request-123',
    )

    testlib.assert_same(
        {
            'status': 'pending',
        },
        result,
    )

@runner.test('action result can succeed')
def action_result_can_succeed():
    registry = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    registry.create(
        'request-123',
        200,
    )

    registry.succeed(
        'request-123',
    )

    testlib.assert_same(
        {
            'status': 'succeeded',
        },
        registry.get(
            'request-123',
        ),
    )

@runner.test('action result can fail')
def action_result_can_fail():
    registry = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    registry.create(
        'request-123',
        200,
    )

    registry.fail(
        'request-123',
    )

    testlib.assert_same(
        {
            'status': 'failed',
        },
        registry.get(
            'request-123',
        ),
    )

@runner.test('expired action result is unavailable')
def expired_action_result_is_unavailable():
    now = [100]

    registry = trusted_discovery.ActionResultRegistry(
        lambda: now[0],
    )

    registry.create(
        'request-123',
        200,
    )

    now[0] = 200

    testlib.assert_same(
        None,
        registry.get(
            'request-123',
        ),
    )

@runner.test('unknown action result is unavailable')
def unknown_action_result_is_unavailable():
    registry = trusted_discovery.ActionResultRegistry(
        lambda: 100,
    )

    testlib.assert_same(
        None,
        registry.get(
            'no-such-request',
        ),
    )

@runner.test('creating action result prunes expired results')
def creating_action_result_prunes_expired_results():
    now = [100]

    registry = trusted_discovery.ActionResultRegistry(
        lambda: now[0],
    )

    registry.create(
        'expired-request',
        150,
    )

    now[0] = 200

    registry.create(
        'current-request',
        300,
    )

    testlib.assert_same(
        [
            'current-request',
        ],
        sorted(
            registry.results.keys()
        ),
    )

    testlib.assert_same(
        {
            'status': 'pending',
        },
        registry.get(
            'current-request',
        ),
    )


runner.finish()
