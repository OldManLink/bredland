<?php

function append_record($file, $record)
{
    $line = json_encode($record) . "\n";

    if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('failed to append record: ' . $file);
    }
}

function daily_jsonl_fileName($data_dir, $host, $date)
{
    $safe_host = preg_replace('/[^a-zA-Z0-9_-]/', '_', $host);

    return rtrim($data_dir, '/') . '/' . $safe_host . '-' . $date . '.jsonl';
}

function ensure_data_dir($data_dir)
{
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0700, true);
    }
}