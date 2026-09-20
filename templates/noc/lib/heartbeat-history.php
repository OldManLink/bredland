<?php

class HeartbeatHistory {
    private $dataDir;

    public function __construct($dataDir) {
        $this->dataDir = $dataDir;
    }

    public function latest($host, $count) {
        $files = array_reverse(
            $this->history_files($host)
        );

        $records = array();

        foreach ($files as $file) {
            $lines = array_reverse(
                $this->read_lines($file)
            );

            foreach ($lines as $line) {
                $records[] = json_decode($line, true);

                if (count($records) === $count) {
                    break 2;
                }
            }
        }

        return array_reverse($records);
    }

    public function range($host, $from, $to, $max) {
        $records = array();

        foreach ($this->history_files($host) as $file) {
            if (substr($file, -3) === '.gz') {
                $lines = array();

                exec(
                    'gzip -dc ' . escapeshellarg($file),
                    $lines
                );
            } else {
                $lines = file(
                    $file,
                    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
                );
            }

            foreach ($lines as $line) {
                $record = json_decode($line, true);

                if ($record['ts'] >= $from &&
                    $record['ts'] <= $to) {
                    $records[] = $record;

                    if (count($records) > $max) {
                        throw new InvalidArgumentException(
                            "range exceeds maximum: $max"
                        );
                    }
                }
            }
        }

        return $records;
    }

    private function history_files($host) {
        $files = array_merge(
            glob(
                rtrim($this->dataDir, '/') .
                '/' . $host . '-*.jsonl'
            ),
            glob(
                rtrim($this->dataDir, '/') .
                '/' . $host . '-*.jsonl.gz'
            )
        );

        sort($files, SORT_STRING);

        return $files;
    }

    private function read_lines($file) {
        if (substr($file, -3) === '.gz') {
            $lines = array();

            exec(
                'gzip -dc ' . escapeshellarg($file),
                $lines
            );

            return $lines;
        }

        return file(
            $file,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );
    }
}