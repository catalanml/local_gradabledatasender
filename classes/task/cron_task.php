<?php

/**
 *
 * @package   local_gradabledatasender
 * @author    Lucas Catalan <catalan.munoz.l@gmail.com>
 */

namespace local_gradabledatasender\task;

use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/gradabledatasender/lib.php');

class cron_task extends \core\task\scheduled_task
{
    /**
     * Get task name
     * @return string
     * @throws coding_exception
     */
    public function get_name()
    {
        return get_string($this->stringname, 'local_gradabledatasender');
    }

    /** @var string $stringname */
    protected $stringname = 'cron_name';

    /**
     * Execute task
     */
    public function execute()
    {

        try {

            $current_token = get_config('gradabledatasender', 'current_token');

            $token = refresh_token();

            if (($token !== false) && ($token !== $current_token)) {
                set_config('current_token', $token, 'gradabledatasender');
                mtrace('Token updated');
            } else {
                mtrace('Token not updated');
                return false;
            }
        } catch (\Throwable $th) {
            var_dump($th);
        }
        
        return true; // Finished OK.
    }
}
