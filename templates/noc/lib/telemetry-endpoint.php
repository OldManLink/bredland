<?php

require_once __DIR__ . '/telemetry-response.php';
require_once __DIR__ . '/authenticator.php';

class TelemetryEndpoint {
    private $authenticator;

    public function __construct($authenticator) {
        $this->authenticator = $authenticator;
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

        $fields = $this->required_param($post, 'fields');

        if ($fields === null) {
            return new TelemetryResponse(
                400,
                'missing parameter: fields'
            );
        }
    
        return null;
    }

    private function required_param($post, $name) {
        if (!isset($post[$name]) || $post[$name] === '') {
            return null;
        }

        return (string) $post[$name];
    }
}
