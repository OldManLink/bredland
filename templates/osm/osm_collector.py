import json
import shlex
import sys

def collect_osm_system_info(system_info):
    identity = system_info.get('identity')

    if (
            not isinstance(identity, dict)
            or 'fwVersion' not in identity
    ):
        raise RuntimeError(
            'missing required system info field: identity.fwVersion'
        )

    if 'rssi' not in identity:
        raise RuntimeError(
            'missing required system info field: identity.rssi'
        )

    miner = system_info.get('miner')

    if (
            not isinstance(miner, dict)
            or 'freeHeap' not in miner
    ):
        raise RuntimeError(
            'missing required system info field: miner.freeHeap'
        )

    if 'blkhits' not in miner:
        raise RuntimeError(
            'missing required system info field: miner.blkhits'
        )

    return {
        'version': identity['fwVersion'],
        'rssi': identity['rssi'],
        'free_heap': miner['freeHeap'],
        'block_hits': miner['blkhits'],
    }


def collect_osm_probe(probe):
    if 'hr' not in probe:
        raise RuntimeError(
            'missing required probe field: hr'
        )

    if 'ut' not in probe:
        raise RuntimeError(
            'missing required probe field: ut'
        )

    if 'ebd' not in probe:
        raise RuntimeError(
            'missing required probe field: ebd'
        )

    return {
        'hash_rate': probe['hr'],
        'uptime': probe['ut'],
        'best_difficulty_ever': probe['ebd'],
    }


def collect_osm_heartbeat(system_info, probe, ttl):
    heartbeat = collect_osm_system_info(system_info)

    heartbeat.update(
        collect_osm_probe(probe)
    )

    heartbeat['ttl'] = ttl

    return heartbeat

def read_json(path):
    with open(path, 'r', encoding='utf-8') as file:
        return json.load(file)


def main():
    system_info = read_json(sys.argv[1])
    probe = read_json(sys.argv[2])
    ttl = int(sys.argv[3])

    heartbeat = collect_osm_heartbeat(
        system_info,
        probe,
        ttl,
    )

    for key in (
            'uptime',
            'hash_rate',
            'version',
            'rssi',
            'free_heap',
            'best_difficulty_ever',
            'block_hits',
    ):
        print('{}={}'.format(
            key,
            shlex.quote(str(heartbeat[key])),
        ))


if __name__ == '__main__':
    main()
