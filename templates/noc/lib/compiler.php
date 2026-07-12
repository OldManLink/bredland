<?php

require_once __DIR__ . '/rule.php';

class CompilationResult {
    private $rules;
    private $messages;

    public function __construct($rules, $messages) {
        $this->rules = $rules;
        $this->messages = $messages;
    }

    public function rules() {
        return $this->rules;
    }

    public function messages() {
        return $this->messages;
    }

    public function hasErrors() {
        return count($this->messages) > 0;
    }

    public function addMessages($messages) {
        $this->messages = array_merge($this->messages, $messages);
    }

    public function addRules($rules) {
        $this->rules = array_merge($this->rules, $rules);
    }
}

function compilation_failure($message) {
    return new CompilationResult(
        array(),
        array($message)
    );
}

function compile_rule($ruleJson, $path) {
    if(!is_array($ruleJson)) {
        return compilation_failure("$path must be an object");
    }

    if (!array_key_exists('when', $ruleJson)) {
        return compilation_failure("$path: expected when");
    }

    if (!array_key_exists('then', $ruleJson)) {
        return compilation_failure("$path: expected then");
    }

    $predicateJson = $ruleJson['when'];
    if(!is_array($predicateJson)) {
        return compilation_failure("$path when must be an object");
    }
    $effectJson = $ruleJson['then'];
    if(!is_array($effectJson)) {
        return compilation_failure("$path then must be an object");
    }

    $supportedComparators = array('equals', 'lessThan');
    $comparators = array();
    foreach ($supportedComparators as $comparator) {
        if (array_key_exists($comparator, $predicateJson)) {
            $comparators[] = $comparator;
        }
    }
    if (count($comparators) !== 1) {
        return compilation_failure("$path.when: expected exactly one supported comparator");
    }
    $comparator = $comparators[0];

    if (!array_key_exists('field', $predicateJson)) {
        return compilation_failure("$path.when: expected field");
    }

    if (!is_string($predicateJson['field']) || $predicateJson['field'] === '' ) {
        return compilation_failure("$path.when: field must be a non-empty string");
    }

    $predicateValue = $predicateJson[$comparator];

    if ($comparator === 'equals' &&
        !is_bool($predicateValue) &&
        !is_int($predicateValue) &&
        !is_float($predicateValue) &&
        !is_string($predicateValue)
    ) {
        return compilation_failure("$path.equals: must be a scalar value");
    }

    if ($comparator === 'lessThan' &&
        !is_int($predicateValue) &&
        !is_float($predicateValue)
        ) {
            return compilation_failure("$path.lessThan: must be numeric");
        }

    $predicate = new Predicate(
        $predicateJson['field'],
        $comparator,
        $predicateValue
    );

    if (!array_key_exists('type', $effectJson)) {
        return compilation_failure("$path.then: expected type");
    }
    if ($effectJson['type'] === '') {
        return compilation_failure("$path.then: type must be a non-empty string");
    }

    $supportedEffects = array('message', 'value');
    $effects = array();
    foreach ($supportedEffects as $effect) {
        if (array_key_exists($effect, $effectJson)) {
            $effects[] = $effect;
        }
    }
    if (count($effects) !== 1) {
        return compilation_failure("$path.then: expected exactly one supported effect");
    }
    $effect = $effects[0];
    $effectType = $effectJson['type'];

    if (!in_array($effectType, array('health', 'notification'))) {
        return compilation_failure("$path.then: unsupported effect type $effectType");
    }

    if ($effectType === 'notification' &&
        $effect !== 'message'
    ) {
        return compilation_failure("$path.then: notification requires message");
    }

    if ($effectType === 'health' &&
        $effect !== 'value'
    ) {
        return compilation_failure("$path.then: health requires value");
    }

    $effectArgument = $effectJson[$effect];
    if(!is_string($effectArgument) || $effectArgument === '') {
        return compilation_failure("$path.then: $effect must be a non-empty string");
    }

    if ($effectType === 'health' && !in_array($effectArgument, array('healthy', 'warning', 'critical'))) {
        return compilation_failure("$path.then: $effectType unsupported value: $effectArgument");
    }

    $effect = new Effect($effectType, $effectArgument);

    return new CompilationResult(
        array(new Rule($predicate, $effect)),
        array()
    );

}

function compile_rules($rulesJson) {
    if(!is_array($rulesJson)) {
        return compilation_failure("rules must be an array");
    }

    $result = new CompilationResult(array(), array());

    foreach ($rulesJson as $index => $ruleJson) {
        $ruleResult = compile_rule(
            $ruleJson,
            "rules[$index]"
        );

        $result->addRules($ruleResult->rules());
        $result->addMessages($ruleResult->messages());
    }

    return $result;
}

    //fwrite(STDERR,'>>>>>' . $effectType . '<<<<<');
