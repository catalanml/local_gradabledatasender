<?php
require_once($CFG->dirroot . '/local/gradabledatasender/lib.php');

function xmldb_local_gradabledatasender_upgrade($oldversion)
{

    if ($oldversion < 2024030504) {
 
        //send_historical_data();

        upgrade_plugin_savepoint(true, 2024030504, 'local', 'gradabledatasender');
    }

    
    return true;
}
