<?php

/**
 * Version file for component local_gradabledatasender.
 *
 * @package         local_gradabledatasender
 * @author          Lucas Catalan <catalan.munoz.l@gmail.com>
 */

defined('MOODLE_INTERNAL') || die;


$componentname = 'local_gradabledatasender';


// Default for users that have site config.
if ($hassiteconfig) {

    // Add the category to the local plugin branch.
    $ADMIN->add('localplugins', new \admin_category($componentname, get_string('pluginname', $componentname)));

    // Create a settings page for local_bcn_mailer.
    $settingspage = new \admin_settingpage('gradabledatasender', get_string('pluginname', $componentname));

    $quiz_options = [];

    $quizessql = "SELECT id, name FROM {quiz} order by id asc";
    $quizes = $DB->get_records_sql($quizessql);

    foreach ($quizes as $quiz) {
        $quiz_options[$quiz->id] = $quiz->id . ' - ' .$quiz->name;
    }

    // Make a container for all of the settings for the settings page.
    $settings = [];

    $settings[] = new admin_setting_configtext(
        'gradabledatasender/destiny_endpoint',
        new lang_string('destiny_endpoint', $componentname),
        new lang_string('destiny_endpoint', $componentname),
        ''
    );

    $settings[] = new admin_setting_configtext(
        'gradabledatasender/endpoint_username',
        new lang_string('endpoint_username', $componentname),
        new lang_string('endpoint_username', $componentname),
        ''
    );

    $settings[] = new admin_setting_configpasswordunmask(
        'gradabledatasender/endpoint_password',
        new lang_string('endpoint_password', $componentname),
        new lang_string('endpoint_password', $componentname),
        ''
    );

    $settings[] = new admin_setting_configtext(
        'gradabledatasender/current_token',
        new lang_string('current_token', $componentname),
        new lang_string('current_token', $componentname),
        ''
    );

    $settingspage->add(new \admin_setting_configmultiselect(
        'gradabledatasender/specificied_quizes',
        get_string('specificied_quizes', $componentname),
        get_string('specificied_quizes', $componentname),
        [],
        $quiz_options
    ));

    // Add all the settings to the settings page.
    foreach ($settings as $setting) {
        $settingspage->add($setting);
    }

    // Add the settings page to the nav tree.
    $ADMIN->add($componentname, $settingspage);


}
