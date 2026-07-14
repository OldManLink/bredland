<?php

function get_exports() {
    return array(
        'formatters' => array(
            'display_memory' => array(
                'valueTypes' => array('integer' => true),
            ),
            'display_uptime' => array(
                'valueTypes' => array('integer' => true),
            ),
        )
    );
}

