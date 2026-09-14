import contextlib
import importlib.util
import json
import os
import subprocess
import tempfile
import threading
import sys
import urllib.request
import testlib
from builtins import(callable, getattr, isinstance, object, open, staticmethod, str)


TEST_BASE_URL = 'https://bredland.example'
TEST_ALLOWED_ORIGIN = 'https://noc.arcanel.se'
TEST_SCRIPT_BODY = 'window.TEST_TRUSTED_ASSET_LOADED = true;'
TEST_STYLESHEET_BODY = 'html { outline: 1px solid; }'
TEST_RESOLUTION = 'install-routeros-update'
TEST_TOKEN = 'test-token'
TEST_SCRIPT_NAME = 'noc-trusted-action-test'
TEST_NOW = 100
TEST_CAPABILITY_EXPIRY = 200
TEST_ACTION_COOLDOWN = 30

_DEFAULT = object()


def load_trusted_discovery():
    with tempfile.TemporaryDirectory() as tmpdir:
        rendered = os.path.join(
            tmpdir,
            'trusted_discovery.py',
        )

        secrets = os.path.join(
            tmpdir,
            'secrets.env',
        )

        with open(secrets, 'w') as file:
            file.write(
                'BREDLAND_TRUSTED_BASE_URL=https://bredland.example:8081\n'
                'BREDLAND_TRUSTED_ALLOWED_ORIGIN=https://noc.arcanel.se\n'
                'MIKROTIK_REST_BASE_URL=https://mikrotik.example\n'
            )

        environment = os.environ.copy()
        environment['BREDLAND_SECRETS_FILE'] = secrets

        subprocess.run(
            [
                'scripts/render-template.sh',
                'templates/bredland/trusted_discovery.template.py',
                rendered,
            ],
            check=True,
            env=environment,
        )

        routeros_rest = os.path.join(
            tmpdir,
            'routeros_rest.py',
        )

        subprocess.run(
            [
                'cp',
                'templates/bredland/routeros_rest.py',
                routeros_rest,
            ],
            check=True,
        )

        spec = importlib.util.spec_from_file_location(
            'trusted_discovery',
            rendered,
        )

        module = importlib.util.module_from_spec(spec)

        sys.path.insert(
            0,
            tmpdir,
        )

        try:
            spec.loader.exec_module(module)
        finally:
            sys.path.remove(
                tmpdir
            )

        return module


def stub_routeros_action_dependencies(
        trusted_discovery,
):
    originals = {
        'load_credentials':
            trusted_discovery.load_routeros_rest_credentials,
        'create_tls_context':
            trusted_discovery.create_routeros_rest_tls_context,
        'create_poster':
            trusted_discovery.create_routeros_rest_poster,
        'create_executor':
            trusted_discovery.create_routeros_action_executor,
    }

    trusted_discovery.load_routeros_rest_credentials = (
        lambda credentials_file: {
            'username': 'test-user',
            'password': 'test-password',
        }
    )

    trusted_discovery.create_routeros_rest_tls_context = (
        lambda ca_file: 'test-routeros-tls-context'
    )

    trusted_discovery.create_routeros_rest_poster = (
        lambda credentials, context, open_request, post_json_function:
        'test-routeros-poster'
    )

    trusted_discovery.create_routeros_action_executor = (
        lambda base_url, post: (
            lambda script_name: True
        )
    )

    return originals


def restore_routeros_action_dependencies(
        trusted_discovery,
        originals,
):
    trusted_discovery.load_routeros_rest_credentials = (
        originals['load_credentials']
    )

    trusted_discovery.create_routeros_rest_tls_context = (
        originals['create_tls_context']
    )

    trusted_discovery.create_routeros_rest_poster = (
        originals['create_poster']
    )

    trusted_discovery.create_routeros_action_executor = (
        originals['create_executor']
    )


def registered_capability_registry(
        trusted_discovery,
        resolution=TEST_RESOLUTION,
        token=TEST_TOKEN,
        script_name=TEST_SCRIPT_NAME,
        now=TEST_NOW,
        expires_at=TEST_CAPABILITY_EXPIRY,
):
    now_function = (
        now
        if callable(now)
        else lambda: now
    )

    registry = trusted_discovery.CapabilityRegistry(
        now_function
    )

    registry.register(
        resolution,
        token,
        script_name,
        expires_at,
    )

    return registry


def create_test_server(
        trusted_discovery,
        action_executor=None,
        registry=None,
        script_renderer=None,
        action_validator=_DEFAULT,
        action_guard=_DEFAULT,
        action_hook=None,
        host='127.0.0.1',
        port=0,
        base_url=TEST_BASE_URL,
        allowed_origin=TEST_ALLOWED_ORIGIN,
        script_body=TEST_SCRIPT_BODY,
        stylesheet_body=TEST_STYLESHEET_BODY,
        asset_path_factory=None,
):
    if action_validator is _DEFAULT:
        action_validator = lambda resolution: True

    if action_guard is _DEFAULT:
        action_guard = trusted_discovery.ActionGuard(
            lambda: TEST_NOW,
            TEST_ACTION_COOLDOWN,
        )

    if asset_path_factory is None:
        asset_number = [0]

        def asset_path_factory():
            asset_number[0] += 1

            return '/test-asset-{}'.format(
                asset_number[0]
            )

    return trusted_discovery.create_server(
        host,
        port,
        base_url,
        allowed_origin,
        script_body,
        stylesheet_body,
        action_executor,
        registry,
        script_renderer,
        action_validator,
        action_guard,
        action_hook=action_hook,
        asset_path_factory=asset_path_factory,
    )

def probe(server):
    return urllib.request.urlopen(
        server_url(
            server,
            '/probe',
        )
    ).read().decode('utf-8')

@contextlib.contextmanager
def serving(server):
    thread = threading.Thread(
        target=lambda: server.serve_forever(
            poll_interval=0.01
        ),
    )

    thread.start()

    try:
        yield server
    finally:
        server.shutdown()
        thread.join()
        server.server_close()


def server_url(server, path):
    return 'http://127.0.0.1:{}{}'.format(
        server.server_port,
        path,
    )


def action_request(
        server,
        resolution=TEST_RESOLUTION,
        token=TEST_TOKEN,
        origin=TEST_ALLOWED_ORIGIN,
        payload=_DEFAULT,
        raw_body=_DEFAULT,
):
    if raw_body is _DEFAULT:
        if payload is _DEFAULT:
            payload = {
                'resolution': resolution,
                'token': token,
            }

        data = json.dumps(
            payload
        ).encode('utf-8')
    else:
        data = raw_body

    headers = {
        'Content-Type': 'application/json',
    }

    if origin is not None:
        headers['Origin'] = origin

    return urllib.request.Request(
        server_url(
            server,
            '/action',
        ),
        data=data,
        headers=headers,
        method='POST',
    )


def action_preflight_request(
        server,
        origin=TEST_ALLOWED_ORIGIN,
):
    return urllib.request.Request(
        server_url(
            server,
            '/action',
        ),
        headers={
            'Origin': origin,
            'Access-Control-Request-Method': 'POST',
            'Access-Control-Request-Headers': 'Content-Type',
        },
        method='OPTIONS',
    )


def fixture_path(name):
    return os.path.abspath(
        os.path.join(
            os.path.dirname(__file__),
            '..',
            'fixtures',
            'trusted-discovery',
            name,
        )
    )


def fixture_html(name):
    with open(
            fixture_path(name),
            'r',
    ) as fixture:
        return fixture.read()


def fixture_loader(name):
    return lambda: fixture_html(
        name
    )


@contextlib.contextmanager
def temporary_trusted_assets(
        trusted_discovery,
        script_body=TEST_SCRIPT_BODY,
        stylesheet_body=TEST_STYLESHEET_BODY,
):
    with tempfile.TemporaryDirectory() as tmpdir:
        script_file = os.path.join(
            tmpdir,
            'trusted.js',
        )

        stylesheet_file = os.path.join(
            tmpdir,
            'trusted.css',
        )

        with open(script_file, 'w') as file:
            file.write(
                script_body
            )

        with open(stylesheet_file, 'w') as file:
            file.write(
                stylesheet_body
            )

        original_script_file = (
            trusted_discovery.TRUSTED_SCRIPT_FILE
        )

        original_stylesheet_file = (
            trusted_discovery.TRUSTED_STYLESHEET_FILE
        )

        trusted_discovery.TRUSTED_SCRIPT_FILE = (
            script_file
        )

        trusted_discovery.TRUSTED_STYLESHEET_FILE = (
            stylesheet_file
        )

        try:
            yield
        finally:
            trusted_discovery.TRUSTED_SCRIPT_FILE = (
                original_script_file
            )

            trusted_discovery.TRUSTED_STYLESHEET_FILE = (
                original_stylesheet_file
            )


@contextlib.contextmanager
def passthrough_tls(trusted_discovery):
    class FakeContext:
        def load_cert_chain(self, certfile, keyfile):
            pass

        def wrap_socket(self, socket, server_side):
            return socket

    class FakeSsl:
        PROTOCOL_TLS_SERVER = 'tls-server'

        @staticmethod
        def SSLContext(protocol):
            return FakeContext()

    original_ssl = trusted_discovery.ssl
    trusted_discovery.ssl = FakeSsl

    try:
        yield
    finally:
        trusted_discovery.ssl = original_ssl


@contextlib.contextmanager
def stubbed_routeros_action_dependencies(
        trusted_discovery,
        getter_response=None,
):
    originals = stub_routeros_action_dependencies(
        trusted_discovery
    )

    original_getter = getattr(
        trusted_discovery,
        'create_routeros_rest_getter',
        None,
    )

    if getter_response is None:
        getter = lambda url: {
            'installed-version': '7.23.1',
            'latest-version': '7.24.1',
            'status': 'New version is available',
        }
    elif callable(getter_response):
        getter = getter_response
    else:
        getter = lambda url: getter_response

    if original_getter is not None:
        trusted_discovery.create_routeros_rest_getter = (
            lambda credentials, context, open_request, get_json_function:
            getter
        )

    try:
        yield
    finally:
        if original_getter is not None:
            trusted_discovery.create_routeros_rest_getter = (
                original_getter
            )

        restore_routeros_action_dependencies(
            trusted_discovery,
            originals,
        )


@contextlib.contextmanager
def temporary_resolutions_file(resolutions):
    with tempfile.NamedTemporaryFile(
            mode='w',
            suffix='.json',
            delete=False,
            encoding='utf-8',
    ) as file:
        path = file.name

        if isinstance(resolutions, str):
            file.write(
                resolutions
            )
        else:
            json.dump(
                resolutions,
                file,
            )

    try:
        yield path
    finally:
        if os.path.exists(path):
            os.remove(
                path
            )

@contextlib.contextmanager
def configured_server_wiring(
        trusted_discovery,
):
    wired = {}
    hook_calls = []

    class FakeServer:
        tls_context = None

    def create_server(
            host,
            port,
            base_url,
            allowed_origin,
            script_body,
            stylesheet_body,
            action_executor,
            capability_registry,
            trusted_script_renderer,
            action_validator,
            action_guard,
            action_hook=None,
            asset_path_factory=None,
    ):
        wired['executor'] = action_executor
        wired['validator'] = action_validator
        wired['action_hook'] = action_hook

        return FakeServer()

    def execute_configured_resolution_hook(
            path,
            resolution,
            hook_executor,
            logger,
    ):
        hook_calls.append(
            (
                path,
                resolution,
                hook_executor,
                logger,
            )
        )

        return True

    with testlib.patched_attribute(
            trusted_discovery,
            'create_server',
            create_server,
    ):
        with testlib.patched_attribute(
                trusted_discovery,
                'load_routeros_rest_credentials',
                lambda credentials_file: {
                    'username': 'test-user',
                    'password': 'test-password',
                },
        ):
            with testlib.patched_attribute(
                    trusted_discovery,
                    'create_routeros_rest_tls_context',
                    lambda ca_file: 'routeros-tls-context',
            ):
                with testlib.patched_attribute(
                        trusted_discovery,
                        'create_routeros_rest_poster',
                        lambda credentials, context, open_request,
                               post_json_function: 'routeros-poster',
                ):
                    with testlib.patched_attribute(
                            trusted_discovery,
                            'create_routeros_action_executor',
                            lambda base_url, post:
                            'routeros-action-executor',
                    ):
                        with testlib.patched_attribute(
                                trusted_discovery,
                                'create_routeros_rest_getter',
                                lambda credentials, context, open_request,
                                       get_json_function:
                                lambda url: {
                                    'installed-version': '7.23.1',
                                    'latest-version': '7.24.1',
                                    'status': 'New version is available',
                                },
                        ):
                            with testlib.patched_attribute(
                                    trusted_discovery,
                                    'execute_configured_resolution_hook',
                                    execute_configured_resolution_hook,
                            ):
                                yield wired, hook_calls

def recording_action_executor(result=True):
    calls = []

    def execute(script_name):
        calls.append(
            script_name
        )

        return result

    return calls, execute


def recording_action_hook(result=True):
    calls = []

    def execute(resolution):
        calls.append(
            resolution
        )

        return result

    return calls, execute
