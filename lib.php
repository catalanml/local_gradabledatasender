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

    // SQL to fetch quiz attempts that are not yet logged in mdl_gradabledatasender_log
    $attempts_sql = "
        WITH mgl_parsed AS (
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(data, '$.email')) AS email,
                CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.quizid')) AS UNSIGNED) AS quizid
            FROM mdl_gradabledatasender_log
            WHERE created BETWEEN 1722470400 AND 1725062400
        ),
        quiz_attempts AS (
            SELECT
                qa.id,
                u.id AS userid,
                u.email,
                qa.quiz,
                q.name AS quiz_name,
                c.fullname AS course_name,
                FROM_UNIXTIME(qa.timefinish, '%d-%m-%Y %H:%i:%s') AS timefinish
            FROM mdl_quiz_attempts qa
            INNER JOIN mdl_user u ON qa.userid = u.id
            INNER JOIN mdl_quiz q ON qa.quiz = q.id
            INNER JOIN mdl_course c ON q.course = c.id
            WHERE qa.quiz IN (3382, 3589, 3580, 3571, 3565, 3581)
                AND qa.timefinish BETWEEN 1722470400 AND 1725062400
        )
        SELECT
            qa.id,
            qa.userid,
            qa.email,
            qa.quiz AS quiz,
            qa.quiz_name,
            qa.course_name,
            qa.timefinish
        FROM quiz_attempts qa
        LEFT JOIN mgl_parsed mp ON qa.email = mp.email AND qa.quiz = mp.quizid
        WHERE mp.email IS NULL
        ORDER BY qa.email;
    ";

    // Fetch all the quiz attempts not yet logged
    $attempts = $DB->get_records_sql($attempts_sql);

    // Loop through each attempt
    foreach ($attempts as $attempt) {

        // Create quiz attempt object
        $attempt_data = \quiz_attempt::create($attempt->id);

        // Fetch the student (user) and quiz data
        $student = $DB->get_record('user', ['id' => $attempt->userid]);
        $quiz_record = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        $cm = get_coursemodule_from_instance('quiz', $quiz_record->id);
        $course = get_course($quiz_record->course);
        $quiz = new quiz($quiz_record, $cm, $course);


        if (!$quiz->has_questions()) {
            // Log error or skip processing if quiz has no questions
            continue;
        }


        $quiz->load_questions();

        $slots = $attempt_data->get_slots();

 
        $tosend = [
            'rut' => $student->username,
            'nombre' => $student->firstname . ' ' . $student->lastname,
            'celular' => $student->phone1 ?: '', 
            'email' => $student->email,
            'curso' => $course->fullname,
            'quizname' => $quiz_record->name,
            'quizid' => $quiz_record->id,
            'completiondate' => $attempt_data->get_submitted_date(),
            'respuestas' => [],
        ];

        $auxcounter = 1;


        foreach ($slots as $slot) {
            $question_data = $attempt_data->get_question_attempt($slot);
            $questionstate = $question_data->get_state()->__toString();


            if ($questionstate !== 'finished') {
                $tosend['respuestas'][$auxcounter] = $questionstate;
                $auxcounter++;
            }
        }


        $record = new stdClass();
        $record->data = json_encode($tosend);
        $record->created = time(); 

        $record_id = $DB->insert_record('gradabledatasender_log', $record);


        $log_record = $DB->get_record('gradabledatasender_log', ['id' => $record_id]);

        send_quiz_data($log_record, $tosend);
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
