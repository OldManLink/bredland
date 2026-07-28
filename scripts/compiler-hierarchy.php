<?php
$compilerRoot = dirname(__DIR__) . '/templates/noc/lib/compiler';

foreach (glob($compilerRoot . '/*.php') as $file) {
    require_once $file;
}

function direct_interfaces($className) {
    $reflection = new ReflectionClass($className);
    $interfaces = $reflection->getInterfaceNames();
    $parent = $reflection->getParentClass();

    if ($parent) {
        $interfaces = array_diff(
            $interfaces,
            $parent->getInterfaceNames()
        );
    }

    foreach ($interfaces as $interface) {
        $reflection = new ReflectionClass($interface);
        $interfaces = array_diff(
            $interfaces,
            $reflection->getInterfaceNames()
        );
    }

    return array_values($interfaces);
}

function implementing_classes($interfaceName) {
    $classes = array();

    foreach (get_declared_classes() as $className) {
        $reflection = new ReflectionClass($className);

        if ($reflection->isUserDefined() &&
            $reflection->implementsInterface($interfaceName)) {
            $classes[] = $className;
        }
    }

    sort($classes);

    return $classes;
}

$interfaces = array();

foreach (get_declared_interfaces() as $interfaceName) {
    $reflection = new ReflectionClass($interfaceName);

    if ($reflection->isUserDefined()) {
        $interfaces[] = $interfaceName;
    }
}

sort($interfaces);

foreach ($interfaces as $interfaceName) {
    echo $interfaceName . PHP_EOL;

    $classes = implementing_classes($interfaceName);
    $count = count($classes);

    foreach ($classes as $index => $className) {
        $branch = $index === $count - 1 ? '└── ' : '├── ';
        echo $branch . $className . PHP_EOL;
    }

    echo PHP_EOL;
}