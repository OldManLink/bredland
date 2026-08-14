#!/usr/bin/env php
<?php
$repoRoot = dirname(dirname(__DIR__));
$libraryRoot = $repoRoot . '/templates/noc/lib';
$compilerRoot = $libraryRoot . '/compiler';
$analysisDir = $repoRoot . '/build/analysis';

if (!is_dir($analysisDir)) {
    mkdir($analysisDir, 0777, true);
}

$outputFile = $analysisDir . '/compiler-hierarchy.txt';

foreach (glob($compilerRoot . '/*.php') as $file) {
    require_once $file;
}

function path_is_below($path, $root) {
    $realPath = realpath($path);
    $realRoot = realpath($root);

    if ($realPath === false || $realRoot === false) {
        return false;
    }

    return $realPath === $realRoot ||
        strpos($realPath, $realRoot . DIRECTORY_SEPARATOR) === 0;
}

function relative_path($path, $root) {
    $realPath = realpath($path);
    $realRoot = realpath($root);

    if ($realPath === false || $realRoot === false) {
        return basename($path);
    }

    return str_replace(
        DIRECTORY_SEPARATOR,
        '/',
        substr($realPath, strlen($realRoot) + 1)
    );
}

function php_files_below($root) {
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

function compiler_classes($compilerRoot) {
    $classes = array();

    foreach (get_declared_classes() as $className) {
        $reflection = new ReflectionClass($className);
        $fileName = $reflection->getFileName();

        if ($reflection->isUserDefined() &&
            $fileName !== false &&
            path_is_below($fileName, $compilerRoot)) {
            $classes[] = $className;
        }
    }

    sort($classes);

    return $classes;
}

function compiler_interfaces($compilerRoot) {
    $interfaces = array();

    foreach (get_declared_interfaces() as $interfaceName) {
        $reflection = new ReflectionClass($interfaceName);
        $fileName = $reflection->getFileName();

        if ($reflection->isUserDefined() &&
            $fileName !== false &&
            path_is_below($fileName, $compilerRoot)) {
            $interfaces[] = $interfaceName;
        }
    }

    sort($interfaces);

    return $interfaces;
}

function compiler_traits($compilerRoot) {
    $traits = array();

    foreach (get_declared_traits() as $traitName) {
        $reflection = new ReflectionClass($traitName);
        $fileName = $reflection->getFileName();

        if ($reflection->isUserDefined() &&
            $fileName !== false &&
            path_is_below($fileName, $compilerRoot)) {
            $traits[] = $traitName;
        }
    }

    sort($traits);

    return $traits;
}

function class_trait_names($className) {
    $traits = array();
    $reflection = new ReflectionClass($className);

    while ($reflection) {
        foreach ($reflection->getTraitNames() as $traitName) {
            $traits[$traitName] = true;
            collect_nested_traits($traitName, $traits);
        }

        $reflection = $reflection->getParentClass();
    }

    $names = array_keys($traits);
    sort($names);

    return $names;
}

function collect_nested_traits($traitName, &$traits) {
    $reflection = new ReflectionClass($traitName);

    foreach ($reflection->getTraitNames() as $nestedTraitName) {
        if (!isset($traits[$nestedTraitName])) {
            $traits[$nestedTraitName] = true;
            collect_nested_traits($nestedTraitName, $traits);
        }
    }
}

function implementing_classes($interfaceName, $classes) {
    $implementers = array();

    foreach ($classes as $className) {
        $reflection = new ReflectionClass($className);

        if ($reflection->implementsInterface($interfaceName)) {
            $implementers[] = $className;
        }
    }

    sort($implementers);

    return $implementers;
}

function trait_users($traitName, $classes) {
    $users = array();

    foreach ($classes as $className) {
        if (in_array($traitName, class_trait_names($className), true)) {
            $users[] = $className;
        }
    }

    sort($users);

    return $users;
}

function print_relationship_section($title, $relationships) {
    echo $title . PHP_EOL;
    echo str_repeat('-', strlen($title)) . PHP_EOL . PHP_EOL;

    if (empty($relationships)) {
        echo '(none)' . PHP_EOL . PHP_EOL;
        return;
    }

    ksort($relationships);

    foreach ($relationships as $name => $users) {
        sort($users);
        echo $name . PHP_EOL;

        if (empty($users)) {
            echo '└── (none)' . PHP_EOL;
        } else {
            $count = count($users);

            foreach ($users as $index => $user) {
                $branch = $index === $count - 1 ? '└── ' : '├── ';
                echo $branch . $user . PHP_EOL;
            }
        }

        echo PHP_EOL;
    }
}

function print_list_section($title, $items) {
    echo $title . PHP_EOL;
    echo str_repeat('-', strlen($title)) . PHP_EOL . PHP_EOL;

    if (empty($items)) {
        echo '(none)' . PHP_EOL . PHP_EOL;
        return;
    }

    sort($items);

    foreach ($items as $item) {
        echo $item . PHP_EOL;
    }

    echo PHP_EOL;
}

function token_name_after($tokens, $start) {
    $parts = array();
    $count = count($tokens);

    for ($index = $start; $index < $count; $index++) {
        $token = $tokens[$index];

        if (is_array($token)) {
            if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT ||
                $token[0] === T_DOC_COMMENT) {
                if (empty($parts)) {
                    continue;
                }

                break;
            }

            if ($token[0] === T_STRING ||
                (defined('T_NAME_QUALIFIED') && $token[0] === T_NAME_QUALIFIED) ||
                (defined('T_NAME_FULLY_QUALIFIED') && $token[0] === T_NAME_FULLY_QUALIFIED) ||
                (defined('T_NS_SEPARATOR') && $token[0] === T_NS_SEPARATOR)) {
                $parts[] = $token[1];
                continue;
            }
        }

        break;
    }

    if (empty($parts)) {
        return null;
    }

    return ltrim(implode('', $parts), '\\');
}

function source_class_exception_uses($file) {
    $tokens = token_get_all(file_get_contents($file));
    $uses = array();
    $namespace = '';
    $currentClass = null;
    $classBraceDepth = null;
    $pendingClass = null;
    $braceDepth = 0;
    $count = count($tokens);

    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];

        if (is_array($token)) {
            if ($token[0] === T_NAMESPACE) {
                $name = token_name_after($tokens, $index + 1);
                $namespace = $name === null ? '' : $name;
                continue;
            }

            if ($token[0] === T_CLASS) {
                $name = token_name_after($tokens, $index + 1);

                if ($name !== null) {
                    $pendingClass = $namespace === '' ? $name : $namespace . '\\' . $name;
                }

                continue;
            }

            if ($token[0] === T_THROW && $currentClass !== null) {
                for ($lookAhead = $index + 1; $lookAhead < $count; $lookAhead++) {
                    $candidate = $tokens[$lookAhead];

                    if (is_array($candidate) && $candidate[0] === T_NEW) {
                        $exceptionName = token_name_after($tokens, $lookAhead + 1);

                        if ($exceptionName !== null) {
                            if (strpos($exceptionName, '\\') === false && $namespace !== '') {
                                $exceptionName = $namespace . '\\' . $exceptionName;
                            }

                            $uses[$exceptionName][$currentClass] = true;
                        }

                        break;
                    }

                    if ($candidate === ';') {
                        break;
                    }
                }
            }

            continue;
        }

        if ($token === '{') {
            $braceDepth++;

            if ($pendingClass !== null) {
                $currentClass = $pendingClass;
                $classBraceDepth = $braceDepth;
                $pendingClass = null;
            }

            continue;
        }

        if ($token === '}') {
            if ($currentClass !== null && $braceDepth === $classBraceDepth) {
                $currentClass = null;
                $classBraceDepth = null;
            }

            $braceDepth--;
        }
    }

    return $uses;
}

function exception_relationships($compilerRoot, $classes) {
    $relationships = array();
    $knownExceptions = array();

    foreach ($classes as $className) {
        $reflection = new ReflectionClass($className);

        if ($reflection->isSubclassOf('Exception')) {
            $knownExceptions[$className] = true;
            $relationships[$className] = array();
        }
    }

    foreach (php_files_below($compilerRoot) as $file) {
        foreach (source_class_exception_uses($file) as $exceptionName => $users) {
            if (!isset($knownExceptions[$exceptionName])) {
                continue;
            }

            foreach (array_keys($users) as $user) {
                $relationships[$exceptionName][$user] = true;
            }
        }
    }

    foreach ($relationships as $exceptionName => $users) {
        $relationships[$exceptionName] = array_keys($users);
    }

    return $relationships;
}

function standalone_classes($classes) {
    $standalone = array();

    foreach ($classes as $className) {
        $reflection = new ReflectionClass($className);

        if ($reflection->isSubclassOf('Exception')) {
            continue;
        }

        if (!empty($reflection->getInterfaceNames())) {
            continue;
        }

        if (!empty(class_trait_names($className))) {
            continue;
        }

        $standalone[] = $className;
    }

    sort($standalone);

    return $standalone;
}

function source_standalone_functions($file) {
    $tokens = token_get_all(file_get_contents($file));
    $functions = array();
    $braceDepth = 0;
    $classBraceDepths = array();
    $pendingClassLike = false;
    $count = count($tokens);

    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];

        if (is_array($token)) {
            if ($token[0] === T_CURLY_OPEN ||
                $token[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                $braceDepth++;
                continue;
            }

            if ($token[0] === T_CLASS || $token[0] === T_INTERFACE ||
                $token[0] === T_TRAIT) {
                $pendingClassLike = true;
                continue;
            }

            if ($token[0] === T_FUNCTION && empty($classBraceDepths)) {
                $name = token_name_after($tokens, $index + 1);

                if ($name !== null) {
                    $functions[] = $name . '()';
                }
            }

            continue;
        }

        if ($token === '{') {
            $braceDepth++;

            if ($pendingClassLike) {
                $classBraceDepths[] = $braceDepth;
                $pendingClassLike = false;
            }

            continue;
        }

        if ($token === '}') {
            if (!empty($classBraceDepths) &&
                end($classBraceDepths) === $braceDepth) {
                array_pop($classBraceDepths);
            }

            $braceDepth--;
        }
    }

    sort($functions);

    return $functions;
}

function standalone_functions($libraryRoot) {
    $functionsByFile = array();

    foreach (php_files_below($libraryRoot) as $file) {
        $functions = source_standalone_functions($file);

        if (!empty($functions)) {
            $functionsByFile[relative_path($file, $libraryRoot)] = $functions;
        }
    }

    ksort($functionsByFile);

    return $functionsByFile;
}

function print_function_section($functionsByFile) {
    $title = 'Standalone Functions';
    echo $title . PHP_EOL;
    echo str_repeat('-', strlen($title)) . PHP_EOL . PHP_EOL;

    if (empty($functionsByFile)) {
        echo '(none)' . PHP_EOL . PHP_EOL;
        return;
    }

    foreach ($functionsByFile as $file => $functions) {
        echo $file . PHP_EOL;

        $count = count($functions);

        foreach ($functions as $index => $function) {
            $branch = $index === $count - 1 ? '└── ' : '├── ';
            echo $branch . $function . PHP_EOL;
        }

        echo PHP_EOL;
    }
}

$classes = compiler_classes($compilerRoot);

$interfaceRelationships = array();

foreach (compiler_interfaces($compilerRoot) as $interfaceName) {
    $interfaceRelationships[$interfaceName] = implementing_classes(
        $interfaceName,
        $classes
    );
}

$traitRelationships = array();

foreach (compiler_traits($compilerRoot) as $traitName) {
    $traitRelationships[$traitName] = trait_users($traitName, $classes);
}

ob_start();

print_relationship_section('Interfaces', $interfaceRelationships);
print_relationship_section('Traits', $traitRelationships);
print_relationship_section('Exceptions', exception_relationships($compilerRoot, $classes));
print_list_section('Standalone Classes', standalone_classes($classes));
print_function_section(standalone_functions($libraryRoot));

file_put_contents($outputFile, ob_get_clean());

echo "✅ Wrote analysis report to $outputFile\n";