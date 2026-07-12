<?php

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/rule.php';
require_once __DIR__ . '/predicate.php';
require_once __DIR__ . '/effect.php';
require_once __DIR__ . '/compilation-result.php';

function compile_predicate($predicateJson, $path) {
    if (!is_array($predicateJson)) {
        return compilation_failure("$path must be an object");
    }

    if (!array_key_exists('field', $predicateJson)) {
        return compilation_failure("$path: expected field");
    }

    $receiver = $predicateJson['field'];

    if (!is_string($receiver) || $receiver === '') {
        return compilation_failure(
            "$path: field must be a non-empty string"
        );
    }

    $supportedComparators = array('equals', 'lessThan');
    $comparators = array();

    foreach ($supportedComparators as $comparator) {
        if (array_key_exists($comparator, $predicateJson)) {
            $comparators[] = $comparator;
        }
    }

    if (count($comparators) !== 1) {
        return compilation_failure(
            "$path: expected exactly one supported comparator"
        );
    }

    $comparator = $comparators[0];
    $argument = $predicateJson[$comparator];

    if (
        $comparator === 'equals' &&
        !is_bool($argument) &&
        !is_int($argument) &&
        !is_float($argument) &&
        !is_string($argument)
    ) {
        return compilation_failure(
            "$path.equals: must be a scalar value"
        );
    }

    if (
        $comparator === 'lessThan' &&
        !is_int($argument) &&
        !is_float($argument)
    ) {
        return compilation_failure(
            "$path.lessThan: must be numeric"
        );
    }

    return compilation_success(
        new Predicate($receiver, $comparator, $argument)
    );
}

function compile_effect($effectJson, $path) {
    if (!is_array($effectJson)) {
        return compilation_failure("$path must be an object");
    }

    if (!array_key_exists('type', $effectJson)) {
        return compilation_failure("$path: expected type");
    }

    $effectType = $effectJson['type'];

    if (!is_string($effectType) || $effectType === '') {
        return compilation_failure(
            "$path: type must be a non-empty string"
        );
    }

    if ($effectType === 'notification') {
        if (!array_key_exists('message', $effectJson)) {
            return compilation_failure(
                "$path: notification requires message"
            );
        }

        $argument = $effectJson['message'];
        $argumentName = 'message';
    } elseif ($effectType === 'health') {
        if (!array_key_exists('value', $effectJson)) {
            return compilation_failure(
                "$path: health requires value"
            );
        }

        $argument = $effectJson['value'];
        $argumentName = 'value';
    } else {
        return compilation_failure(
            "$path: unsupported effect type $effectType"
        );
    }

    if (!is_string($argument) || $argument === '') {
        return compilation_failure(
            "$path: $argumentName must be a non-empty string"
        );
    }

    if (
        $effectType === 'health' &&
        !in_array(
            $argument,
            array('healthy', 'warning', 'critical'),
            true
        )
    ) {
        return compilation_failure(
            "$path: health unsupported value: $argument"
        );
    }

    return compilation_success(
        new Effect($effectType, $argument)
    );
}

function compile_rule($ruleJson, $path) {
    if (!is_array($ruleJson)) {
        return compilation_failure("$path must be an object");
    }

    $validation = check_allowed_keys(
        $ruleJson,
        array('when', 'then'),
        $path
    );

    if ($validation->hasErrors()) {
        return $validation;
    }

    if (!array_key_exists('when', $ruleJson)) {
        return compilation_failure("$path: expected when");
    }

    if (!array_key_exists('then', $ruleJson)) {
        return compilation_failure("$path: expected then");
    }

    $predicateResult = compile_predicate(
        $ruleJson['when'],
        "$path.when"
    );

    if ($predicateResult->hasErrors()) {
        return $predicateResult;
    }

    $effectResult = compile_effect(
        $ruleJson['then'],
        "$path.then"
    );

    if ($effectResult->hasErrors()) {
        return $effectResult;
    }

    return compilation_success(
        new Rule(
            $predicateResult->value(),
            $effectResult->value()
        )
    );
}

function compile_rules($rulesJson) {
    if (!is_array($rulesJson)) {
        return compilation_failure("rules must be an array");
    }

    $rules = array();
    $messages = array();

    foreach ($rulesJson as $index => $ruleJson) {
        $result = compile_rule(
            $ruleJson,
            indexed_path('rules', $index)
        );

        if ($result->hasErrors()) {
            $messages = array_merge(
                $messages,
                $result->messages()
            );
        } else {
            $rules[] = $result->value();
        }
    }

    return new CompilationResult(
        $rules,
        $messages
    );
}

function check_allowed_keys($object, $allowedKeys, $path) {
    $allowed = array_flip($allowedKeys);

    foreach ($object as $key => $value) {
        if (!isset($allowed[$key])) {
            return compilation_failure(
                "$path: unsupported attribute $key"
            );
        }
    }

    return compilation_success(null);
}
