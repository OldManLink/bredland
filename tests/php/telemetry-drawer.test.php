#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/telemetry-drawer.php';

$runner = new TestRunner('telemetry-drawer');

$tmpdir = sys_get_temp_dir() . '/telemetry-drawer-test-' . uniqid('', true);

if (!mkdir($tmpdir)) {
    throw new RuntimeException('failed to create temporary directory');
}

$runner->test('render() renders escaped heartbeat telemetry', function () use ($tmpdir) {
    $heartbeat_file = "$tmpdir/test-client.jsonl";

    try {
        $heartbeat = '{"message":"<ready> & \\"waiting\\""}';

        if (file_put_contents(
            $heartbeat_file,
            $heartbeat . "\n",
            LOCK_EX
        ) === false) {
            throw new RuntimeException(
                'failed to create heartbeat file: ' . $heartbeat_file
            );
        }

        $client = array(
            'host' => 'test-client',
            'heartbeat_file' => $heartbeat_file
        );

        $telemetry_drawer = new TelemetryDrawer(1, $client);
        $html = $telemetry_drawer->render();

        $template = '<template id="test-client-telemetry-template">';
        $pre = '<pre class="telemetry">';
        $escaped_heartbeat = htmlspecialchars(
            $heartbeat,
            ENT_QUOTES,
            'UTF-8'
        );

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
    } finally {
        if (file_exists($heartbeat_file)) {
            unlink($heartbeat_file);
        }
    }
});

rmdir($tmpdir);
$runner->finish();
