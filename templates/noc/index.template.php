<?php
// BRD-005: Network Operations Centre template.

$base_dir = dirname($_SERVER['SCRIPT_FILENAME']);

require '__TELEMETRY_CONFIG_FILE__';
require "$base_dir/lib/storage.php";
require "$base_dir/lib/telemetry.php";

$clients = load_clients("$base_dir/clients", $DATA_DIR);

function load_clients($clients_dir, $data_dir)
{
    $client_files = glob($clients_dir . '/*.json');
    $clients = [];

    foreach ($client_files as $client_file) {
        $client = json_decode(file_get_contents($client_file), true);
        $host = $client['host'];

        $heartbeat_file = daily_jsonl_filename($data_dir, $host, gmdate('Y-m-d'));
        $heartbeat = heartbeat_from_jsonl($heartbeat_file);
        $age = heartbeat_age_seconds($heartbeat, gmdate('c'));

        $client['heartbeat_file'] = $heartbeat_file;
        $client['heartbeat'] = $heartbeat;
        $client['age'] = $age;

        $clients[] = $client;
    }

    usort($clients, 'compare_client_order');

    return $clients;
}

function compare_client_order($a, $b)
{
    return $a['order'] - $b['order'];
}

function display_client_field($client, $field)
{
    $value = heartbeat_field($client['heartbeat'], $field['field'], 'unavailable');

    if ($value !== 'unavailable' && isset($field['format'])) {
        $value = call_user_func($field['format'], $value);
    }

    return $value;
}
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
    <div id="refresh-indicator">
        <div class="spinner"></div>
    </div>
    <div class="dashboard">
        <div class="cards-row">
            <?php foreach ($clients as $client): ?>
            <div class="card-slot">
                <div class="card-container">
                    <div class="card <?= heartbeat_health_colour($client['age']) ?>">
                        <h2>
                            <span class="led <?= heartbeat_health_colour($client['age']) ?>"></span>
                            <?= htmlspecialchars($client['title'], ENT_QUOTES, 'UTF-8') ?>
                        </h2>

                        <p>Last heartbeat: <?= htmlspecialchars(format_heartbeat_age($client['age']), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php foreach ($client['fields'] as $field): ?>
                        <p><?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars(display_client_field($client, $field), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endforeach; ?>
                        <button class="drawer-handle" type="button" data-telemetry-toggle="<?= htmlspecialchars($client['host'], ENT_QUOTES, 'UTF-8') ?>">=</button>
                    </div>
                </div>
                <template id="<?= htmlspecialchars($client['host'], ENT_QUOTES, 'UTF-8') ?>-telemetry-template">
                    <pre class="telemetry"><?= htmlspecialchars(latest_jsonl_line($client['heartbeat_file']), ENT_QUOTES, 'UTF-8') ?></pre>
                </template>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
