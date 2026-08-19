#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/schema-loader.php';

$runner = new TestSuiteRunner('schema-loader');

$runner->test('loads schema for host', function () {
    $dir = sys_get_temp_dir() . '/bredland-schema-' . uniqid();
    mkdir($dir);

    file_put_contents(
        $dir . '/bredland.json',
        '{"schema":{"const":1},"host":{"const":"bredland"}}'
    );

    $loader = new SchemaLoader($dir);
    $schema = $loader->load('bredland');

    assertSame(
        array(
            'schema' => array('const' => 1),
            'host' => array('const' => 'bredland')
        ),
        $schema
    );

    unlink($dir . '/bredland.json');
    rmdir($dir);
});

$runner->test('rejects missing schema', function () {
    $dir = sys_get_temp_dir() . '/bredland-schema-' . uniqid();
    mkdir($dir);

    $loader = new SchemaLoader($dir);

    try {
        $loader->load('bredland');
        fail('expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        assertSame(
            'missing record schema: bredland',
            $e->getMessage()
        );
    }

    rmdir($dir);
});

$runner->test('rejects invalid schema', function () {
    $dir = sys_get_temp_dir() . '/bredland-schema-' . uniqid();
    mkdir($dir);

    file_put_contents(
        $dir . '/bredland.json',
        '{not valid json'
    );

    $loader = new SchemaLoader($dir);

    try {
        $loader->load('bredland');
        fail('expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        assertSame(
            'invalid record schema: bredland',
            $e->getMessage()
        );
    }

    unlink($dir . '/bredland.json');
    rmdir($dir);
});

$runner->finish();
