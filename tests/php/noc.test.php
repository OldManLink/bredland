#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/noc.php';
require_once $nocRoot . '/lib/dashboard.php';
require_once $nocRoot . '/lib/text-renderable.php';

$runner = new TestRunner('noc');

$tmpdir = sys_get_temp_dir() . '/noc-test-' . uniqid('', true);

if (!mkdir($tmpdir)) {
    throw new RuntimeException('failed to create temporary directory');
}

$runner->test('render() renders the complete noc', function () use ($tmpdir){
try {
    $clients = array();
    $template_file = "$tmpdir/noc_template.php";
    $test_contents = <<<'PHP'
<div id="dashboard-test">
</div>
PHP
;
    if (file_put_contents($template_file, "<?php ?>\n$test_contents", LOCK_EX) === false) {
        throw new RuntimeException('failed to create template: ' . $template_file);
    }
    $dashboard = new Dashboard($clients, $template_file);
    $noc = new Noc($dashboard);
    $html = $noc->render();

    assertStringContains('<!DOCTYPE html>', $html);
    assertStringContains('<html lang="en">', $html);
    assertStringContains('<head>', $html);
    assertStringContains('</head>', $html);
    assertStringContains('<body>', $html);
    assertStringContains('<div id="dashboard-test">', $html);
    assertStringContains('</body>', $html);
    assertStringContains('</html>', $html);

    assertSame(1, substr_count($html, '<!DOCTYPE html>'));
    assertSame(1, substr_count($html, '<html lang="en">'));
    assertSame(1, substr_count($html, '<div id="dashboard-test">'));

    assertTrue(strpos($html, '<head>') < strpos($html, '</head>'));
    assertTrue(strpos($html, '</head>') < strpos($html, '<body>'));
    assertTrue(strpos($html, '<body>') < strpos($html, '<div id="dashboard-test">'));
    assertTrue(strpos($html, '<div id="dashboard-test">') < strpos($html, '</body>'));
}
finally {
    if (file_exists($template_file)) {
        unlink($template_file);
    }
}
});

rmdir($tmpdir);
$runner->finish();
