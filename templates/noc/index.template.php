<?php
// BRD-005: Network Operations Centre template.
require_once '__TELEMETRY_CONFIG_FILE__';

$base_dir = dirname($_SERVER['SCRIPT_FILENAME']);

require_once "$base_dir/lib/client-loader.php";
require_once "$base_dir/lib/cards-row.php";
require_once "$base_dir/lib/dashboard.php";
require_once "$base_dir/lib/noc.php";

$clients = ClientLoader::load(
    "$base_dir/clients",
    "$base_dir/schemas",
    $DATA_DIR
);

$cards_row = new CardsRow(2, $clients);
$noc = new Noc(new Dashboard(1, $cards_row));
echo $noc->render();
?>