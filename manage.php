<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/local/gradabledatasender/lib.php');
require_login();
//require_capability('local/bcn_mailer:can_manage_variabels', context_system::instance());

$PAGE->set_url(new moodle_url('/local/gradabledatasender/test.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('TEST');
$PAGE->set_heading('TEST');
$PAGE->set_pagelayout('admin');
echo $OUTPUT->header();
echo html_writer::tag('h1', 'TEST');

//var_dump(get_config('gradabledatasender'));


var_dump(refresh_token());

$output = shell_exec('ping 20.119.8.50');
$output2 = shell_exec('ping https://servicios-siae.udla.cl');
echo "<pre>$output</pre>";
echo "<pre>$output2</pre>";

//exit;
echo $OUTPUT->footer();
