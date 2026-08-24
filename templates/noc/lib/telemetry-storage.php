<?php

class TelemetryStorage {
    private $dataDir;

    public function __construct($dataDir) {
        $this->dataDir = $dataDir;
    }

    public function append($host, $date, $record) {
        $this->ensure_data_dir();

        $file = $this->daily_jsonl_filename(
            $host,
            $date
        );

        $line = json_encode($record, JSON_UNESCAPED_SLASHES) . "\n";

        if (file_put_contents(
            $file,
            $line,
            FILE_APPEND | LOCK_EX
        ) === false) {
            throw new RuntimeException(
                'failed to append record: ' . $file
            );
        }
    }

    private function daily_jsonl_filename($host, $date) {
        $safeHost = preg_replace(
            '/[^a-zA-Z0-9_-]/',
            '_',
            $host
        );

        return rtrim($this->dataDir, '/') .
            '/' . $safeHost .
            '-' . $date .
            '.jsonl';
    }

    private function ensure_data_dir() {
        if (!is_dir($this->dataDir)) {
            mkdir(
                $this->dataDir,
                0700,
                true
            );
        }
    }
}