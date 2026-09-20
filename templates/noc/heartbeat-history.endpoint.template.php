<?php
// BRD-035: Heartbeat history endpoint.
//
// PHP 5.5 compatible.
// This file is a template.
// Deployment-specific values are injected outside version control.

$baseDir = dirname($_SERVER['SCRIPT_FILENAME']);

require_once '__TELEMETRY_CONFIG_FILE__';
require_once "$baseDir/lib/heartbeat-history-endpoint.php";
require_once "$baseDir/lib/heartbeat-history.php";

if (!isset($HOST_TOKENS) || !isset($DATA_DIR)) {
    $response = new TelemetryResponse(
        500,
        'server configuration error'
    );
} else {
    $endpoint = new HeartbeatHistoryEndpoint(
        new Authenticator($HOST_TOKENS),
        new HeartbeatHistory($DATA_DIR),
        HEARTBEAT_HISTORY_MAX_LATEST,
        HEARTBEAT_HISTORY_MAX_RANGE
    );

    $response = $endpoint->handle(
        $_SERVER,
        $_POST
    );
}

http_response_code($response->status());
header('Content-Type: ' . $response->content_type());
echo $response->body();
