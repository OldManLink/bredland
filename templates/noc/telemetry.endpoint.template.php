<?php
// BRD-003: Generic NOC telemetry endpoint.
//
// PHP 5.5 compatible.
// This file is a template.
// Deployment-specific values are injected outside version control.

$baseDir = dirname($_SERVER['SCRIPT_FILENAME']);

require_once '__TELEMETRY_CONFIG_FILE__';
require_once "$baseDir/lib/telemetry-endpoint.php";

if (!isset($HOST_TOKENS) || !isset($DATA_DIR)) {
    $response = new TelemetryResponse(
        500,
        'server configuration error'
    );
} else {
    $endpoint = new TelemetryEndpoint(
        new Authenticator($HOST_TOKENS),
        new SchemaLoader("$baseDir/schemas"),
        new TelemetryStorage($DATA_DIR)
    );

    $response = $endpoint->handle(
        $_SERVER,
        $_POST
    );
}

http_response_code($response->status());
header('Content-Type: ' . $response->content_type());
echo $response->body();