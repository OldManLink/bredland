<?php

require_once __DIR__ . '/assertion-failed.php';

class TestSuiteRunner {
    private $suiteName;
    private $testsPassed = 0;
    private $testsSkipped = 0;
    private $testsFailed = 0;
    private $quiet = false;

    public function __construct($suiteName) {
        $this->suiteName = $suiteName;
        $this->quiet = in_array('-q', $_SERVER['argv'], true);
    }

    public function test($description, $test) {
        $this->output("→ $description\n");
        try {
            call_user_func($test);
            ++$this->testsPassed;
            $this->output("✅ $description\n");
        } catch (AssertionFailed $e) {
            ++$this->testsFailed;
            fwrite(STDOUT, "❌ $description\n");
            fwrite(STDOUT, $e->getMessage() . "\n");
        }
    }

    public function skip($description, $reason, $test) {
        if (!is_callable($test)) {
            throw new InvalidArgumentException(
                "Skipped test must be callable: $description"
            );
        }

        $this->testsSkipped++;

        echo "⚠️ $description — $reason\n";
    }

    private function output($message) {
        if (!$this->quiet) {
            fwrite(STDOUT, $message);
        }
    }

    public function finish() {
        $total =
            $this->testsPassed +
            $this->testsSkipped +
            $this->testsFailed;

        fwrite(
            STDOUT,
            "$this->suiteName: $total tests run, " .
            "$this->testsSkipped skipped, " .
            "$this->testsPassed passed, $this->testsFailed failed\n"
        );

        if ($this->testsFailed > 0) {
            exit(1);
        }
    }
}
