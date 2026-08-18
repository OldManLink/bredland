#!/usr/bin/env php
<?php

$repoRoot = dirname(dirname(__DIR__));
$libRoot = $repoRoot . '/templates/noc/lib';
$analysisDir = $repoRoot . '/build/analysis';
$outputFile = $analysisDir . '/php-dependencies.dot';

if (!is_dir($analysisDir) && !mkdir($analysisDir, 0777, true)) {
    fwrite(STDERR, "Failed to create analysis directory: $analysisDir\n");
    exit(1);
}

$phpFiles = php_files_below($libRoot);
$edges = array();

foreach ($phpFiles as $file) {
    $source = relative_path($libRoot, $file);

    foreach (required_files($file) as $requiredFile) {
        $target = canonical_path($requiredFile);

        if (!is_below($libRoot, $target)) {
            continue;
        }

        if (!file_exists($target)) {
            fwrite(
                STDERR,
                "Required file does not exist: $target\n" .
                "Referenced from: $file\n"
            );
            exit(1);
        }

        $edges[] = array(
            $source,
            relative_path($libRoot, $target)
        );
    }
}

usort($edges, function ($left, $right) {
    $sourceComparison = strcmp($left[0], $right[0]);

    if ($sourceComparison !== 0) {
        return $sourceComparison;
    }

    return strcmp($left[1], $right[1]);
});

$lines = array(
    'digraph php_dependencies {',
    '  rankdir=LR;'
);

foreach ($edges as $edge) {
    $lines[] =
        '  "' . dot_escape($edge[0]) . '" -> "' .
        dot_escape($edge[1]) . '";';
}

$lines[] = '}';
$report = implode("\n", $lines) . "\n";

if (file_put_contents($outputFile, $report) === false) {
    fwrite(STDERR, "Failed to write analysis report: $outputFile\n");
    exit(1);
}

echo "✅ Wrote analysis report to $outputFile\n";

function php_files_below($root) {
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        if (strtolower($fileInfo->getExtension()) !== 'php') {
            continue;
        }

        $files[] = $fileInfo->getPathname();
    }

    sort($files);

    return $files;
}

function required_files($file) {
    $tokens = token_get_all(file_get_contents($file));
    $requiredFiles = array();
    $count = count($tokens);

    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];

        if (!is_array($token) ||
            ($token[0] !== T_REQUIRE &&
             $token[0] !== T_REQUIRE_ONCE &&
             $token[0] !== T_INCLUDE &&
             $token[0] !== T_INCLUDE_ONCE)) {
            continue;
        }

        $expressionTokens = array();

        for ($index++; $index < $count; $index++) {
            $expressionToken = $tokens[$index];

            if ($expressionToken === ';') {
                break;
            }

            $expressionTokens[] = $expressionToken;
        }

        $requiredFiles[] = resolve_path_expression(
            $expressionTokens,
            dirname($file),
            $file
        );
    }

    return $requiredFiles;
}

function resolve_path_expression($tokens, $currentDir, $sourceFile) {
    $parser = new PathExpressionParser(
        remove_ignorable_tokens($tokens),
        $currentDir,
        $sourceFile
    );

    return $parser->parse();
}

function remove_ignorable_tokens($tokens) {
    $result = array();

    foreach ($tokens as $token) {
        if (is_array($token) &&
            ($token[0] === T_WHITESPACE ||
             $token[0] === T_COMMENT ||
             $token[0] === T_DOC_COMMENT)) {
            continue;
        }

        $result[] = $token;
    }

    return $result;
}

class PathExpressionParser {
    private $tokens;
    private $index;
    private $currentDir;
    private $sourceFile;

    public function __construct($tokens, $currentDir, $sourceFile) {
        $this->tokens = array_values($tokens);
        $this->index = 0;
        $this->currentDir = $currentDir;
        $this->sourceFile = $sourceFile;
    }

    public function parse() {
        $value = $this->parse_concat();

        if ($this->index !== count($this->tokens)) {
            $this->unsupported();
        }

        return canonical_path($value);
    }

    private function parse_concat() {
        $value = $this->parse_term();

        while ($this->peek() === '.') {
            $this->index++;
            $value .= $this->parse_term();
        }

        return $value;
    }

    private function parse_term() {
        $token = $this->peek();

        if ($token === null) {
            $this->unsupported();
        }

        if (is_array($token) && $token[0] === T_DIR) {
            $this->index++;
            return $this->currentDir;
        }

        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $this->index++;
            return decode_php_string_literal($token[1]);
        }

        if (is_array($token) &&
            $token[0] === T_STRING &&
            strtolower($token[1]) === 'dirname') {
            $this->index++;

            if ($this->peek() !== '(') {
                $this->unsupported();
            }

            $this->index++;
            $value = $this->parse_concat();

            if ($this->peek() !== ')') {
                $this->unsupported();
            }

            $this->index++;

            return dirname($value);
        }

        if ($token === '(') {
            $this->index++;
            $value = $this->parse_concat();

            if ($this->peek() !== ')') {
                $this->unsupported();
            }

            $this->index++;
            return $value;
        }

        $this->unsupported();
    }

    private function peek() {
        return isset($this->tokens[$this->index])
            ? $this->tokens[$this->index]
            : null;
    }

    private function unsupported() {
        $expression = '';

        foreach ($this->tokens as $token) {
            $expression .= is_array($token) ? $token[1] : $token;
        }

        fwrite(
            STDERR,
            "Unsupported include/require expression in {$this->sourceFile}:\n" .
            "  $expression\n"
        );
        exit(1);
    }
}

function decode_php_string_literal($literal) {
    $quote = substr($literal, 0, 1);
    $value = substr($literal, 1, -1);

    if ($quote === "'") {
        return str_replace(
            array("\\\\", "\\'"),
            array("\\", "'"),
            $value
        );
    }

    return stripcslashes($value);
}

function canonical_path($path) {
    $absolute = substr($path, 0, 1) === '/'
        ? $path
        : getcwd() . '/' . $path;

    $parts = explode('/', $absolute);
    $resolved = array();

    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            array_pop($resolved);
            continue;
        }

        $resolved[] = $part;
    }

    return '/' . implode('/', $resolved);
}

function is_below($root, $path) {
    $root = rtrim(canonical_path($root), '/') . '/';
    $path = canonical_path($path);

    return strpos($path . '/', $root) === 0;
}

function relative_path($root, $path) {
    $root = rtrim(canonical_path($root), '/') . '/';
    $path = canonical_path($path);

    if (strpos($path, $root) !== 0) {
        throw new RuntimeException(
            "Path is outside root: $path (root: $root)"
        );
    }

    return substr($path, strlen($root));
}

function dot_escape($value) {
    return str_replace(
        array("\\", "\""),
        array("\\\\", "\\\""),
        $value
    );
}
