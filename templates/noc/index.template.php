<?php
// BRD-005: Network Operations Centre template.
require_once '__TELEMETRY_CONFIG_FILE__';

$base_dir = dirname($_SERVER['SCRIPT_FILENAME']);

require_once "$base_dir/lib/client.php";
require_once "$base_dir/lib/dashboard.php";
require_once "$base_dir/lib/noc.php";

$clients = load_clients("$base_dir/clients", $DATA_DIR);
$dashboard = new Dashboard($clients, "$base_dir/lib/views/dashboard.php");
$noc = new Noc($dashboard);
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
    <? echo $noc->render(); ?>
</body>
</html>
