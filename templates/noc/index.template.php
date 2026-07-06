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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#ffffff">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icons/favicon-16x16.png">
    <link rel="stylesheet" href="static/style.css?v=__STATIC_VERSION__">
    <script src="static/dashboard.js?v=__STATIC_VERSION__"></script>
    <title>Network Operations Centre</title>
</head>
<body>
    <div class="dashboard">
        <div class="cards-row">
            <div class="card-slot">
                <div class="card-container">
                    <div class="card <?= heartbeat_health_colour($ages['mikrotik']) ?>">
                        <h2>
                            <span class="led <?= heartbeat_health_colour($ages['mikrotik']) ?>"></span>
                            MikroTik
                        </h2>

                        <p>Last heartbeat: <?= htmlspecialchars(format_heartbeat_age($ages['mikrotik']), ENT_QUOTES, 'UTF-8') ?></p>
                        <p>Uptime: <?= htmlspecialchars(heartbeat_field($heartbeats['mikrotik'], 'uptime', 'unavailable'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p>Free memory: <?= htmlspecialchars(heartbeat_field($heartbeats['mikrotik'], 'free_memory', 'unavailable'), ENT_QUOTES, 'UTF-8') ?></p>
                        <button class="drawer-handle" type="button" data-telemetry-toggle="mikrotik">=</button>
                    </div>
                </div>
                <template id="mikrotik-telemetry-template">
                    <pre class="telemetry"><?= htmlspecialchars(latest_jsonl_line($mikrotik_file), ENT_QUOTES, 'UTF-8') ?></pre>
                </template>
            </div>

            <div class="card-slot">
                <div class="card-container">
                    <div class="card <?= heartbeat_health_colour($ages['bredland']) ?>">
                        <h2>
                            <span class="led <?= heartbeat_health_colour($ages['bredland']) ?>"></span>
                            Bredland
                        </h2>

                        <p>Last heartbeat: <?= htmlspecialchars(format_heartbeat_age($ages['bredland']), ENT_QUOTES, 'UTF-8') ?></p>
                        <p>Uptime: <?= htmlspecialchars(heartbeat_field($heartbeats['bredland'], 'uptime', 'unavailable'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p>Free memory: <?= htmlspecialchars(heartbeat_field($heartbeats['bredland'], 'free_memory', 'unavailable'), ENT_QUOTES, 'UTF-8') ?></p>
                        <button class="drawer-handle" type="button" data-telemetry-toggle="bredland">=</button>
                    </div>
                </div>
                <template id="bredland-telemetry-template">
                    <pre class="telemetry"><?= htmlspecialchars(latest_jsonl_line($bredland_file), ENT_QUOTES, 'UTF-8') ?></pre>
                </template>
            </div>
        </div>
    </div>
</body>
</html>
