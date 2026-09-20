<?php
// BRD-003: private configuration template for generic telemetry endpoint.
//
// Copy outside the web root and replace placeholders during deployment.
// Do not commit rendered files.

const TELEMETRY_SCHEMA_VERSION = 1;
const HEARTBEAT_HISTORY_PAGE_SIZE = 100;
$DATA_DIR = '__NOC_DATA_DIR__';
$HOST_TOKENS = [ // Arrays are not allowed as constants in PHP 5.5
    '__MIKROTIK_NOC_HOST__' => '__MIKROTIK_NOC_TOKEN__',
    '__BREDLAND_NOC_HOST__' => '__BREDLAND_NOC_TOKEN__'
];
