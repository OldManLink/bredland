<?php

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
                yield json_decode($line, true);
            }
        }
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
