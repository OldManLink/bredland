<?php
require_once __DIR__ . '/compatibility.php';

class HeartbeatStream implements IteratorAggregate {
    private $files;

    public function __construct($files) {
        $this->files = $files;
    }

    public function getIterator() {
        $files = array_reverse($this->files);

        foreach ($files as $file) {
            $lines = array_reverse(
                $this->read_lines($file)
            );

            foreach ($lines as $line) {
                $record = json_decode($line, true);

                if (runtime_type($record) !== 'object') {
                    throw new RuntimeException(
                        'invalid heartbeat record in ' . $file
                    );
                }

                yield $record;
            }
        }
    }

    private function read_lines($file) {
        if (substr($file, -3) === '.gz') {
            $lines = array();
            $status = 0;

            exec(
                'gzip -dc ' . escapeshellarg($file) . ' 2>/dev/null',
                $lines,
                $status
            );

            if ($status !== 0) {
                throw new RuntimeException(
                    'failed to read heartbeat archive: ' . $file
                );
            }

            return $lines;
        }

        $lines = @file(
            $file,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if ($lines === false) {
            throw new RuntimeException(
                'failed to read heartbeat archive: ' . $file
            );
        }

        return $lines;
    }
}
