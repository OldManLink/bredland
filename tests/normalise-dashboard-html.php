<?php

$html = file_get_contents($argv[1]);

$html = preg_replace(
    '/Last heartbeat:\s*[^<]+/',
    'Last heartbeat: __DYNAMIC__',
    $html
);

$html = preg_replace(
    '/(<pre class="telemetry">).*?(<\/pre>)/s',
    '$1__DYNAMIC_TELEMETRY__$2',
    $html
);

$html = preg_replace(
    '/\?v=\d+/',
    '?v=__STATIC_VERSION__',
    $html
);

$html = preg_replace(
    '/\b(?:green|amber|red)\b/',
    '__HEALTH__',
    $html
);

$html = preg_replace(
    '/Uptime: [^<]+/',
    'Uptime: __DYNAMIC__',
    $html
);

$html = preg_replace(
    '/Free memory: [^<]+/',
    'Free memory: __DYNAMIC__',
    $html
);

$html = preg_replace('/\s+/', ' ', $html);

echo trim($html), PHP_EOL;
