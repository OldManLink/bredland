<?php
require_once __DIR__ . '/test-runner.php';

function assertSame($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        throw new AssertionFailed(
            "Same assertion failed" . ($message === '' ? '' : ": " . $message) . "\n" .
            "Expected: " . var_export($expected, true) . "\n" .
            "Actual:   " . var_export($actual, true) . "\n"
        );
    }
}

function assertDifferent($expected, $actual, $message = '') {
    if ($expected === $actual) {
        throw new AssertionFailed(
            "Different assertion failed" . ($message === '' ? '' : ": " . $message) . "\n" .
            "Expected: " . var_export($expected, true) . "\n" .
            "Actual:   " . var_export($actual, true) . "\n"
        );
    }
}

function assertThrows($exceptionClass, $expectedMessage, $operation)
{
    try {
        call_user_func($operation);
    } catch (Exception $e) {
        assertSame(
            $exceptionClass,
            get_class($e),
            'Unexpected exception type'
        );
        assertSame($expectedMessage, $e->getMessage());
        return;
    }

    throw new AssertionFailed(
        "Expected exception: $exceptionClass\n" .
        "Expected message: " . var_export($expectedMessage, true)
    );
}

function disabled($testDescription) {
    logDebug("Disabled test: $testDescription");
}

function logDebug($message) {
    fwrite(STDERR,"\n🔎 >> $message << 🔍\n");
}

function assertTrue($actual, $message = '') {
    assertSame(true, $actual, $message);
}

function assertFalse($actual, $message = '') {
    assertSame(false, $actual, $message);
}

function fail($message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function assertIdentifier($identifier, $message = '') {
    assertTrue(
        preg_match('/^[a-z][a-z0-9_]*$/', $identifier) === 1,
        $message === ''
            ? "Expected valid identifier: $identifier"
            : $message
    );
}

function required_string($array, $key, $context) {
    assertTrue(isset($array[$key]), "$context must define $key");
    assertTrue(is_string($array[$key]) && $array[$key] !== '', "$context $key must be a non-empty string");

    return $array[$key];
}

function assert_allowed_keys($required, $allowed, $actual, $context)
{
    $actualKeys = array_keys($actual);

    foreach ($required as $key) {
        assertTrue(
            array_key_exists($key, $actual),
            "$context is missing required property: $key"
        );
    }

    foreach ($actualKeys as $key) {
        assertTrue(
            in_array($key, $allowed, true),
            "$context contains unknown property: $key"
        );
    }
}

function assert_compile_success($result) {
    assertTrue($result instanceof CompilationResult, 'CompilationResult expected');
    assertTrue($result->isSuccess());
}

function assert_compile_error($result, $message) {
    assertSame(null, $result->value());
    assertDifferent(0, count($result->errors()));
    assertSame($message, $result->errors()[0]);
}

function test_schema() {
    return array(
       'ts' => array(
           'value_type' => 'integer'
       ),
       'uptime' => array(
           'value_type' => 'integer'
       ),
       'temperature' => array(
           'value_type' => 'float'
       ),
       'free_memory' => array(
           'value_type' => 'integer'
       ),
       'latest_version' => array(
           'value_type' => 'string'
       ),
       'update_available' => array(
            'value_type' => 'boolean'
       )
    );
}

function test_methods() {
    return array(
       'addNotification' => SlotVal::class,
       'setHealth' => HealthVal::class,
       'setValue' => StrVal::class
    );
}

function from_json($json) {
    $result = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception(
            json_last_error_msg()
        );
    }

    return $result;
}

function from_json_file($filename) {
    assertTrue(
        file_exists($filename),
        "Missing JSON file: $filename"
    );

    return from_json(file_get_contents($filename));
}