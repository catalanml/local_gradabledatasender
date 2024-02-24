<?php
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');

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
    public static function registered_gradable_activity(\mod_quiz\event\attempt_reviewed $event)
    {
        global $DB;

        $data = $event->get_data();

        $student = $DB->get_record('user', array('id' => $data['relateduserid']));

        $reviewedby = $DB->get_record('user', array('id' => $data['userid']));

        $cm = get_coursemodule_from_id('', $data['contextinstanceid']);

        $quiz_record = $DB->get_record('quiz', array('id' => $cm->instance));
        $c = get_course($quiz_record->course);
        $quiz = new quiz($quiz_record, $cm, $c);

        $attempt = \quiz_attempt::create($data['objectid']);

        $quiz->has_questions();

        $quiz->load_questions();
    
        $slots = $attempt->get_slots();

        $toprint = [
            'rut_revisor' => $reviewedby->username,
            'nombre_revisor' => $reviewedby->firstname,
            'rut' => $student->username,
            'nombre' => $student->firstname,
            'celular' => $student->phone1,
            'email' => $student->email,
            'curso' => $c->fullname,
            'quizname' => $quiz_record->name,
            'quizid' => $quiz_record->id,
            'respuestas' => [],
        ];

        foreach ($slots as $slot) {
            $question_data = $attempt->get_question_attempt($slot);
            $toprint['respuestas'][$slot] = $question_data->get_state()->__toString();
        }
        
        $record = new stdClass();
        $record->logstoreid = 1;
        $record->data = json_encode($toprint);
        $record->created = time();

        $DB->insert_record('gradabledatasender_log', $record);
        return true;
       
       
    }
}
