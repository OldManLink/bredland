<?php
require_once __DIR__ . '/compiler/client.php';

class ClientLoader {
    public static function load($clients_dir, $schemas_dir, $heartbeats_dir) {
        $clients = array();

        foreach (glob($clients_dir . '/*.json') as $client_file) {
            $client = self::load_client(
                $client_file,
                $schemas_dir,
                $heartbeats_dir
            );

            if ($client !== null) {
                $clients[] = $client;
            }
        }

        usort($clients, array('ClientLoader', 'compare_order'));

        return $clients;
    }

    private static function load_client($client_file, $schemas_dir, $heartbeats_dir) {
        $definition = self::read_json($client_file);

        if ($definition === null ||
            !isset($definition['host']) ||
            !is_string($definition['host']) ||
            $definition['host'] === '') {
            return null;
        }

        $host = $definition['host'];
        $schema = self::read_json($schemas_dir . '/' . $host . '.json');

        if ($schema === null) {
            return null;
        }

        $result = Client::compile(
            $definition,
            $schema,
            $client_file
        );

        if (!$result->isSuccess()) {
            return null;
        }

        $heartbeat = self::read_latest_heartbeat(
            $heartbeats_dir,
            $host
        );

        if ($heartbeat === null) {
            return null;
        }

        $client = $result->value();
        $client->render($heartbeat);

        return $client;
    }

    private static function read_json($file) {
        if (!is_file($file)) {
            return null;
        }

        $json = file_get_contents($file);

        if ($json === false) {
            return null;
        }

        $value = json_decode($json, true);

        return is_array($value) ? $value : null;
    }

    private static function read_latest_heartbeat($heartbeats_dir, $host) {
        $files = glob($heartbeats_dir . '/' . $host . '-*.jsonl');

        if (empty($files)) {
            return null;
        }

        rsort($files, SORT_STRING);

        $lines = file(
            $files[0],
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if ($lines === false || empty($lines)) {
            return null;
        }

        $heartbeat = json_decode(
            $lines[count($lines) - 1],
            true
        );

        return is_array($heartbeat) ? $heartbeat : null;
    }

    public static function compare_order($a, $b) {
        $a_order = $a->order();
        $b_order = $b->order();

        if ($a_order === $b_order) {
            return 0;
        }

        return $a_order < $b_order ? -1 : 1;
    }
}
