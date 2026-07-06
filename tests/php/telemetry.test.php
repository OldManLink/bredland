#!/usr/bin/env php
<?php

declare(strict_types=1);

require getenv('TEST_CONFIG');
require __DIR__ . '/lib/testlib.php';
require __DIR__ . '/../../templates/noc/lib/telemetry.php';

$tmpdir = sys_get_temp_dir() . '/bredland-telemetry-test-' . getmypid();
mkdir($tmpdir);

$two_line_file = $tmpdir . '/bredland-2026-07-01.jsonl';

file_put_contents(
    $two_line_file,
    "{\"host\":\"bredland\",\"status\":\"online\",\"seq\":1}\n" .
    "{\"host\":\"bredland\",\"status\":\"online\",\"seq\":2}\n" .
    "\n"
);

assertSame(
    "{\"host\":\"bredland\",\"status\":\"online\",\"seq\":2}",
    latest_jsonl_line($two_line_file)
);

$missing_file = $tmpdir . '/missing.jsonl';

assertSame(
    'unavailable',
    latest_jsonl_line($missing_file)
);

$empty_file = $tmpdir . '/empty.jsonl';
file_put_contents($empty_file, "");

assertSame(
    'empty',
    latest_jsonl_line($empty_file)
);

$sample_file = $tmpdir . '/sample.jsonl';

file_put_contents(
    $sample_file,
    "{\"ts\":\"2026-07-04T18:30:00Z\",\"host\":\"bredland\",\"uptime\":\"6d08:32:06\",\"free_memory\":123456}\n"
);

$heartbeat = heartbeat_from_jsonl($sample_file);

assertSame(
    [
        'ts' =>'2026-07-04T18:30:00Z',
        'host' => 'bredland',
        'uptime' => '6d08:32:06',
        'free_memory' => 123456,
    ],
    $heartbeat
);

assertSame(300, heartbeat_age_seconds($heartbeat, '2026-07-04T18:35:00Z'));
assertSame(1200, heartbeat_age_seconds($heartbeat, '2026-07-04T18:50:00Z'));
assertSame(null, heartbeat_age_seconds($heartbeat, null));

$sample_no_ts = $tmpdir . '/sample_no_ts.jsonl';

file_put_contents(
    $sample_no_ts,
    "{\"host\":\"bredland\",\"uptime\":\"6d08:32:06\",\"free_memory\":123456}\n"
);

$heartbeat_no_ts = heartbeat_from_jsonl($sample_no_ts);
assertSame(null, heartbeat_age_seconds($heartbeat_no_ts, '2026-07-04T18:50:00Z'));

unlink($two_line_file);
unlink($empty_file);
unlink($sample_file);
unlink($sample_no_ts);
rmdir($tmpdir);

assertSame('green', heartbeat_health_colour(0));
assertSame('green', heartbeat_health_colour(360));

assertSame('yellow', heartbeat_health_colour(361));
assertSame('yellow', heartbeat_health_colour(1200));

assertSame('red', heartbeat_health_colour(1201));
assertSame('red', heartbeat_health_colour(null));

assertSame('0s', format_duration_seconds(0));
assertSame('42s', format_duration_seconds(42));
assertSame('5m 0s', format_duration_seconds(300));
assertSame('1h 2m', format_duration_seconds(3720));
assertSame('2d 3h', format_duration_seconds(183600));
assertSame('36d 8h', format_duration_seconds(3141459));

assertSame('unavailable', format_heartbeat_age(null));
assertSame('42s ago', format_heartbeat_age(42));
assertSame('5m 0s ago', format_heartbeat_age(300));

assertSame(
    'unavailable',
    heartbeat_field(null, 'uptime', 'unavailable')
);

assertSame(
    '6d08:32:06',
    heartbeat_field(
        ['uptime' => '6d08:32:06'],
        'uptime',
        'unavailable'
    )
);