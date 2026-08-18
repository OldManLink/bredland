#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/heartbeat-age.php';

$runner = new TestSuiteRunner('heartbeat-age');

$runner->test('render() renders the formatted heartbeat age', function () {
    with_noc_now('2026-08-10T12:01:05Z', function () {
        $client = test_client(
            array(),
            array(
                'ts' => '2026-08-10T12:00:00Z'
            )
        );

        $heartbeat_age = new HeartbeatAge(1, $client);
        $html = $heartbeat_age->render();
        $paragraph = '<p>Last heartbeat: 1m 5s ago</p>';

        assertStringStartsWith(indentation(1, $paragraph), $html);
        assertSame(1, substr_count($html, '<p>'));
        assertSame(1, substr_count($html, '</p>'));
        assertSame(1, substr_count($html, 'Last heartbeat:'));
        assertSame(1, substr_count($html, '1m 5s ago'));
        assertSame(1, substr_count($html, "\n"));

        $paragraph_start = strpos($html, '<p>');
        $label_start = strpos($html, 'Last heartbeat:');
        $age_start = strpos($html, '1m 5s ago');
        $paragraph_end = strpos($html, '</p>');

        assertTrue($paragraph_start < $label_start);
        assertTrue($label_start < $age_start);
        assertTrue($age_start < $paragraph_end);
    });
});

$runner->finish();
