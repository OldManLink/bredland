<?php

require_once __DIR__ . '/telemetry-response.php';
require_once __DIR__ . '/authenticator.php';
require_once __DIR__ . '/field-selector.php';
require_once __DIR__ . '/schema-loader.php';
require_once __DIR__ . '/record-builder.php';
require_once __DIR__ . '/telemetry-storage.php';

class TelemetryEndpoint {
    private $authenticator;
    private $fieldSelector;
    private $schemaLoader;
    private $recordBuilder;
    private $telemetryStorage;

    public function __construct($authenticator, $schemaLoader, $telemetryStorage) {
        $this->authenticator = $authenticator;
        $this->fieldSelector = new FieldSelector();
        $this->schemaLoader = $schemaLoader;
        $this->recordBuilder = new RecordBuilder();
        $this->telemetryStorage = $telemetryStorage;
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

        try {
            $selectedFields = $this->fieldSelector->select(
                $fields,
                $post
            );

            $schema = $this->schemaLoader->load($host);
        } catch (InvalidArgumentException $e) {
            return new TelemetryResponse(
                400,
                $e->getMessage()
            );
        }

        $uptime = $this->required_param($post, 'uptime');

        if ($uptime === null) {
            return new TelemetryResponse(
                400,
                'missing parameter: uptime'
            );
        }

        $ttl = $this->required_param($post, 'ttl');

        if ($ttl === null) {
            return new TelemetryResponse(
                400,
                'missing parameter: ttl'
            );
        }

        $source = array_merge(
            array(
                'ts' => gmdate('Y-m-d\TH:i:s\Z'),
                'uptime' => $uptime,
                'ttl' => $ttl,
                'remote_addr' => isset($server['REMOTE_ADDR'])
                    ? $server['REMOTE_ADDR']
                    : ''
            ),
            $selectedFields
        );

        try {
            $record = $this->recordBuilder->build(
                $schema,
                $source
            );
        } catch (InvalidArgumentException $e) {
            return new TelemetryResponse(
                400,
                $e->getMessage()
            );
        }

        $this->telemetryStorage->append(
            $host,
            gmdate('Y-m-d'),
            $record
        );

        return new TelemetryResponse(
            200,
            'ok'
        );
    }

    private function required_param($post, $name) {
        if (!isset($post[$name]) || $post[$name] === '') {
            return null;
        }

        return (string) $post[$name];
    }
}
