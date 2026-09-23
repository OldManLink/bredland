import os
import sys
import threading
import time

sys.path.insert(
    0,
    os.path.join(
        os.path.dirname(__file__),
        'lib',
    ),
)

import testlib
from test_suite_runner import TestSuiteRunner
from trusted_discovery_testlib import (load_trusted_discovery, registered_capability_registry)


runner = TestSuiteRunner('trusted-discovery-capabilities')

trusted_discovery = load_trusted_discovery()

@runner.test('issues capability for supported rendered resolution')
def issues_capability_for_supported_rendered_resolution():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    capabilities = trusted_discovery.issue_capabilities(
        ['install-routeros-update'],
        lambda: 'test-token',
        registry,
        200,
    )

    testlib.assert_same(
        {
            'install-routeros-update': 'test-token',
        },
        capabilities,
    )

@runner.test('consumes capability only once')
def consumes_capability_only_once():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

    testlib.assert_same(
        'noc-trusted-action-test',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

    testlib.assert_same(
        None,
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

@runner.test('consumes capability atomically across threads')
def consumes_capability_atomically_across_threads():
    results = []
    errors = []

    class SlowCapabilities(dict):
        def get(self, token):
            capability = dict.get(
                self,
                token,
            )

            if capability is not None:
                time.sleep(0.05)

            return capability

    registry = registered_capability_registry(
        trusted_discovery,
    )

    registry.capabilities = SlowCapabilities(
        registry.capabilities
    )

    def consume():
        try:
            results.append(
                registry.consume(
                    'install-routeros-update',
                    'test-token',
                )
            )
        except Exception as error:
            errors.append(
                error.__class__.__name__
            )

    threads = [
        threading.Thread(target=consume),
        threading.Thread(target=consume),
    ]

    for thread in threads:
        thread.start()

    for thread in threads:
        thread.join()

    testlib.assert_same([], errors)
    testlib.assert_same(2, len(results))
    testlib.assert_same(
        1,
        results.count('noc-trusted-action-test'),
    )
    testlib.assert_same(
        1,
        results.count(None),
    )

@runner.test('registers capability under registry lock')
def registers_capability_under_registry_lock():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registered = threading.Event()
    finished = threading.Event()

    def register():
        registered.set()

        registry.register(
            'install-routeros-update',
            'test-token',
            'noc-trusted-action-test',
            200,
        )

        finished.set()

    registry.lock.acquire()

    try:
        thread = threading.Thread(
            target=register,
        )

        thread.start()

        registered.wait(
            1,
        )

        testlib.assert_false(
            finished.is_set()
        )
    finally:
        registry.lock.release()

    thread.join()

    testlib.assert_true(
        finished.is_set()
    )

    testlib.assert_same(
        'noc-trusted-action-test',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

@runner.test('does not consume capability for wrong resolution')
def does_not_consume_capability_for_wrong_resolution():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        200,
    )

    testlib.assert_same(
        None,
        registry.consume(
            'different-resolution',
            'test-token',
        ),
    )

    testlib.assert_same(
        'noc-trusted-action-test',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

@runner.test('rejects expired capability')
def rejects_expired_capability():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'test-token',
        'noc-trusted-action-test',
        110,
    )

    testlib.assert_same(
        'noc-trusted-action-test',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

    registry.register(
        'install-routeros-update',
        'expired-token',
        'noc-trusted-action-test',
        90,
    )

    testlib.assert_same(
        None,
        registry.consume(
            'install-routeros-update',
            'expired-token',
        ),
    )

@runner.test('removes expired capability when consumed')
def removes_expired_capability_when_consumed():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    registry.register(
        'install-routeros-update',
        'expired-token',
        'noc-trusted-action-test',
        90,
    )

    testlib.assert_same(
        None,
        registry.consume(
            'install-routeros-update',
            'expired-token',
        ),
    )

    testlib.assert_same(
        None,
        registry.consume(
            'install-routeros-update',
            'expired-token',
        ),
    )

@runner.test('generates cryptographically random capability token')
def generates_cryptographically_random_capability_token():
    tokens = iter([
        b'\x01\x02\x03\x04',
    ])

    def token_bytes(length):
        testlib.assert_same(32, length)
        return next(tokens)

    testlib.assert_same(
        '01020304',
        trusted_discovery.generate_capability_token(
            token_bytes,
        ),
    )

@runner.test('creates capability token')
def creates_capability_token():
    token = trusted_discovery.create_capability_token()

    testlib.assert_same(
        64,
        len(token),
    )

@runner.test('calculates capability expiry')
def calculates_capability_expiry():
    testlib.assert_same(
        130,
        trusted_discovery.capability_expiry(
            lambda: 100,
            30,
        ),
    )

@runner.test('registers issued capability')
def registers_issued_capability():
    registry = trusted_discovery.CapabilityRegistry(
        lambda: 100,
    )

    capabilities = trusted_discovery.issue_capabilities(
        ['install-routeros-update'],
        lambda: 'test-token',
        registry,
        200,
    )

    testlib.assert_same(
        {
            'install-routeros-update': 'test-token',
        },
        capabilities,
    )

    testlib.assert_same(
        'noc-install-routeros-update',
        registry.consume(
            'install-routeros-update',
            'test-token',
        ),
    )

@runner.test('registering capability prunes expired capabilities')
def registering_capability_prunes_expired_capabilities():
    now = [100]

    registry = trusted_discovery.CapabilityRegistry(
        lambda: now[0],
    )

    registry.register(
        'install-routeros-update',
        'expired-token',
        'noc-trusted-action-test',
        150,
    )

    now[0] = 200

    registry.register(
        'install-routeros-update',
        'current-token',
        'noc-trusted-action-test',
        300,
    )

    testlib.assert_same(
        [
            'current-token',
        ],
        sorted(
            registry.capabilities.keys()
        ),
    )

runner.finish()
