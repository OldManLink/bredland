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
from trusted_discovery_testlib import (load_trusted_discovery, fixture_html)

runner = TestSuiteRunner('trusted-discovery-resolutions')
trusted_discovery = load_trusted_discovery()

@runner.test('maps supported resolution to RouterOS script')
def supported_resolution_maps_to_routeros_script():
    testlib.assert_same(
        'noc-install-routeros-update',
        trusted_discovery.routeros_script_for_resolution(
            'install-routeros-update',
        ),
    )

@runner.test('maps supported resolution to RouterBoot script')
def supported_resolution_maps_to_routerboot_script():
    testlib.assert_same(
        'noc-install-routerboot-update',
        trusted_discovery.routeros_script_for_resolution(
            'install-routerboot-update',
        ),
    )

@runner.test('maps unsupported resolution to nothing')
def unsupported_resolution_maps_to_nothing():
    testlib.assert_same(
        None,
        trusted_discovery.routeros_script_for_resolution(
            'launch-missiles',
        ),
    )

@runner.test('discovers rendered resolution from NOC HTML')
def discovers_rendered_resolution_from_noc_html():
    html = fixture_html('noc-with-resolution.html')
    testlib.assert_same(
        ['install-routeros-update'],
        trusted_discovery.resolutions_from_noc_html(html),
    )


@runner.test('discovers no resolution from NOC HTML without one')
def discovers_no_resolution_from_noc_html():
    html = fixture_html('noc-without-resolution.html')
    testlib.assert_same(
        [],
        trusted_discovery.resolutions_from_noc_html(html),
    )

@runner.test('keeps only supported rendered resolutions')
def keeps_only_supported_rendered_resolutions():
    testlib.assert_same(
        ['install-routeros-update'],
        trusted_discovery.supported_rendered_resolutions(
            [
                'install-routeros-update',
                'future-resolution',
            ]
        ),
    )

@runner.test('fetches current NOC HTML')
def fetches_current_noc_html():
    requested_urls = []

    class Response:
        def read(self):
            return b'<html>current NOC</html>'

    def open_url(url):
        requested_urls.append(url)
        return Response()

    html = trusted_discovery.fetch_noc_html(
        'https://noc.arcanel.se',
        open_url,
    )

    testlib.assert_same(
        ['https://noc.arcanel.se/'],
        requested_urls,
    )

    testlib.assert_same(
        '<html>current NOC</html>',
        html,
    )

@runner.test('discovers current supported rendered resolutions')
def discovers_current_supported_rendered_resolutions():
    requested_urls = []

    def open_url(url):
        requested_urls.append(
            url
        )

        class Response:
            def read(self):
                return (
                    b'<div data-resolution="install-routeros-update"></div>'
                    b'<div data-resolution="future-resolution"></div>'
                )

        return Response()

    resolutions = (
        trusted_discovery.current_supported_resolutions(
            'https://noc.arcanel.se',
            open_url,
        )
    )

    testlib.assert_same(
        [
            'https://noc.arcanel.se/',
        ],
        requested_urls,
    )

    testlib.assert_same(
        [
            'install-routeros-update',
        ],
        resolutions,
    )

runner.finish()
