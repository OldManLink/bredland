<?php

class RecordBuilder {
    public function build($schema, $source) {
        $record = array();

        foreach ($schema as $fieldName => $rule) {
            if (array_key_exists('const', $rule)) {
                $record[$fieldName] = $rule['const'];
                continue;
            }

            if (!array_key_exists($fieldName, $source)) {
                throw new InvalidArgumentException(
                    "missing field: $fieldName"
                );
            }

            $valueType = $rule['value_type'];
            $value = $this->convert_field_value(
                $source[$fieldName],
                $valueType
            );

            if ($value === null) {
                throw new InvalidArgumentException(
                    "invalid value for field $fieldName: expected $valueType"
                );
            }

            $record[$fieldName] = $value;
        }

        return $record;
    }

    private function convert_field_value($value, $valueType) {
        if ($valueType === 'integer') {
            if (!preg_match('/^-?[0-9]+$/', $value)) {
                return null;
            }

            return (int) $value;
        }

        if ($valueType === 'float') {
            if (!preg_match(
                '/^-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/',
                $value
            )) {
                return null;
            }

            return (float) $value;
        }

        if ($valueType === 'boolean') {
            if ($value === 'true') {
                return true;
            }

            if ($value === 'false') {
                return false;
            }

            return null;
        }

        if ($valueType === 'string') {
            return (string) $value;
        }

        return null;
    }
}