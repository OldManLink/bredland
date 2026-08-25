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

        with open(secrets, 'w') as file:
            file.write(
                'BREDLAND_TRUSTED_BASE_URL=https://bredland.example:8081\n'
                'BREDLAND_TRUSTED_ALLOWED_ORIGIN=https://noc.arcanel.se\n'
                'BREDLAND_TRUSTED_SCRIPT_PATH=/trusted-script-test\n'
                'BREDLAND_TRUSTED_STYLESHEET_PATH=/trusted-style-test\n'
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

    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TRUSTED_MODE = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
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
            '{"assets":{"script":"https://bredland.example/trusted-script-test",'
            '"stylesheet":"https://bredland.example/trusted-style-test"}}',
            body,
        )
    finally:
        thread.join()
        server.server_close()

def discovery_endpoint_only_serves_probe_path():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TRUSTED_MODE = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
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
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TRUSTED_MODE = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
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

def trusted_script_is_served():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TRUSTED_MODE = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/trusted-script-test'.format(
                server.server_port,
            ),
        )

        body = response.read().decode('utf-8')

        assert_same(200, response.status)
        assert_same(
            'application/javascript',
            response.headers.get_content_type(),
        )
        assert_same(
            'window.TRUSTED_MODE = true;',
            body,
        )
    finally:
        thread.join()
        server.server_close()

def trusted_stylesheet_is_served():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TRUSTED_MODE = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
    )

    thread = threading.Thread(
        target=server.handle_request,
    )
    thread.start()

    try:
        response = urllib.request.urlopen(
            'http://127.0.0.1:{}/trusted-style-test'.format(
                server.server_port,
            ),
        )

        body = response.read().decode('utf-8')

        assert_same(200, response.status)
        assert_same(
            'text/css',
            response.headers.get_content_type(),
        )
        assert_same(
            'html { outline: 1px solid; }',
            body,
        )
    finally:
        thread.join()
        server.server_close()

def discovery_urls_match_served_asset_paths():
    server = trusted_discovery.create_server(
        '127.0.0.1',
        0,
        'https://bredland.example',
        'https://noc.arcanel.se',
        '/trusted-script-test',
        'window.TRUSTED_MODE = true;',
        '/trusted-style-test',
        'html { outline: 1px solid; }',
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

        assert_same(
            '{"assets":{"script":"https://bredland.example/trusted-script-test",'
            '"stylesheet":"https://bredland.example/trusted-style-test"}}',
            body,
        )
    finally:
        thread.join()
        server.server_close()

def deployment_configuration_is_rendered():
    assert_same(
        'https://bredland.example:8081',
        getattr(
            trusted_discovery,
            'TRUSTED_BASE_URL',
            None,
        ),
    )

    assert_same(
        'https://noc.arcanel.se',
        getattr(
            trusted_discovery,
            'TRUSTED_ALLOWED_ORIGIN',
            None,
        ),
    )

    assert_same(
        '/trusted-script-test',
        getattr(
            trusted_discovery,
            'TRUSTED_SCRIPT_PATH',
            None,
        ),
    )

    assert_same(
        '/trusted-style-test',
        getattr(
            trusted_discovery,
            'TRUSTED_STYLESHEET_PATH',
            None,
        ),
    )


def configured_server_uses_rendered_configuration():
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
            file.write('window.TRUSTED_MODE = true;')

        with open(stylesheet_file, 'w') as file:
            file.write('html { outline: 1px solid; }')

        trusted_discovery.TRUSTED_SCRIPT_FILE = script_file
        trusted_discovery.TRUSTED_STYLESHEET_FILE = stylesheet_file

        server = trusted_discovery.create_configured_server(
            '127.0.0.1',
            0,
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

            assert_same(
                '{"assets":{"script":"https://bredland.example:8081/trusted-script-test",'
                '"stylesheet":"https://bredland.example:8081/trusted-style-test"}}',
                body,
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

def configured_server_loads_trusted_assets_from_files():
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
            file.write('window.TRUSTED_MODE = true;')

        with open(stylesheet_file, 'w') as file:
            file.write('html { outline: 1px solid; }')

        trusted_discovery.TRUSTED_SCRIPT_FILE = script_file
        trusted_discovery.TRUSTED_STYLESHEET_FILE = stylesheet_file

        server = trusted_discovery.create_configured_server(
            '127.0.0.1',
            0,
        )

        thread = threading.Thread(
            target=server.serve_forever,
        )
        thread.start()

        try:
            script_response = urllib.request.urlopen(
                'http://127.0.0.1:{}/trusted-script-test'.format(
                    server.server_port,
                ),
            )

            stylesheet_response = urllib.request.urlopen(
                'http://127.0.0.1:{}/trusted-style-test'.format(
                    server.server_port,
                ),
            )

            assert_same(
                'window.TRUSTED_MODE = true;',
                script_response.read().decode('utf-8'),
            )

            assert_same(
                'html { outline: 1px solid; }',
                stylesheet_response.read().decode('utf-8'),
            )
        finally:
            server.shutdown()
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

runner.test(
    'serves the configured trusted script',
    trusted_script_is_served,
)

runner.test(
    'serves the configured trusted stylesheet',
    trusted_stylesheet_is_served,
)

runner.test(
    'discovery advertises the served asset paths',
    discovery_urls_match_served_asset_paths,
)

runner.test(
    'uses rendered deployment configuration',
    deployment_configuration_is_rendered,
)

runner.test(
    'creates a server from rendered deployment configuration',
    configured_server_uses_rendered_configuration,
)

runner.test(
    'loads trusted assets from fixed local files',
    configured_server_loads_trusted_assets_from_files,
)

runner.finish()
