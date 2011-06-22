<?php
require_once($CFG->dirroot . '/lib/formslib.php');

class enrole_survey_config_form extends moodleform {
    function definition() {
        global $CFG, $COURSE;
        $mform = & $this->_form;

        $mform->addElement('text', 'title', get_string('config_title', 'block_enrol_survey'));

        $available_intervals = array(0=>'never',
                                     HOURSECS=>'hour',
                                     DAYSECS=>'day',
                                     YEARSECS=>'year');

        $mform->addElement('select', 'cron_time', get_string('config_cron_time', 'block_enrol_survey'), $available_intervals);

        $mform->addElement('submit', 'submitbutton', get_string('savechanges'));
    }
}
?>
