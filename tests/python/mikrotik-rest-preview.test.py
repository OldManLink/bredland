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

def start_server(*args):
    process = subprocess.Popen(
        [
            sys.executable,
            'scripts/in-container/mikrotik-rest-preview.py',
        ] + list(args),
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


def script_request(
        script='noc-trusted-action-probe',
        path='/rest/system/script/run',
):
    return urllib.request.Request(
        'http://127.0.0.1:8082' + path,
        data=json.dumps({
            '.id': script,
        }).encode('utf-8'),
        headers={
            'Content-Type': 'application/json',
        },
        method='POST',
        )

def staged_request():
    return urllib.request.Request(
        'http://127.0.0.1:8082/rest/execute',
        data=json.dumps({
            'script': ':put [/system script run noc-routeros-staged]',
            'as-string': '',
        }).encode('utf-8'),
        headers={
            'Content-Type': 'application/json',
        },
        method='POST',
    )

def assert_script_shuts_down(script):
    process = start_server()

    try:
        response = urllib.request.urlopen(
            script_request(
                script=script,
            ),
            timeout=1,
        )

        testlib.assert_same(
            200,
            response.status,
        )

        deadline = time.time() + 1

        while (
                process.poll() is None
                and time.time() < deadline
        ):
            time.sleep(0.01)

        testlib.assert_true(
            process.poll() is not None,
            'Mock MikroTik should have shut down',
            )
    finally:
        if process.poll() is None:
            stop_server(
                process
            )
        elif process.stdout is not None:
            process.stdout.close()

@runner.test('accepts trusted action test script')
def accepts_trusted_action_test_script():
    process = start_server()

    try:
        response = urllib.request.urlopen(
            script_request(),
            timeout=1,
        )

        testlib.assert_same(
            200,
            response.status,
        )
    finally:
        stop_server(process)


@runner.test('RouterOS download changes staged predicate to true')
def routeros_download_changes_staged_predicate_to_true():
    process = start_server()

    try:
        response = urllib.request.urlopen(
            staged_request(),
            timeout=1,
        )

        before = json.loads(
            response.read().decode('utf-8')
        )

        testlib.assert_same(
            {
                'ret': 'false',
            },
            before,
        )

        urllib.request.urlopen(
            script_request(
                script='noc-download-routeros-update',
            ),
            timeout=1,
        )

        response = urllib.request.urlopen(
            staged_request(),
            timeout=1,
        )

        after = json.loads(
            response.read().decode('utf-8')
        )

        testlib.assert_same(
            {
                'ret': 'true',
            },
            after,
        )
    finally:
        stop_server(process)

@runner.test('rejects unknown script')
def rejects_unknown_script():
    process = start_server()

    try:
        testlib.assert_http_error(
            400,
            lambda: urllib.request.urlopen(
                script_request(
                    script='something-else',
                ),
                timeout=1,
            ),
            'Expected mock MikroTik server to reject unknown script',
        )
    finally:
        stop_server(process)

@runner.test('rejects wrong path')
def rejects_wrong_path():
    process = start_server()

    try:
        testlib.assert_http_error(
            404,
            lambda: urllib.request.urlopen(
                script_request(
                    script='noc-trusted-action-probe',
                    path='/not-routeros',
                ),
                timeout=1,
            ),
            'Expected mock MikroTik server to reject wrong path',
        )
    finally:
        stop_server(process)

@runner.test('defaults to never shutting down')
def defaults_to_never_shutting_down():
    process = start_server()

    try:
        urllib.request.urlopen(
            script_request(
                script='noc-trusted-action-probe',
            ),
            timeout=1,
        )

        time.sleep(0.1)

        testlib.assert_same(
            None,
            process.poll(),
        )
    finally:
        stop_server(process)

@runner.test('RouterOS update shuts down router')
def routeros_update_shuts_down_router():
    assert_script_shuts_down(
        'noc-install-routeros-update'
    )


@runner.test('RouterBOOT update shuts down router')
def routerboot_update_shuts_down_router():
    assert_script_shuts_down(
        'noc-install-routerboot-update'
    )

runner.finish()