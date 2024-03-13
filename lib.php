<?php

/**
 *
 * @package   local_gradabledatasender
 * @author    Lucas Catalan <catalan.munoz.l@gmail.com>
 */

define('UDLALOGINWS', '/api/v1.0/login');
define('UDLASENDQUIZ', '/api/v1.0/internal/moodle');

require_once($CFG->dirroot . '/local/gradabledatasender/classes/local_gradabledatasender_curl_manager.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');

defined('MOODLE_INTERNAL') || die;


/**
 * Get the current token from the destiny endpoint
 * Return the token or false if the request fails
 * @return string|bool
 */


function refresh_token()
{

    $curl = new \local_gradabledatasender_curl_manager();

    $destiny_endpoint = get_config('gradabledatasender', 'destiny_endpoint');
    $endpoint_username = get_config('gradabledatasender', 'endpoint_username');
    $endpoint_password = get_config('gradabledatasender', 'endpoint_password');

    print_object($destiny_endpoint);
    print_object($endpoint_username );
    print_object($endpoint_password);

    print_object($destiny_endpoint . UDLALOGINWS);


    $data = [
        'username' => $endpoint_username,
        'password' => $endpoint_password,
    ];

    $headers[] = 'Content-Type: application/json';

    try {
        $wsresult  = $curl->make_request(
            $destiny_endpoint . UDLALOGINWS,
            'POST',
            $data,
            $headers
        );

        print_object($wsresult);

        if ($wsresult->remote_endpoint_status === 200) {
            $curl->close();
            return $wsresult->remote_endpoint_response->data->accessToken;
        } else {
            $curl->close();
            return false;
        }
    } catch (\Throwable $th) {
        return false;
    }
}

function send_historical_data()
{

    global $DB;

    //Valid quiz id
    $valid_quizes = [
        3338,
        3341,
        3347,
        3356,
        3357
    ];

    //for each quiz, get submitted attempts finished from 10-01-2024 00:00(ten of january of 2024) 

    foreach ($valid_quizes as $quizid) {

        $attempts_sql = "SELECT * FROM {quiz_attempts} WHERE quiz = ? AND state = 'finished' AND timefinish > ?";
        $attempts = $DB->get_records_sql($attempts_sql, [$quizid, 1704844800]);

        foreach ($attempts as $attempt) {

            $attempt_data = \quiz_attempt::create($attempt->id);
            $student = $DB->get_record('user', array('id' => $attempt->userid));
            $quiz_record = $DB->get_record('quiz', array('id' => $attempt->quiz));
            $cm = get_coursemodule_from_instance('quiz', $quiz_record->id);
            $c = get_course($quiz_record->course);
            $quiz = new quiz($quiz_record, $cm, $c);

            $attempt = \quiz_attempt::create($attempt->id);

            $quiz->has_questions();

            $quiz->load_questions();

            $slots = $attempt->get_slots();

            $tosend = [
                'rut' => $student->username,
                'nombre' => $student->firstname,
                'celular' => $student->phone1,
                'email' => $student->email,
                'curso' => $c->fullname,
                'quizname' => $quiz_record->name,
                'quizid' => $quiz_record->id,
                'completiondate' => $attempt_data->get_submitted_date(),
                'respuestas' => [],
            ];

            $auxcounter = 1;

            foreach ($slots as $slot) {
                $question_data = $attempt->get_question_attempt($slot);
                $questionstate = $question_data->get_state()->__toString();
                if($questionstate !== 'finished'){
                    $tosend['respuestas'][$auxcounter] =  $questionstate;
                    $auxcounter += 1;
                }
                
            }

            $record = new stdClass();
            $record->data = json_encode($tosend);
            $record->created = time();

            $record_id = $DB->insert_record('gradabledatasender_log', $record);

            $log_record = $DB->get_record('gradabledatasender_log', array('id' => $record_id));

            send_quiz_data($log_record, $tosend);
        }
    }
}

function send_quiz_data(stdClass $log_record, array $tosend)
{
    global $DB;

    $curl = new \local_gradabledatasender_curl_manager();

    $destiny_endpoint = get_config('gradabledatasender', 'destiny_endpoint') . UDLASENDQUIZ;

    $token = get_config('gradabledatasender', 'token');

    $headers[] = 'Content-Type: application/json';

    try {
        $wsresult  = $curl->make_request(
            $destiny_endpoint,
            'POST',
            $tosend,
            $headers,
            'bearer',
            $token
        );

        if ($wsresult->remote_endpoint_status === 200) {
            $log_record->message = 'Success';
            $log_record->sended = time();
            $DB->update_record('gradabledatasender_log', $log_record);
            $curl->close();
        } else {
            $curl->close();
            $curl = new \local_gradabledatasender_curl_manager();
            $refreshed_token = refresh_token();

            if ($refreshed_token !== false) {
                set_config('token', $refreshed_token, 'gradabledatasender');
                $wsresult  = $curl->make_request(
                    $destiny_endpoint,
                    'POST',
                    $tosend,
                    $headers,
                    'bearer',
                    $refreshed_token
                );

                if ($wsresult->remote_endpoint_status === 200) {
                    $log_record->message = 'Success';
                    $log_record->sended = time();
                    $DB->update_record('gradabledatasender_log', $log_record);
                } else {
                    $log_record->message = 'Failed to send data';
                    $DB->update_record('gradabledatasender_log', $log_record);
                }
            } else {
                $log_record->message = 'Failed to get token';
                $DB->update_record('gradabledatasender_log', $log_record);
            }

            $curl->close();
        }
    } catch (\Throwable $th) {
        $log_record->message = 'WS ERROR';
        $DB->update_record('gradabledatasender_log', $log_record);
    }
}
