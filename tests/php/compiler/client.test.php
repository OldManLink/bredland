#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpRoot = dirname(__DIR__);
require_once $phpRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpRoot)) . '/templates/noc/lib/compiler';
require_once $compilerRoot .'/client.php';

$runner = new TestRunner('Client');

$runner->test('instance creation', function () {
    $client = new Client(new StrVal("test"), new StrVal("Test"), array(), array(), new IntVal(42));

    assertSame("test", $client->host()->value());
    assertSame("Test", $client->title()->value());
    assertSame(array(), $client->fields());
    assertSame(array(), $client->rules());
    assertSame(42, $client->order()->value());
});

$runner->test('compiler tests: Client', function () {
    $clientJson = from_json(<<<'JSON'
    {
      "host": "test",
      "title": "Test",
      "fields": [
        {
          "label": "Uptime",
          "field": "uptime",
          "value_type": "integer",
          "format": "display_uptime"
        }
      ],
      "rules": [
          {
            "when": {
              "field": "free_memory",
              "operator": "lessThan",
              "value": 1073741824
            },
            "then": {
              "receiver": "client",
              "method": "setHealth",
              "argument": "warning"
            }
          }
        ],
      "order": 42
    }
JSON
    );

    $result = Client::compile($clientJson, test_schema(), 'Happy Path');
    assert_compile_success($result);

    $client = $result->value();
    assertTrue($client->host() instanceof StrVal, 'StrVal expected');
    assertTrue($client->title() instanceof StrVal, 'StrVal expected');
    assertSame('array', runtime_type($client->fields()));
    assertSame('array', runtime_type($client->rules()));
    assertTrue($client->order() instanceof IntVal, 'IntVal expected');

    assertSame('test', $client->host()->value());
    assertSame('Test', $client->title()->value());
    assertSame(1, count($client->fields()));
    assertTrue($client->fields()[0] instanceof Field);
    assertSame(1, count($client->rules()));
    assertTrue($client->rules()[0] instanceof Rule);
    assertSame(42, $client->order()->value());

    assert_compile_error(Client::compile(42, test_schema(), '42'), '42: must be an object');
});

$runner->test('rejects missing host', function () {
    $clientJson = from_json(<<<'JSON'
    {
      "title": "Test",
      "fields": [],
      "rules": [],
      "order": 42
    }
JSON
    );
    assert_compile_error(Client::compile($clientJson, test_schema(), 'client'), 'client: expected host');
});

$runner->test('rejects missing title', function () {
    $clientJson = from_json(<<<'JSON'
    {
      "host": "test",
      "fields": [],
      "rules": [],
      "order": 42
    }
JSON
    );
    assert_compile_error(Client::compile($clientJson, test_schema(), 'client'), 'client: expected title');
});

$runner->test('rejects missing fields', function () {
    $clientJson = from_json(<<<'JSON'
    {
      "host": "test",
      "title": "Test",
      "rules": [],
      "order": 42
    }
JSON
    );
    assert_compile_error(Client::compile($clientJson, test_schema(), 'client'), 'client: expected fields');
});

$runner->test('rejects missing rules', function () {
    $clientJson = from_json(<<<'JSON'
    {
      "host": "test",
      "title": "Test",
      "fields": [],
      "order": 42
    }
JSON
    );
    assert_compile_error(Client::compile($clientJson, test_schema(), 'client'), 'client: expected rules');
});

$runner->test('rejects missing order', function () {
    $clientJson = from_json(<<<'JSON'
    {
      "host": "test",
      "title": "Test",
      "fields": [],
      "rules": []
    }
JSON
    );
    assert_compile_error(Client::compile($clientJson, test_schema(), 'client'), 'client: expected order');
});

$runner->test('rejects unsupported attribute', function () {
    $clientJson = from_json(<<<'JSON'
    {
      "host": "test",
      "title": "Test",
      "fields": [],
      "rules": [],
      "order": 42,
      "healthy": true
    }
JSON
    );
    assert_compile_error(Client::compile($clientJson, test_schema(), 'client'), 'client: unsupported attribute: healthy');
});

$runner->test('rejects invalid identifier', function () {
    $clientJson = from_json(<<<'JSON'
    {
      "host": "test",
      "title": "Test",
      "fields": [],
      "rules": [],
      "order": 42,
      "héalthy": true
    }
JSON
    );
    assert_compile_error(Client::compile($clientJson, test_schema(), 'client'), 'client: invalid identifier: héalthy');
});

$runner->finish();
