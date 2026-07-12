<?php

class CompilationResult
{
    private $value;
    private $messages;

    public function __construct($value, $messages)
    {
        $this->value = $value;
        $this->messages = $messages;
    }

    public function value() {
        return $this->value;
    }

    public function messages() {
        return $this->messages;
    }

    public function hasErrors() {
        return count($this->messages) > 0;
    }

}

function compilation_success($value) {
    return new CompilationResult($value, array());
}

function compilation_failure($message) {
    return new CompilationResult(
        null,
        array($message)
    );
}
