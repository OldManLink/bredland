<?php

function reserved_fields() {
    return array('schema', 'ts', 'host', 'token', 'uptime', 'fields', 'remote_addr');
}

function select_fields($fields, $source) {
    $selected = array();

    foreach (explode(',', $fields) as $field) {
        $field = trim($field);

        if ($field === '') {
            continue;
        }

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $field)) {
            throw new InvalidArgumentException("invalid field name: $field");
        }

        if (!array_key_exists($field, $source)) {
            throw new InvalidArgumentException("missing field: $field");
        }

        if (in_array($field, reserved_fields(), true)) {
            throw new InvalidArgumentException("reserved field: $field");
        }

        $selected[$field] = trim($source[$field]);
    }

    return $selected;
}

function build_record($schema_version, $timestamp, $host, $fields) {
    return array_merge(
        array(
            'schema' => $schema_version,
            'ts' => $timestamp,
            'host' => $host,
        ),
        $fields
    );
}