<?php
// BRD-005: Networkk Operations Centre template.

$base_dir = dirname($_SERVER['SCRIPT_FILENAME']);

require '__TELEMETRY_CONFIG_FILE__';
require "$base_dir/lib/storage.php";
require "$base_dir/lib/telemetry.php";

$mikrotik_file = daily_jsonl_filename($DATA_DIR, 'mikrotik', gmdate('Y-m-d'));
$bredland_file = daily_jsonl_filename($DATA_DIR, 'bredland', gmdate('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="60">
    <link rel="stylesheet" href="static/style.css?v=4">
    <title>Network Operations Centre</title>
</head>
<body>
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
