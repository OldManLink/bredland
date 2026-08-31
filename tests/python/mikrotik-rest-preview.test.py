import json
import os
import subprocess
import sys
import time
import urllib.error
import urllib.request

sys.path.insert(
    0,
    os.path.join(
        os.path.dirname(__file__),
        'lib',
    ),
)

from test_suite_runner import TestSuiteRunner
import testlib


runner = TestSuiteRunner('mikrotik-rest-preview')


def start_server():
    process = subprocess.Popen(
        [
            sys.executable,
            'scripts/in-container/mikrotik-rest-preview.py',
        ],
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
    )

    time.sleep(0.2)

    if process.poll() is not None:
        output = process.stdout.read().decode('utf-8')

        testlib.fail(
            'Mock MikroTik server exited early:\n' + output
        )

    return process


def stop_server(process):
    process.terminate()
    process.wait()

    if process.stdout is not None:
        process.stdout.close()


@runner.test('accepts trusted action test script')
def accepts_trusted_action_test_script():
    process = start_server()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:8082/rest/system/script/run',
            data=json.dumps({
                '.id': 'noc-trusted-action-test',
            }).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
            },
            method='POST',
        )

        response = urllib.request.urlopen(
            request,
            timeout=1,
        )

        testlib.assert_same(
            200,
            response.status,
        )
    finally:
        stop_server(process)


@runner.test('rejects unknown script')
def rejects_unknown_script():
    process = start_server()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:8082/rest/system/script/run',
            data=json.dumps({
                '.id': 'something-else',
            }).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(
                request,
                timeout=1,
            )

            testlib.fail(
                'Expected mock MikroTik server to reject unknown script'
            )
        except urllib.error.HTTPError as error:
            testlib.assert_same(
                400,
                error.code,
            )
    finally:
        stop_server(process)

@runner.test('rejects wrong path')
def rejects_wrong_path():
    process = start_server()

    try:
        request = urllib.request.Request(
            'http://127.0.0.1:8082/not-routeros',
            data=json.dumps({
                '.id': 'noc-trusted-action-test',
            }).encode('utf-8'),
            headers={
                'Content-Type': 'application/json',
            },
            method='POST',
        )

        try:
            urllib.request.urlopen(
                request,
                timeout=1,
            )

            testlib.fail(
                'Expected mock MikroTik server to reject wrong path'
            )
        except urllib.error.HTTPError as error:
            testlib.assert_same(
                404,
                error.code,
            )
    finally:
        stop_server(process)

runner.finish()