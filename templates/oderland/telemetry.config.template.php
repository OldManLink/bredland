<?php
// BRD-003: private configuration template for generic telemetry endpoint.
//
// Copy outside the web root and replace placeholders during deployment.
// Do not commit rendered files.

$HOST_TOKENS = [
    '__MIKROTIK_NOC_HOST__' => '__MIKROTIK_NOC_TOKEN__',
    '__BREDLAND_NOC_HOST__' => '__BREDLAND_NOC_TOKEN__',
];
$DATA_DIR = '__NOC_DATA_DIR__';
