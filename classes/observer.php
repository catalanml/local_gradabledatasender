<?php

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
require_once($CFG->dirroot . '/local/gradabledatasender/lib.php');

/**
 * Version file for component local_gradabledatasender.
 *
 * @package         local_gradabledatasender
 * @author          Lucas Catalan <catalan.munoz.l@gmail.com>
 */

class local_gradabledatasender_observer
{
    /**
     * Event processor - user_graded
     *
     * @param \mod_quiz\event\attempt_reviewed $event
     * @return bool
     */
    public static function registered_gradable_activity(\mod_quiz\event\attempt_submitted $event)
    {
        global $DB;

        $data = $event->get_data();

        $specified_quizes = get_config('gradabledatasender', 'specificied_quizes');

        $specified_quizes = explode(',', $specified_quizes);

        $cm = get_coursemodule_from_id('quiz', $data['contextinstanceid']);

        $quiz_record = $DB->get_record('quiz', array('id' => $cm->instance));

        if (!in_array($quiz_record->id, $specified_quizes) || $specified_quizes == false ) {
            return false;
        }

        $student = $DB->get_record('user', array('id' => $data['relateduserid']));

        $c = get_course($quiz_record->course);
        $quiz = new quiz($quiz_record, $cm, $c);

        $attempt = \quiz_attempt::create($data['objectid']);

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
            'completiondate' => $data['timecreated'],
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

        return true;
    }
}
