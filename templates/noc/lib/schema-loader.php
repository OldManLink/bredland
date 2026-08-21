<?php

class SchemaLoader {
    private $schemasDir;

    public function __construct($schemasDir) {
        $this->schemasDir = $schemasDir;
    }

    public function load($host) {
        $schemaFile = $this->schemasDir . '/' . $host . '.json';

        if (!file_exists($schemaFile)) {
            throw new InvalidArgumentException(
                "missing record schema: $host"
            );
        }

        $contents = file_get_contents($schemaFile);

        if ($contents === false) {
            throw new InvalidArgumentException(
                "invalid record schema: $host"
            );
        }

        $schema = json_decode($contents, true);

        if ($schema === null ||
            json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                "invalid record schema: $host"
            );
        }

        return $schema;
    }
}
