<?php
// BRD-005: Networkk Operations Centre template.

$base_dir = dirname($_SERVER['SCRIPT_FILENAME']);

require '__TELEMETRY_CONFIG_FILE__';
require "$base_dir/lib/storage.php";
require "$base_dir/lib/telemetry.php";

$mikrotik_file = daily_jsonl_filename($DATA_DIR, 'mikrotik', gmdate('Y-m-d'));
$bredland_file = daily_jsonl_filename($DATA_DIR, 'bredland', gmdate('Y-m-d'));

$heartbeats = [
    'mikrotik' => heartbeat_from_jsonl($mikrotik_file),
    'bredland' => heartbeat_from_jsonl($bredland_file),
];

$now = gmdate('c');
$ages = [
    'mikrotik' => heartbeat_age_seconds($heartbeats['mikrotik'], $now),
    'bredland' => heartbeat_age_seconds($heartbeats['bredland'], $now),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="60">
    <link rel="stylesheet" href="static/style.css?v=__STATIC_VERSION__">
    <title>Network Operations Centre</title>
</head>
<body>
    <div class="card <?= heartbeat_health_colour($ages['mikrotik']) ?>">
        <h2>
            <span class="led <?= heartbeat_health_colour($ages['mikrotik']) ?>"></span>
            MikroTik
        </h2>

        <p>Last heartbeat: <?= format_duration_seconds($ages['mikrotik']) ?> ago</p>
        <p>Uptime: <?= htmlspecialchars($heartbeats['mikrotik']['uptime']) ?></p>
        <p>Free memory: <?= htmlspecialchars($heartbeats['mikrotik']['free_memory']) ?></p>
    </div>

    <div class="card <?= heartbeat_health_colour($ages['bredland']) ?>">
        <h2>
            <span class="led <?= heartbeat_health_colour($ages['bredland']) ?>"></span>
            Bredland
        </h2>

        <p>Last heartbeat: <?= format_duration_seconds($ages['bredland']) ?> ago</p>
        <p>Uptime: <?= htmlspecialchars($heartbeats['bredland']['uptime']) ?></p>
        <p>Free memory: <?= htmlspecialchars($heartbeats['bredland']['free_memory']) ?></p>
    </div>

    <h2>MikroTik</h2>
    <pre class="telemetry"><?= htmlspecialchars(
        latest_jsonl_line($mikrotik_file),
        ENT_QUOTES,
        'UTF-8'
    ) ?></pre>

    <h2>Bredland</h2>
    <pre class="telemetry"><?= htmlspecialchars(
        latest_jsonl_line($bredland_file),
        ENT_QUOTES,
        'UTF-8'
    ) ?></pre>
</body>
</html>
