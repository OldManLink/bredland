import os
import sys
import threading

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


runner = TestSuiteRunner('trusted-discovery-action-guard')

trusted_discovery = load_trusted_discovery()

@runner.test('claims trusted action resolution only once')
def claims_trusted_action_resolution_only_once():
    guard = trusted_discovery.ActionGuard(
        lambda: 100,
        30,
    )

    testlib.assert_true(
        guard.claim(
            'install-routeros-update'
        )
    )

    testlib.assert_false(
        guard.claim(
            'install-routeros-update'
        )
    )

@runner.test('releases trusted action claim after failure')
def releases_trusted_action_claim_after_failure():
    guard = trusted_discovery.ActionGuard(
        lambda: 100,
        30,
    )

    testlib.assert_true(
        guard.claim(
            'install-routeros-update'
        )
    )

    guard.release(
        'install-routeros-update'
    )

    testlib.assert_true(
        guard.claim(
            'install-routeros-update'
        )
    )

@runner.test('keeps trusted action claimed during cooldown')
def keeps_trusted_action_claimed_during_cooldown():
    now = [100]

    guard = trusted_discovery.ActionGuard(
        lambda: now[0],
        30,
    )

    testlib.assert_true(
        guard.claim(
            'install-routeros-update'
        )
    )

    guard.complete(
        'install-routeros-update'
    )

    testlib.assert_false(
        guard.claim(
            'install-routeros-update'
        )
    )

    now[0] = 131

    testlib.assert_true(
        guard.claim(
            'install-routeros-update'
        )
    )

@runner.test('claims trusted action atomically across threads')
def claims_trusted_action_atomically_across_threads():
    results = []
    errors = []

    guard = trusted_discovery.ActionGuard(
        lambda: 100,
        30,
    )

    def claim():
        try:
            results.append(
                guard.claim(
                    'install-routeros-update'
                )
            )
        except Exception as error:
            errors.append(error)

    threads = [
        threading.Thread(target=claim),
        threading.Thread(target=claim),
    ]

    for thread in threads:
        thread.start()

    for thread in threads:
        thread.join()

    testlib.assert_same(
        [],
        errors,
    )

    testlib.assert_same(
        2,
        len(results),
    )

    testlib.assert_same(
        1,
        results.count(True),
    )

    testlib.assert_same(
        1,
        results.count(False),
    )



runner.finish()
