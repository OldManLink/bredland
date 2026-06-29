<?php
// BRD-003: private configuration template for generic telemetry endpoint.
//
// Copy outside the web root and replace placeholders during deployment.
// Do not commit rendered files.

$HOST_TOKENS = [
    'mikrotik' => '__MIKROTIK_NOC_TOKEN__',
];
$DATA_DIR = '__NOC_DATA_DIR__';
