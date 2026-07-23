<?php

function get_exports() {
    return array(
        'formatters' => array(
            'display_memory' => array(
                'value_types' => array('integer' => true),
            ),
            'display_uptime' => array(
                'value_types' => array('integer' => true),
            ),
        )
    );
}

