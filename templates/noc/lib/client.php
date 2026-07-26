<?php
require_once __DIR__ . '/telemetry.php';
require_once __DIR__ . '/storage.php';

function known_value_types()
{
    return array('integer', 'float', 'boolean', 'string');
}

function is_known_value_type($value_type)
{
    return in_array($value_type, known_value_types(), true);
}

function load_clients($clients_dir, $data_dir) {
    $client_files = glob($clients_dir . '/*.json');
    $clients = array();

    foreach ($client_files as $client_file) {
        $client = read_client_file($client_file);

        if ($client === null) {
            continue;
        }

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

function read_client_file($client_file) {
    $contents = file_get_contents($client_file);

    if ($contents === false) {
        error_log("Unable to read client definition: " . $client_file);
        return null;
    }

    $client = json_decode($contents, true);

    if ($client === null || json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid client definition JSON: " . $client_file);
        return null;
    }

    return $client;
}

function compare_client_order($a, $b) {
    return $a['order'] - $b['order'];
}

function display_client_field($client, $field) {
    if (!isset($field['value_type']) || !is_known_value_type($field['value_type'])) {
        return 'unavailable';
    }

    $value = heartbeat_field($client['heartbeat'], $field['field'], 'unavailable');

    if ($value === 'unavailable') {
        return $value;
    }

    if (!value_matches_type($value, $field['value_type'])) {
        return 'unavailable';
    }

    if (isset($field['format'])) {
        $value = call_user_func($field['format'], $value);
    }

    return $value;
}

function value_matches_type($value, $value_type) {
    if ($value_type === 'integer') {
        return is_int($value);
    }

    if ($value_type === 'float') {
        return is_float($value);
    }

    if ($value_type === 'boolean') {
        return is_bool($value);
    }

    if ($value_type === 'string') {
        return is_string($value);
    }

    return false;
}
