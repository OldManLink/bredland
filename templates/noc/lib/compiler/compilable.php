<?php
/**
 * An object that can be constructed from declarative client configuration.
 *
 * Implementations provide a compile() method that validates their part of the
 * input configuration and, on success, produces the corresponding strongly
 * validated compiler object. This keeps configuration parsing and validation
 * at the boundary so runtime code can operate on trusted objects.
 *
 * Not every compiler value needs to be Compilable. MethodVal is deliberately
 * excluded because it is created by Action while compiling a validated method
 * name and its argument contract; it is not compiled independently from the
 * client configuration.
 */
interface Compilable {
    public static function compile($definition, $schema, $path);
}