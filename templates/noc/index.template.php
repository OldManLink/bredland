<?php
// BRD-005: Network Operations Centre template.
require_once '__TELEMETRY_CONFIG_FILE__';

$base_dir = dirname($_SERVER['SCRIPT_FILENAME']);

require_once "$base_dir/lib/client.php";
require_once "$base_dir/lib/cards-row.php";
require_once "$base_dir/lib/dashboard.php";
require_once "$base_dir/lib/noc.php";

$clients = load_clients("$base_dir/clients", $DATA_DIR);
$cards_row = new CardsRow(2, $clients);
$noc = new Noc(new Dashboard(1, $cards_row));
echo $noc->render();
?>