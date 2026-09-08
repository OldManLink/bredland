import importlib.util
import os
import subprocess
import tempfile
import sys

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
