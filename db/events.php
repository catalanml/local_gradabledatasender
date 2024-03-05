<?php

defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => 'local_gradabledatasender_observer::registered_gradable_activity',
    ),
);
