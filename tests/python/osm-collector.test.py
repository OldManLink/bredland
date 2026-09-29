import json
import os
import subprocess
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'lib'))
import testlib
from test_suite_runner import TestSuiteRunner

repo_root = os.path.dirname(os.path.dirname(os.path.dirname(__file__)))
osm_root = os.path.join(repo_root, 'templates', 'osm')
fixture_root = os.path.join(repo_root, 'tests', 'fixtures', 'osm')

sys.path.insert(0, osm_root)
import osm_collector

runner = TestSuiteRunner('osm-collector')


def read_fixture(name):
    with open(
            os.path.join(fixture_root, name),
            'r',
            encoding='utf-8',
    ) as file:
        return json.load(file)

@runner.test('CLI outputs heartbeat assignments')
def cli_outputs_heartbeat_assignments():
    system_info_path = os.path.join(
        fixture_root,
        'system-info.json',
    )
    probe_path = os.path.join(
        fixture_root,
        'probe.json',
    )

    output = subprocess.check_output(
        [
            sys.executable,
            os.path.join(
                osm_root,
                'osm_collector.py',
            ),
            system_info_path,
            probe_path,
            '600',
        ],
        text=True,
    )

    assignments = {}

    for line in output.strip().splitlines():
        key, value = line.split('=', 1)
        assignments[key] = value

    heartbeat = osm_collector.collect_osm_heartbeat(
        read_fixture('system-info.json'),
        read_fixture('probe.json'),
        600,
    )

    del heartbeat['ttl']

    expected = dict(
        (key, str(value))
        for key, value in heartbeat.items()
    )

    testlib.assert_same(
        expected,
        assignments,
    )

@runner.test('extracts heartbeat data from system info')
def extracts_heartbeat_data_from_system_info():
    system_info = read_fixture('system-info.json')

    heartbeat = osm_collector.collect_osm_system_info(
        system_info
    )

    testlib.assert_same(
        'v2.0.03',
        heartbeat['version'],
    )
    testlib.assert_same(
        -67,
        heartbeat['rssi'],
    )
    testlib.assert_same(
        54608,
        heartbeat['free_heap'],
    )
    testlib.assert_same(
        0,
        heartbeat['block_hits'],
    )


@runner.test('extracts heartbeat data from probe')
def extracts_heartbeat_data_from_probe():
    probe = read_fixture('probe.json')

    heartbeat = osm_collector.collect_osm_probe(
        probe
    )

    testlib.assert_same(
        1040183,
        heartbeat['hash_rate'],
    )
    testlib.assert_same(
        727990,
        heartbeat['uptime'],
    )
    testlib.assert_same(
        5306,
        heartbeat['best_difficulty_ever'],
    )


@runner.test('combines OSM data into one heartbeat')
def combines_osm_data_into_one_heartbeat():
    system_info = read_fixture('system-info.json')
    probe = read_fixture('probe.json')

    system_info_heartbeat = osm_collector.collect_osm_system_info(
        system_info
    )
    probe_heartbeat = osm_collector.collect_osm_probe(
        probe
    )

    expected = dict(system_info_heartbeat)
    expected.update(probe_heartbeat)
    expected['ttl'] = 600

    heartbeat = osm_collector.collect_osm_heartbeat(
        system_info,
        probe,
        600,
    )

    testlib.assert_same(
        expected,
        heartbeat,
    )


@runner.test('rejects probe with missing hash rate')
def rejects_probe_with_missing_hash_rate():
    probe = read_fixture('probe.json')
    del probe['hr']

    testlib.assert_throws(
        RuntimeError,
        'missing required probe field: hr',
        lambda: osm_collector.collect_osm_probe(probe),
    )


@runner.test('rejects probe with missing uptime')
def rejects_probe_with_missing_uptime():
    probe = read_fixture('probe.json')
    del probe['ut']

    testlib.assert_throws(
        RuntimeError,
        'missing required probe field: ut',
        lambda: osm_collector.collect_osm_probe(probe),
    )


@runner.test('rejects probe with missing best difficulty ever')
def rejects_probe_with_missing_best_difficulty_ever():
    probe = read_fixture('probe.json')
    del probe['ebd']

    testlib.assert_throws(
        RuntimeError,
        'missing required probe field: ebd',
        lambda: osm_collector.collect_osm_probe(probe),
    )


@runner.test('rejects system info with missing firmware version')
def rejects_system_info_with_missing_firmware_version():
    system_info = read_fixture('system-info.json')
    del system_info['identity']['fwVersion']

    testlib.assert_throws(
        RuntimeError,
        'missing required system info field: identity.fwVersion',
        lambda: osm_collector.collect_osm_system_info(system_info),
    )


@runner.test('rejects system info with missing rssi')
def rejects_system_info_with_missing_rssi():
    system_info = read_fixture('system-info.json')
    del system_info['identity']['rssi']

    testlib.assert_throws(
        RuntimeError,
        'missing required system info field: identity.rssi',
        lambda: osm_collector.collect_osm_system_info(system_info),
    )


@runner.test('rejects system info with missing free heap')
def rejects_system_info_with_missing_free_heap():
    system_info = read_fixture('system-info.json')
    del system_info['miner']['freeHeap']

    testlib.assert_throws(
        RuntimeError,
        'missing required system info field: miner.freeHeap',
        lambda: osm_collector.collect_osm_system_info(system_info),
    )


@runner.test('rejects system info with missing block hits')
def rejects_system_info_with_missing_block_hits():
    system_info = read_fixture('system-info.json')
    del system_info['miner']['blkhits']

    testlib.assert_throws(
        RuntimeError,
        'missing required system info field: miner.blkhits',
        lambda: osm_collector.collect_osm_system_info(system_info),
    )


runner.finish()
