<?php

require_once __DIR__ . '/telemetry-response.php';
require_once __DIR__ . '/authenticator.php';
require_once __DIR__ . '/heartbeat-history-response.php';
require_once __DIR__ . '/timestamp.php';

class HeartbeatHistoryEndpoint {
    private $heartbeatHistory;
    private $maxLatest;
    private $maxRange;

    public function __construct($authenticator, $heartbeatHistory, $maxLatest, $maxRange) {
        $this->authenticator = $authenticator;
        $this->heartbeatHistory = $heartbeatHistory;
        $this->maxLatest = $maxLatest;
        $this->maxRange = $maxRange;
    }

    public function handle($server, $post) {
        if (!isset($server['REQUEST_METHOD']) ||
            $server['REQUEST_METHOD'] !== 'POST') {
            return new TelemetryResponse(
                405,
                'method not allowed'
            );
        }

        $host = $this->required_param($post, 'host');

        if ($host === null) {
            return new TelemetryResponse(
                400,
                'missing parameter: host'
            );
        }

        $token = $this->required_param($post, 'token');

        if ($token === null) {
            return new TelemetryResponse(
                400,
                'missing parameter: token'
            );
        }

        if (!$this->authenticator->authenticate($host, $token)) {
            return new TelemetryResponse(
                403,
                'forbidden'
            );
        }

        $latest = $this->required_param($post, 'latest');
        $from = $this->required_param($post, 'from');
        $to = $this->required_param($post, 'to');

        if ($latest !== null &&
            ($from !== null || $to !== null)) {
            return new TelemetryResponse(
                400,
                'multiple queries'
            );
        }

        if ($from !== null && $to === null) {
            return new TelemetryResponse(
                400,
                'missing parameter: to'
            );
        }

        if ($from === null && $to !== null) {
            return new TelemetryResponse(
                400,
                'missing parameter: from'
            );
        }

        if ($latest !== null) {
            if (!preg_match('/^[1-9][0-9]*$/', $latest)) {
                return new TelemetryResponse(
                    400,
                    'invalid parameter: latest'
                );
            }

            $latest = (int) $latest;

            if ($latest > $this->maxLatest) {
                return new TelemetryResponse(
                    400,
                    'latest exceeds maximum: ' . $this->maxLatest
                );
            }

            $records = $this->heartbeatHistory->latest(
                $host,
                $latest
            );

            return new HeartbeatHistoryResponse(
                200,
                $records
            );
        }

        if ($from !== null && $to !== null) {
            if (!Timestamp::is_valid($from)) {
                return new TelemetryResponse(
                    400,
                    'invalid parameter: from'
                );
            }

            if (!Timestamp::is_valid($to)) {
                return new TelemetryResponse(
                    400,
                    'invalid parameter: to'
                );
            }

            if ($from > $to) {
                return new TelemetryResponse(
                    400,
                    'invalid range'
                );
            }

            try {
                $records = $this->heartbeatHistory->range(
                    $host,
                    $from,
                    $to,
                    $this->maxRange
                );
            } catch (InvalidArgumentException $e) {
                return new TelemetryResponse(
                    400,
                    $e->getMessage()
                );
            }

            return new HeartbeatHistoryResponse(
                200,
                $records
            );
        }

        return new TelemetryResponse(
            400,
            'missing query'
        );
    }

    private function required_param($post, $name) {
        if (!isset($post[$name]) || $post[$name] === '') {
            return null;
        }

        return (string) $post[$name];
    }
}