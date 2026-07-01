<?php

function append_record($file, $record): void
{
    $line = json_encode($record) . "\n";

    if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('failed to append record: ' . $file);
    }
}