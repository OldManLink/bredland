#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/telemetry-drawer.php';

$runner = new TestRunner('telemetry-drawer');

$runner->test('render() renders escaped heartbeat telemetry', function () {
    $client = test_client(
        array(),
        array(
            'message' => '<ready> & "waiting"'
        )
    );

    $telemetry_drawer = new TelemetryDrawer(1, $client);
    $html = $telemetry_drawer->render();

    $heartbeat = json_encode($client->heartbeat());

    $escaped_heartbeat = htmlspecialchars(
        $heartbeat,
        ENT_QUOTES,
        'UTF-8'
    );

    $template = '<template id="test-client-telemetry-template">';
    $pre = '<pre class="telemetry">';

    assertStringContains($template, $html);
    assertStringContains("<pre class=\"telemetry\">$escaped_heartbeat</pre>", $html);
    assertStringContains('</template>', $html);

    assertSame(1, substr_count($html, $template));
    assertSame(1, substr_count($html, $pre));
    assertSame(1, substr_count($html, $escaped_heartbeat));
    assertSame(0, substr_count($html, $heartbeat));
    assertSame(3, substr_count($html, "\n"));

    $template_start = strpos($html, $template);
    $pre_start = strpos($html, $pre);
    $heartbeat_start = strpos($html, $escaped_heartbeat);
    $pre_end = strpos($html, '</pre>', $heartbeat_start);
    $template_end = strpos($html, '</template>', $pre_end);

    assertTrue($template_start < $pre_start);
    assertTrue($pre_start < $heartbeat_start);
    assertTrue($heartbeat_start < $pre_end);
    assertTrue($pre_end < $template_end);
});

$runner->finish();
