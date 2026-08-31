#!/usr/bin/env python3

import importlib.util
import os
import time
import urllib.request


REPO_ROOT = '/app'
BUILD_DIR = os.path.join(
    REPO_ROOT,
    'build',
    'rendered-noc',
)

TRUSTED_DISCOVERY_FILE = os.path.join(
    BUILD_DIR,
    'trusted_discovery.py',
)

TRUSTED_SCRIPT_FILE = os.path.join(
    REPO_ROOT,
    'templates',
    'bredland',
    'static',
    'trusted.js',
)

TRUSTED_STYLESHEET_FILE = os.path.join(
    REPO_ROOT,
    'templates',
    'bredland',
    'static',
    'trusted.css',
)


def load_trusted_discovery():
    spec = importlib.util.spec_from_file_location(
        'trusted_discovery_preview',
        TRUSTED_DISCOVERY_FILE,
    )

    module = importlib.util.module_from_spec(
        spec
    )

    spec.loader.exec_module(
        module
    )

    return module


def main():
    trusted_discovery = load_trusted_discovery()

    with open(TRUSTED_SCRIPT_FILE, 'r') as file:
        script_body = file.read()

    with open(TRUSTED_STYLESHEET_FILE, 'r') as file:
        stylesheet_body = file.read()

    capability_registry = (
        trusted_discovery.CapabilityRegistry(
            time.time,
        )
    )

    def load_noc_html():
        return trusted_discovery.fetch_noc_html(
            trusted_discovery.TRUSTED_ALLOWED_ORIGIN,
            urllib.request.urlopen,
        )

    def expires_at():
        return trusted_discovery.capability_expiry(
            time.time,
            60,
        )

    trusted_script_renderer = (
        trusted_discovery.create_trusted_script_renderer(
            trusted_discovery.TRUSTED_BASE_URL,
            load_noc_html,
            trusted_discovery.create_capability_token,
            capability_registry,
            expires_at,
        )
    )

    def preview_post(url, body):
        return trusted_discovery.post_json(
            url,
            body,
            {
                'Content-Type': 'application/json',
            },
            None,
            urllib.request.urlopen,
            )

    execute_action = trusted_discovery.create_routeros_action_executor(
        trusted_discovery.MIKROTIK_REST_BASE_URL,
        preview_post,
    )

    server = trusted_discovery.create_server(
        '0.0.0.0',
        8081,
        trusted_discovery.TRUSTED_BASE_URL,
        trusted_discovery.TRUSTED_ALLOWED_ORIGIN,
        trusted_discovery.TRUSTED_SCRIPT_PATH,
        script_body,
        trusted_discovery.TRUSTED_STYLESHEET_PATH,
        stylesheet_body,
        execute_action,
        capability_registry,
        trusted_script_renderer,
    )

    print(
        'Local trusted-discovery preview listening on '
        'http://0.0.0.0:8081',
        flush=True,
    )

    server.serve_forever()

if __name__ == '__main__':
    main()
