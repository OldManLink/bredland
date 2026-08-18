<?php

require_once __DIR__ . '/assertion-failed.php';

class TestSuiteRunner {
    private $suiteName;
    private $passed = 0;
    private $failed = 0;
    private $quiet = false;

    public function __construct($suiteName)
    {
        $this->suiteName = $suiteName;
        $this->quiet = in_array('-q', $_SERVER['argv'], true);
    }

    public function test($description, $test)
    {
        $this->output("→ $description\n");
        try {
            call_user_func($test);
            ++$this->passed;
            $this->output("✅ $description\n");
        } catch (AssertionFailed $e) {
            ++$this->failed;
            fwrite(STDOUT, "❌ $description\n");
            fwrite(STDOUT, $e->getMessage() . "\n");
        }
    }

    private function output($message)
    {
        if (!$this->quiet) {
            fwrite(STDOUT, $message);
        }
    }

    public function finish()
    {
        $total = $this->passed + $this->failed;

        fwrite(
            STDOUT,
            "$this->suiteName: $total tests run, " .
            "$this->passed passed, $this->failed failed\n"
        );

        if ($this->failed > 0) {
            exit(1);
        }
    }
}
