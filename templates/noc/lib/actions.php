<?php

function roll_up($card) {
    $card['height'] = '4.5rem';
    $card['clickAction'] = 'roll_down';

    return $card;
}

function roll_down($card) {
    $card['height'] = '13rem';
    $card['clickAction'] = 'roll_up';

    return $card;
}