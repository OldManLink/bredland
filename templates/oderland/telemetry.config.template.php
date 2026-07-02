<?php
// BRD-003: private configuration template for generic telemetry endpoint.
//
// Copy outside the web root and replace placeholders during deployment.
// Do not commit rendered files.

const TELEMETRY_SCHEMA_VERSION = 1;

$HOST_TOKENS = [
    '__MIKROTIK_NOC_HOST__' => '__MIKROTIK_NOC_TOKEN__',
    '__BREDLAND_NOC_HOST__' => '__BREDLAND_NOC_TOKEN__',
    'smoke-test' => 'bredland.v1.smoke_test_token',
];

$DATA_DIR = '__NOC_DATA_DIR__';

