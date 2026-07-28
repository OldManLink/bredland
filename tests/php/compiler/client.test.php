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
          "method": "addNotification",
          "argument": "Warning: low memory, {{free_memory}} bytes free."
        }
      }
    ],
  "order": 42
}
JSON
);

$clientJson2 = from_json(<<<'JSON'
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

$clientJson3 = from_json(<<<'JSON'
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
      },
      {
        "when": {
          "field": "update_available",
          "operator": "equals",
          "value": true
        },
        "then": {
          "receiver": "client",
          "method": "addNotification",
          "argument": "Software update available:\nVersion: {{latest_version}} (stable)."
        }
      }
    ],
  "order": 42
}
JSON
);

$heartbeatJson = from_json(<<<'JSON'
{
  "schema": 1,
  "ts": "2026-07-26T21:23:02Z",
  "host": "test",
  "uptime": 2673306,
  "version": "7.23.1 (stable)",
  "model": "RB4011iGS+",
  "cpu_load": 0,
  "free_memory": 879349760,
  "total_memory": 1073741824,
  "update_available": true,
  "latest_version": "7.23.2",
  "remote_addr": "91.128.129.6"
}
JSON
);

$heartbeatJson2 = from_json(<<<'JSON'
{
  "schema": 1,
  "ts": "2026-07-26T21:23:02Z",
  "host": "test",
  "uptime": 2673306,
  "version": "7.23.1 (stable)",
  "model": "RB4011iGS+",
  "cpu_load": 0,
  "free_memory": 1073741824,
  "total_memory": 1073741824,
  "update_available": true,
  "latest_version": "7.23.2",
  "remote_addr": "91.128.129.6"
}
JSON
);

$runner->test('render tests: Client action triggered', function () use ($clientJson, $heartbeatJson) {
    $client = Client::compile($clientJson, test_schema(), 'Happy Path')->value();
    assertSame(null, $client->health());
    assertThrows('Exception', 'Programming error: Client has not been rendered',
        function () use ($client) {
            $client->get('uptime');
        }
    );
    $client->render($heartbeatJson);
    assertSame(display_uptime(2673306), $client->get('uptime'));
    assertSame(1, count($client->notifications()));
    assertSame('Warning: low memory, 879349760 bytes free.', $client->notifications()[0]->text());
    assertSame(null, $client->health());
});

$runner->test('render tests: Client action not triggered', function () use ($clientJson, $heartbeatJson2) {
    $client = Client::compile($clientJson, test_schema(), 'Happy Path')->value();
    assertSame(null, $client->health());
    $client->render($heartbeatJson2);
    assertSame(0, count($client->notifications()));
    assertSame(null, $client->health());
});

$runner->test('render tests: Client setHealth triggered', function () use ($clientJson2, $heartbeatJson) {
    $client = Client::compile($clientJson2, test_schema(), 'Happy Path')->value();
    assertSame("warning", $client->render($heartbeatJson)->health());
});

$runner->test('render tests: multiple Client rules triggered', function () use ($clientJson3, $heartbeatJson) {
    $client = Client::compile($clientJson3, test_schema(), 'Happy Path')->value();

    assertSame("warning", $client->render($heartbeatJson)->health());

    assertSame(1, $client->notification_count());
    assertSame("Software update available:\nVersion: 7.23.2 (stable).",
        $client->notifications()[0]->text()
    );
});

$runner->test('compiler tests: Client', function () use ($clientJson) {
    $result = Client::compile($clientJson, test_schema(), 'Happy Path');
    assert_compile_success($result);

    $client = $result->value();
    assertTrue($client->host() instanceof StrVal, 'StrVal expected');
    assertTrue($client->title() instanceof StrVal, 'StrVal expected');
    assertTrue($client->fields() instanceof FieldList, 'FieldList expected');
    assertSame('array', runtime_type($client->rules()));
    assertTrue($client->order() instanceof IntVal, 'IntVal expected');

    assertSame('test', $client->host()->value());
    assertSame('Test', $client->title()->value());
    assertSame(1, count($client->fields()));
    assertTrue($client->fields()->fields()['uptime'] instanceof Field);
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

$runner->test('starts with no notifications', function () {
    $client = new Client(new StrVal("test"), new StrVal("Test"), array(), array(), new IntVal(42));

    assertSame(array(), $client->notifications());
    assertSame(0, $client->notification_count());
});

$runner->test('adds notification', function () {
    $client = new Client(new StrVal("test"), new StrVal("Test"), array(), array(), new IntVal(42));

    $client->addNotification('This is a test!');

    assertSame(1, $client->notification_count());
    assertTrue($client->notifications()[0] instanceof Notification);
    assertSame('This is a test!', $client->notifications()[0]->text());
});

$runner->test('preserves notification order', function () {
    $client = new Client(new StrVal("test"), new StrVal("Test"), array(), array(), new IntVal(42));

    $client->addNotification('First');
    $client->addNotification('Second');

    assertSame(2, $client->notification_count());
    assertSame('First', $client->notifications()[0]->text());
    assertSame('Second', $client->notifications()[1]->text());
});

$runner->finish();
