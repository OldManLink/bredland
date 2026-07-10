<?php
// BRD-003: Generic NOC telemetry endpoint.
//
// PHP 5.5 compatible.
// This file is a template.
// Deployment-specific values are injected outside version control.

$base_dir = dirname($_SERVER['SCRIPT_FILENAME']);

require_once '__TELEMETRY_CONFIG_FILE__';
require_once "$base_dir/lib/compatibility.php";
require_once "$base_dir/lib/auth.php";
require_once "$base_dir/lib/record.php";
require_once "$base_dir/lib/storage.php";

function respond($status, $message)
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message . "\n";
    exit;
}

function param($name)
{
    if (!isset($_POST[$name]) || $_POST[$name] === '') {
        respond(400, 'missing parameter: ' . $name);
    }
    return (string) $_POST[$name];
}

if (!isset($HOST_TOKENS) || !isset($DATA_DIR)) respond(500, 'server configuration error');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, 'method not allowed');

$host = param('host');

if (!authenticate($host, param('token'), $HOST_TOKENS)) {
    respond(403, 'forbidden');
}

try {
    $selected_fields = select_fields(param('fields'), $_POST);

    $schema = load_record_schema("$base_dir/schemas", $host);

    $source = array_merge(
        array(
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'uptime' => param('uptime'),
            'remote_addr' => isset($_SERVER['REMOTE_ADDR'])
                ? $_SERVER['REMOTE_ADDR']
                : '',
        ),
        $selected_fields
    );

    $record = build_record($schema, $source);
} catch (InvalidArgumentException $e) {
    respond(400, $e->getMessage());
}

ensure_data_dir($DATA_DIR);

$data_file = daily_jsonl_filename($DATA_DIR, $host, gmdate('Y-m-d'));
append_record($data_file, $record);

respond(200, 'ok');