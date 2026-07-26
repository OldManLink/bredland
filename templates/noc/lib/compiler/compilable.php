<?php

interface Compilable
{
    public static function compile($definition, $schema, $path);
}