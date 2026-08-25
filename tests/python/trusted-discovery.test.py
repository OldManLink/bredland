import importlib.util
import os
import subprocess
import sys
import tempfile
import threading
import urllib.request
import urllib.error

sys.path.insert(
    0,
    os.path.join(
        os.path.dirname(__file__),
        'lib',
    ),
)

from test_suite_runner import TestSuiteRunner
from testlib import assert_same


runner = TestSuiteRunner('trusted-discovery')


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

        with open(secrets, 'w'):
            pass

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

        spec = importlib.util.spec_from_file_location(
            'trusted_discovery',
            rendered,
        )

        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)

        return module


trusted_discovery = load_trusted_discovery()

def discovery_response_is_rendered():
    assert_same(
        '{"assets":{"script":"https://bredland.example/opaque-script",'
        '"stylesheet":"https://bredland.example/opaque-style"}}',
        trusted_discovery.render_discovery_response(
            'https://bredland.example/opaque-script',
            'https://bredland.example/opaque-style',
        ),
    )

def discovery_endpoint_returns_json():
    create_server = getattr(
        trusted_discovery,
        'create_server',
        None,
    )

    assert_same(
        True,
        callable(create_server),
        'Expected trusted discovery to provide create_server()',
    )

    server = create_server(
        '127.0.0.1',
        0,
        'https://bredland.example/opaque-script',
        'https://bredland.example/opaque-style',
        'https://noc.arcanel.se',
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/probe'.format(
                server.server_port,
            ),
        )

        body = response.read().decode('utf-8')

        assert_same(200, response.status)
        assert_same(
            'application/json',
            response.headers.get_content_type(),
        )
        assert_same(
            '{"assets":{"script":"https://bredland.example/opaque-script",'
            '"stylesheet":"https://bredland.example/opaque-style"}}',
            body,
        )
    finally:
        thread.join()
        server.server_close()

def discovery_endpoint_only_serves_probe_path():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example/opaque-script',
        'https://bredland.example/opaque-style',
        'https://noc.arcanel.se',
    )

    thread = threading.Thread(
        target=server.serve_forever,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/probe'.format(
                server.server_port,
            ),
        )

        assert_same(200, response.status)

        try:
            urllib.request.urlopen(
                'http://127.0.0.1:{}/anything'.format(
                    server.server_port,
                ),
            )
        except urllib.error.HTTPError as error:
            assert_same(404, error.code)
        else:
            assert_same(
                True,
                False,
                'Expected unrelated path to return 404',
            )
    finally:
        server.shutdown()
        thread.join()
        server.server_close()


def discovery_endpoint_allows_noc_origin():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example/opaque-script',
        'https://bredland.example/opaque-style',
        'https://noc.arcanel.se',
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/probe'.format(
                server.server_port,
            ),
        )

        assert_same(
            'https://noc.arcanel.se',
            response.headers.get(
                'Access-Control-Allow-Origin',
            ),
        )
    finally:
        thread.join()
        server.server_close()


runner.test(
    'renders the trusted discovery response',
    discovery_response_is_rendered,
)

runner.test(
    'serves the trusted discovery response over HTTP',
    discovery_endpoint_returns_json,
)

runner.test(
    'serves discovery only on the probe path',
    discovery_endpoint_only_serves_probe_path,
)

runner.test(
    'allows the NOC origin to read discovery',
    discovery_endpoint_allows_noc_origin,
)

runner.finish()
