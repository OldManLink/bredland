<?php
// BRD-005: Network Operations Centre template.
require_once '__TELEMETRY_CONFIG_FILE__';

$base_dir = dirname($_SERVER['SCRIPT_FILENAME']);

require_once "$base_dir/lib/client.php";
require_once "$base_dir/lib/dashboard.php";
require_once "$base_dir/lib/noc.php";
require_once "$base_dir/lib/page-head.php";

$clients = load_clients("$base_dir/clients", $DATA_DIR);
$dashboard = new Dashboard(1, $clients, "$base_dir/lib/views/dashboard.php");
$noc = new Noc($dashboard);
echo $noc->render();
?>