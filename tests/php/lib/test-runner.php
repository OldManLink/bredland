<?php

require_once __DIR__ . '/assertion-failed.php';

class TestRunner
{
    private $suiteName;
    private $passed = 0;
    private $failed = 0;

    public function __construct($suiteName)
    {
        $this->suiteName = $suiteName;
    }

    public function test($description, $test)
    {
        fwrite(STDOUT, "→ $description\n");

        try {
            call_user_func($test);
            ++$this->passed;
            fwrite(STDOUT, "✅ $description\n");
        } catch (AssertionFailed $e) {
            ++$this->failed;
            fwrite(STDOUT, "❌ $description\n");
            fwrite(STDOUT, $e->getMessage() . "\n");
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
