<?php
require_once __DIR__ . '/heartbeat-stream.php';

class HeartbeatHistory {
    private $dataDir;

    public function __construct($dataDir) {
        $this->dataDir = $dataDir;
    }

    public function latest($host, $count, $startingAt = null) {
        $records = array();

        $stream = new HeartbeatStream(
            $this->history_files($host)
        );

        foreach ($stream as $record) {
            if ($startingAt !== null &&
                $record['ts'] > $startingAt) {
                continue;
            }

            $records[] = $record;

            if (count($records) === $count) {
                break;
            }
        }

        return array_reverse($records);
    }

    public function range($host, $from, $to, $max) {
        $records = array();

        $stream = new HeartbeatStream(
            $this->range_files($host, $from, $to)
        );

        foreach ($stream as $record) {
            if ($record['ts'] > $to) {
                continue;
            }

            if ($record['ts'] < $from) {
                break;
            }

            $records[] = $record;

            if (count($records) > $max) {
                break;
            }
        }

        return array_reverse($records);
    }

    private function range_files($host, $from, $to) {
        $files = array();

        foreach ($this->history_files($host) as $file) {
            $name = basename($file);

            if (preg_match(
                '/^' . preg_quote($host, '/') .
                '-([0-9]{4})-([0-9]{2})-([0-9]{2})\.jsonl(?:\.gz)?$/',
                $name,
                $parts
            )) {
                $date = $parts[1] . '-' . $parts[2] . '-' . $parts[3];

                if ($date >= substr($from, 0, 10) &&
                    $date <= substr($to, 0, 10)) {
                    $files[] = $file;
                }

                continue;
            }

            if (preg_match(
                '/^' . preg_quote($host, '/') .
                '-([0-9]{4})-([0-9]{2})\.jsonl\.gz$/',
                $name,
                $parts
            )) {
                $month = $parts[1] . '-' . $parts[2];

                if ($month >= substr($from, 0, 7) &&
                    $month <= substr($to, 0, 7)) {
                    $files[] = $file;
                }
            }
        }

        return $files;
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
}
