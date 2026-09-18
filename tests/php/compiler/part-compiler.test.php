#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/compilable.php';
require_once $compilerRoot .'/compilation-result.php';
require_once $compilerRoot .'/part-compiler.php';

class TestVal implements Compilable {
    public static function compile($definition, $schema, $path) {
        return CompilationResult::success($definition);
    }
}

class TestParts {
    use PartCompiler;

    private static function partClasses() {
        return array(
            'required' => TestVal::class,
            'optional' => TestVal::class
        );
    }

    private static function optionalParts() {
        return array(
            'optional' => true
        );
    }

    public static function compile_test_parts($definition) {
        return self::compile_parts($definition, array(), 'test');
    }
}

$runner = new TestSuiteRunner('part-compiler');

$runner->test('missing optional part compiles to None', function () {
    $result = TestParts::compile_test_parts(
        array(
            'required' => 'hello'
        )
    );

    assertTrue($result->isSuccess());

    $parts = $result->value();

    assertSame(
        array(),
        $parts['optional']->value()->values()
    );
});

$runner->test('present optional part compiles to Some(compiled_value)', function () {
    $result = TestParts::compile_test_parts(
        array(
            'required' => 'hello',
            'optional' => 'fubar'
        )
    );

    assertTrue($result->isSuccess());
    $parts = $result->value();

    assertSame(
        array('fubar'),
        $parts['optional']->value()->values()
    );
});

$runner->test('missing required part fails', function () {
    $result = TestParts::compile_test_parts(
        array(
            'optional' => 'hello'
        )
    );

    assertFalse($result->isSuccess());

    assertSame(
        array(
            'test.required: missing required part'
        ),
        $result->errors()
    );
});

$runner->test('non-object definition fails', function () {
    $result = TestParts::compile_test_parts(
        array('hello')
    );

    assertFalse($result->isSuccess());

    assertSame(
        array(
            'test: must be an object'
        ),
        $result->errors()
    );
});

$runner->finish();