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

function load_record_schema($schemas_dir, $host) {
    $schema_file = $schemas_dir . '/' . $host . '.json';

    if (!file_exists($schema_file)) {
        throw new InvalidArgumentException("missing record schema: $host");
    }

    $contents = file_get_contents($schema_file);

    if ($contents === false) {
        throw new InvalidArgumentException("invalid record schema: $host");
    }

    $schema = json_decode($contents, true);

    if ($schema === null || json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException("invalid record schema: $host");
    }

    return $schema;
}

function build_record($schema, $source) {
    $record = array();

    foreach ($schema as $field_name => $rule) {
        if (array_key_exists('const', $rule)) {
            $record[$field_name] = $rule['const'];
            continue;
        }

        if (!array_key_exists($field_name, $source)) {
            throw new InvalidArgumentException("missing field: $field_name");
        }

        $value_type = $rule['valueType'];
        $value = convert_field_value($source[$field_name], $value_type);

        if ($value === null) {
            throw new InvalidArgumentException(
                "invalid value for field $field_name: expected $value_type"
            );
        }

        $record[$field_name] = $value;
    }

    return $record;
}

function convert_field_value($value, $value_type) {
    if ($value_type === 'integer') {
        if (!preg_match('/^-?[0-9]+$/', $value)) {
            return null;
        }

        return (int)$value;
    }

    if ($value_type === 'float') {
        if (!preg_match('/^-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/', $value)) {
            return null;
        }

        return (float)$value;
    }

    if ($value_type === 'boolean') {
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        return null;
    }

    if ($value_type === 'string') {
        return (string)$value;
    }

    return null;
}
