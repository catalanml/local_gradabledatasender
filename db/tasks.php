<?php 
/**
 *
 * @package   local_gradabledatasender
 * @author    Lucas Catalan <catalan.munoz.l@gmail.com>
 */

$tasks = [
    [
        'classname' => 'local_gradabledatasender\task\cron_task',
        'blocking' => 0,
        'minute' => '*/5',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ]
];
