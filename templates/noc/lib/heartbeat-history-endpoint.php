<?php

require_once __DIR__ . '/telemetry-response.php';
require_once __DIR__ . '/authenticator.php';
require_once __DIR__ . '/heartbeat-history-response.php';
require_once __DIR__ . '/timestamp.php';

class HeartbeatHistoryEndpoint {
    private $heartbeatHistory;
    private $maxPageSize;

    public function __construct($authenticator, $heartbeatHistory, $maxPageSize) {
        $this->authenticator = $authenticator;
        $this->heartbeatHistory = $heartbeatHistory;
        $this->maxPageSize = $maxPageSize;
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
        $startingAt = $this->required_param($post, 'starting_at');

        if ($latest !== null &&
            ($from !== null || $to !== null)) {
            return new TelemetryResponse(
                400,
                'multiple queries'
            );
        }

        if ($startingAt !== null &&
            $latest === null) {
            return new TelemetryResponse(
                400,
                'starting_at requires latest'
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

            if ($startingAt !== null &&
                !Timestamp::is_valid($startingAt)) {
                return new TelemetryResponse(
                    400,
                    'invalid parameter: starting_at'
                );
            }

            $pageSize = min(
                $latest,
                $this->maxPageSize
            );

            $fetchCount = $pageSize;

            if ($latest > $this->maxPageSize) {
                $fetchCount++;
            }

            $records = $this->heartbeatHistory->latest(
                $host,
                $fetchCount,
                $startingAt
            );

            if (count($records) > $pageSize) {
                $next = array_shift($records);

                return $this->paged_response(
                    $records,
                    array(
                        'starting_at' => $next['ts']
                    )
                );
            }

            return $this->paged_response($records);
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

            $records = $this->heartbeatHistory->range(
                $host,
                $from,
                $to,
                $this->maxPageSize
            );

            if (count($records) > $this->maxPageSize) {
                $next = array_shift($records);

                return new HeartbeatHistoryResponse(
                    200,
                    array(
                        'records' => $records,
                        'count' => count($records),
                        'next' => array(
                            'to' => $next['ts']
                        )
                    )
                );
            }

            return new HeartbeatHistoryResponse(
                200,
                array(
                    'records' => $records,
                    'count' => count($records)
                )
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

    private function paged_response($records, $next = null) {
        $body = array(
            'records' => $records,
            'count' => count($records)
        );

        if ($next !== null) {
            $body['next'] = $next;
        }

        return new HeartbeatHistoryResponse(
            200,
            $body
        );
    }
}
