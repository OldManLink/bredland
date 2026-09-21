<?php

class Timestamp {
    public static function is_valid($value) {
        if (!preg_match(
            '/^([0-9]{4})-([0-9]{2})-([0-9]{2})T' .
            '([0-9]{2}):([0-9]{2}):([0-9]{2})Z$/',
            $value,
            $parts
        )) {
            return false;
        }

        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $day = (int) $parts[3];
        $hour = (int) $parts[4];
        $minute = (int) $parts[5];
        $second = (int) $parts[6];

        return checkdate($month, $day, $year)
            && $hour <= 23
            && $minute <= 59
            && $second <= 59;
    }
}
