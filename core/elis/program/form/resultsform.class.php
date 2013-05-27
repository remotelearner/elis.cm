<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('lib.php');
require_once elispm::file('form/cmform.class.php');
require_once elispm::lib('resultsengine.php');
require_once elispm::lib('data/track.class.php');
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/resultsengine.class.php');

/**
 * the form element for curriculum
 */
class cmEngineForm extends cmform {
    const LANG_FILE = 'elis_program';

    // Names used in lookup for language strings
    public $types = array(
        ACTION_TYPE_TRACK   => 'track',
        ACTION_TYPE_CLASS   => 'class',
        ACTION_TYPE_PROFILE => 'profile'
    );

    // Type of form element used to select value for each action type
    public $rowtypes = array(
        ACTION_TYPE_TRACK   => 'picklist',
        ACTION_TYPE_CLASS   => 'picklist',
        ACTION_TYPE_PROFILE => 'doubleselect',
    );

    // Prefixes used by tables
    protected $prefixes = array(
        ACTION_TYPE_TRACK   => 'track',
        ACTION_TYPE_CLASS   => 'class',
        ACTION_TYPE_PROFILE => 'profile'
    );

    // The names of the form elements on each row of the table
    protected $settings = array('min','max','selected','value');

    // Form html
    protected $_html = array();

    // Layout switching
    protected $_layout = 'default';

    protected $_submitted_data = '';


    /**
     * defines items in the form
     */
    public function definition() {

        global $PAGE;

        $configData = array('title');

        $PAGE->requires->css('/elis/program/js/results_engine/jquery-ui-1.8.16.custom.css', true);
        $PAGE->requires->js('/elis/program/js/results_engine/jquery-1.6.2.min.js', true);
        $PAGE->requires->js('/elis/program/js/results_engine/jquery-ui-1.8.16.custom.js', true);
        $PAGE->requires->js('/elis/program/js/results_engine/results_selection.js', true);
        $PAGE->requires->js('/elis/program/js/dhtmltable.js', true);

        $formid   = $this->_form->_attributes['id'];
        $settings = implode(',', $this->settings);

        foreach ($this->prefixes as $type => $prefix) {
            $typename = $this->types[$type];
            $js = "{$prefix}_object = new dhtml_table(\"$formid\", \"$prefix\",\"$settings\");"
                . "{$prefix}_object.set_add_button(\"id_{$typename}_add\");"
                . "{$prefix}_object.set_footer_rows(0);"
                . "{$prefix}_object.set_header_rows(1);"
                . "{$prefix}_object.set_id_prefix('id_');"
                . "{$prefix}_object.set_table(\"{$typename}_selection_table\")";
            $PAGE->requires->js_init_code($js);
        }

        $this->defineActivation();
        $this->defineResults();

        $submitlabel = get_string('savechanges');
        $mform =& $this->_form;

        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitlabel);
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }

    /**
     * Define the activation section of the form.
     *
     * @uses $CFG Get url root.
     * @uses $DB Look up course completions.
     */
    protected function defineActivation() {
        global $CFG, $DB;

        $grades = array(0 => get_string('results_class_grade', self::LANG_FILE));
        $dates  = array(
            RESULTS_ENGINE_AFTER_START => get_string('results_after_class_start', self::LANG_FILE),
            RESULTS_ENGINE_BEFORE_END  => get_string('results_before_class_end', self::LANG_FILE),
            RESULTS_ENGINE_AFTER_END   => get_string('results_after_class_end', self::LANG_FILE)
        );

        $conditions = array('courseid' => $this->_customdata['courseid']);

        $completions = $DB->get_recordset(coursecompletion::TABLE, $conditions);
        foreach ($completions as $completion) {
            $grades[$completion->id] = $completion->name;
        }
        unset($completions);

        $page = 'crsenginestatus';
        if ($this->_customdata['enginetype'] == 'class') {
            $page = 'clsenginestatus';
        }

        $this->_submitted_data = $this->get_submitted_data();

        $reporturl = $CFG->wwwroot .'/elis/program/index.php?s='. $page .'&amp;id='. $this->_customdata['id'];

        $activaterule    = get_string('results_activate_this_rule', self::LANG_FILE);
        $activationrules = get_string('results_activation_rules', self::LANG_FILE);
        $criterion       = get_string('results_criterion', self::LANG_FILE);
        $days            = get_string('days');
        $eventtrigger    = get_string('results_event_trigger', self::LANG_FILE);
        $executemanually = get_string('results_execute_manually', self::LANG_FILE);
        $gradeset        = get_string('results_when_student_grade_set', self::LANG_FILE);
        $on              = get_string('results_on', self::LANG_FILE);
        $manualtrigger   = get_string('results_manual_trigger', self::LANG_FILE);
        $selectgrade     = get_string('results_select_grade', self::LANG_FILE);
        $statusreport    = get_string('results_engine_status_report', self::LANG_FILE);
        $uselocked       = get_string('results_use_locked_grades',self::LANG_FILE);

        $mform =& $this->_form;

        $mform->addElement('header', 'statusreport');
        $mform->addElement('html', '<a href="'. $reporturl .'">'. $statusreport .'</a>');
        $mform->addElement('header', 'activationrules', $activationrules);

        $mform->addElement('hidden', 'rid', $this->_customdata['rid']);
        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid']);

        $attributes = array('onchange' => 'toggleform(this);', 'group' => null);
        $active= array();
        $active[] = $mform->createElement('advcheckbox', 'active', '', $activaterule, $attributes);
        $mform->addGroup($active, '', '', ' ', false);
        $mform->setType('active', PARAM_BOOL);

        $attributes = array('group' => null);
        if (! (array_key_exists('active', $this->_customdata) && $this->_customdata['active'])) {
            $attributes = array('disabled' => 'disabled', 'group' => null);
        }

        $exists = array_key_exists('eventtriggertype', $this->_customdata);
        if ($exists && ($this->_customdata['eventtriggertype'] == RESULTS_ENGINE_MANUAL)) {
            $settings = 'height=200,width=500,top=0,left=0,menubar=0,location=0,scrollbars,'
                      . 'resizable,toolbar,status,directories=0,fullscreen=0,dependent';
            $url = $CFG->wwwroot .'/elis/program/resultsprocesspopup.php?id='. $this->_customdata['contextid'];
            $jsondata = array('url'=>$url,'name'=>'resultspopup','options'=>$settings);
            $jsondata = json_encode($jsondata);
            $options  = $attributes;
            $options['onclick'] = "return openpopup(null,$jsondata);";

            $execute = array();
            $execute[] = $mform->createElement('button', 'executebutton', $executemanually, $options);
            $mform->addGroup($execute, '', '', ' ', false);
        }

        $mform->addElement('html', '<fieldset class="engineform">');
        $mform->addElement('html', '<legend>'. $eventtrigger .'</legend>');

        $attributes['group'] = null;

        $grade = array();
        $grade[] = $mform->createElement('radio', 'eventtriggertype', '', $gradeset, RESULTS_ENGINE_GRADE_SET, $attributes);
        $grade[] = $mform->createElement('advcheckbox', 'lockedgrade', '', $uselocked, $attributes);

        $date = array();
        $date[] = $mform->createElement('radio', 'eventtriggertype', '', $on, RESULTS_ENGINE_SCHEDULED, $attributes);
        $date[] = $mform->createElement('text', 'days', '', 'size="2"', $attributes);
        $date[] = $mform->createElement('select', 'triggerstartdate', '', $dates, $attributes);

        $manual = array();
        $manual[] = $mform->createElement('radio', 'eventtriggertype', '', $manualtrigger, RESULTS_ENGINE_MANUAL, $attributes);

        $mform->setDefaults(array('eventtriggertype' => RESULTS_ENGINE_MANUAL));

        $mform->addGroup($grade, 'gradeevent', '', ' ', false);
        $mform->addGroup($date, 'dateevent', '', array(' ', ' '. $days .' '), false);
        $mform->addGroup($manual, 'manualevent', '', ' ', false);

        $mform->setType('locked', PARAM_BOOL);
        $mform->addElement('html', '</fieldset>');


        $mform->addElement('html', '<fieldset class="engineform">');
        $mform->addElement('html', '<legend>'. $criterion .'</legend>');

        $grade = array();
        $grade[] = $mform->createElement('select', 'criteriatype', '', $grades, $attributes);

        $mform->addElement('html', $selectgrade .'<br />');
        $mform->addGroup($grade);

        $mform->addElement('html', '</fieldset>');
    }

    /**
     * Define the results section of the form.
     */
    protected function defineResults() {

        $result          = get_string('results_result', self::LANG_FILE);
        $addscorerange   = get_string('results_add_another_score_btn', self::LANG_FILE);

        $mform =& $this->_form;

        $assign = array(
            ACTION_TYPE_CLASS   => get_string('results_assign_to_class', self::LANG_FILE),
            ACTION_TYPE_PROFILE => get_string('results_assign_to_profile', self::LANG_FILE),
            ACTION_TYPE_TRACK   => get_string('results_assign_to_track', self::LANG_FILE),
        );
        $cache = array(
            ACTION_TYPE_CLASS   => array(),
            ACTION_TYPE_PROFILE => array(),
            ACTION_TYPE_TRACK   => array(),
        );

        $cache[$this->_customdata['actiontype']] = $this->format_cache_data();

        $rid = $this->_customdata['rid'];

        $attributes = array();
        if (! (array_key_exists('active', $this->_customdata) && $this->_customdata['active'])) {
            $attributes = array('disabled' => 'disabled');
        }

        $mform->addElement('hidden', 'aid');
        $mform->addElement('hidden', 'actioncache');
        $mform->addElement('hidden', 'result_type_id', $this->_customdata['actiontype'], 'id="result_type_id"');

        $mform->addElement('html', '<fieldset class="engineform">');

        // Accordion implementation
        $mform->addElement('html', '<div class="engineform">');
        $mform->addElement('html', '<div id="accordion">');

        foreach ($this->types as $type => $typename) {

            // Add track score range
            $mform->addElement('html', '<div>');
            $mform->addElement('html', '<h3>');
            $mform->addElement('html', '<a href="#">'.$assign[$type].'</a>');
            $mform->addElement('html', '</h3>');

            // Create assign to table elements
            $mform->addElement('html', '<div>');

            $this->setup_table_type($mform, $type, $rid, $cache[$type]);

            $mform->addElement('button', $typename .'_add', $addscorerange, $attributes);

            $mform->addElement('html', '</div>');

            $mform->addElement('html', '</div>');
        }

        $mform->addElement('html', '</div>');
        $mform->addElement('html', '</div>');

        $mform->addElement('html', '</fieldset>');

    }

    function check_unique($table, $field, $value, $id) {
        global $DB;
        return !$DB->record_exists_select($table, "$field = ? AND id <> ?", array($value, $id));
    }

    /**
     * Validation function, validates the form.
     *
     * @param array $data  The form data
     * @param array $files The form files
     * @return array An array of error strings.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $actiontype = ACTION_TYPE_TRACK;
        if (!empty($data['result_type_id'])) {
            $actiontype = $data['result_type_id'];
        }

        $errors = array_merge($errors, $this->validate_fold($actiontype, $data));

        return $errors;
    }

    /**
     * Validate score min/max values
     *
     * @param  mixed  $data  The form data with score
     * @param  string $key   The score value data key to validate
     * @return bool   true for valid score, false for invalid
     */
    function valid_score($data, $key) {
        return(isset($data[$key]) && is_numeric($data[$key]));
    }

    /**
     * Validate Fold
     *
     * @param int   $actiontype The action type of the fold we're validating
     * @param array $data       The form data in array format
     * @return array An array of error strings
     */
    function validate_fold($actiontype, $data) {
        $errors = array();
        $ranges = array();
        $parsed = array();

        $langsuffix = $this->types[$actiontype];
        $prefix = $langsuffix .'_';

        // Fallback check, if add another range button pushed skip vaidation.
        // Otherwise validate and make sure all rows have been filled correctly out
        if (!array_key_exists($prefix .'add', $data)) {
            // Iterate through the submitted values.
            // Existing data has the key track_<number>_min/max/etc.
            foreach ($data as $key => $value) {
                $error = '';

                if (false === strpos($key, $prefix)) {
                    continue;
                }

                // Extract the element unique id
                $parts      = explode('_', $key);
                $id         = $parts[1];
                if (array_key_exists($id, $parsed)) {
                    continue;
                }

                $keyprefix  = $prefix . $id;
                $keymin     = $keyprefix .'_min';
                $keymax     = $keyprefix .'_max';
                $keyselect  = $keyprefix .'_selected';
                $keygroup   = $keyprefix .'_score';
                $skip_empty = false;
                // Skip over empty score ranges.
                if (!$this->valid_score($data, $keymin) &&
                    !$this->valid_score($data, $keymax)) {
                    if (!empty($data[$keyselect])) {
                        $error = get_string('results_error_incomplete_score_range', self::LANG_FILE);
                    } else {
                        $skip_empty = true;
                    }
                } else if (!$this->valid_score($data, $keymin) ||
                           !$this->valid_score($data, $keymax)) {
                    $error = get_string('results_error_incomplete_score_range', self::LANG_FILE);

                } else if ((int)$data[$keymin] > (int)$data[$keymax]) {
                    $error = get_string('results_error_min_larger_than_max', self::LANG_FILE);

                } else if (empty($data[$keyselect])) {
                    $error = get_string('results_error_no_'. $langsuffix,
                                        self::LANG_FILE);
                }

                // Only check the ranges if no other error has been found yet.
                if (!$skip_empty && empty($error)) {
                    foreach ($ranges as $range) {
                        if (($range['min'] <= $data[$keymin]) && ($data[$keymin] <= $range['max'])) {
                            $error = get_string('results_error_range_overlap_min', self::LANG_FILE);

                        } else if (($range['min'] <= $data[$keymax]) && ($data[$keymax] <= $range['max'])) {
                            $error = get_string('results_error_range_overlap_max', self::LANG_FILE);

                        } else if (($data[$keymin] <= $range['min']) && ($range['max'] <= $data[$keymax])) {
                            $error = get_string('results_error_range_envelop', self::LANG_FILE);
                        }
                    }

                    $ranges[] = array('min' => $data[$keymin], 'max' => $data[$keymax]);
                }
                if (!empty($error)) {
                    $errors[$keygroup] = $error;
                }
                $parsed[$id] = true;
            }
        }

        return $errors;
    }


    /**
     * Display HTML
     *
     * This function works around the limitations of the moodle forms by printing html
     * directly.  This allows for more custom designed forms.
     */
    function display() {
        if ($this->_layout == 'custom') {
            print(implode("\n", $this->_html));
        } else {
            parent::display();
        }
    }

    /**
     * Get label name
     *
     * @param string $type The type of label to retrieve
     * @param mixed  $id   The id to use to retrieve the label
     * @return string The name of the label.
     * @uses $DB;
     */
    function get_label_name($type, $id) {
        global $DB;

        $name = '';
        switch ($type) {
            case 'track':
                $param = array('id' => $id);
                $name = $DB->get_field(track::TABLE, 'name', $param);

                break;
            case 'class':
                $param = array('id' => $id);
                $name = $DB->get_field(pmclass::TABLE, 'idnumber', $param);

                break;
            case 'profile':
            default:
                $param = array('id' => $id);
                $name = $DB->get_field('elis_field', 'name', $param);

                break;
        }

        return $name;
    }

    /**
     * Get profile fields for select
     *
     * @return array The options for the user profile field.
     * @uses $CFG
     * @uses $DB
     */
    function get_profile_fields() {
        global $CFG, $DB;

        $results   = array('' => get_string('results_select_profile', self::LANG_FILE));

        $sql = 'SELECT f.id, f.name
                  FROM {'. field::TABLE .'} f
            RIGHT JOIN {'. field_contextlevel::TABLE .'} fc
                    ON fc.fieldid = f.id
                 WHERE fc.contextlevel = '. CONTEXT_ELIS_USER;

        $rows = $DB->get_recordset_sql($sql);
        foreach ($rows as $row) {
            if (!empty($row->id)) {
                $results[$row->id] = $row->name;
            }
        }
        unset($rows);

        return $results;
    }

    /**
     * Get profile values
     *
     * @return array The options for the user profile field.
     * @uses $DB
     */
    function get_profile_config($fieldid) {
        global $CFG, $DB;

        $criteria = array('fieldid' => $fieldid, 'plugin' => 'manual');
        $params = $DB->get_field(field_owner::TABLE, 'params', $criteria);

        $config = unserialize($params);

        return $config;
    }
    /**
     * Setup table type
     *
     * @param object $mform     The moodle form object
     * @param int    $type      The type of the table we're setting up
     * @param int    $resultsid The result id
     * @param array  $cache     An array of data to be used in the table
     * @uses $OUTPUT
     */
    protected function setup_table_type($mform, $type, $resultsid = 0, $cache = array()) {
        global $OUTPUT;

        $typename = $this->types[$type];

        $scoreheader = get_string('results_score', self::LANG_FILE);
        $assigntype  = get_string("results_assign_to_{$typename}", self::LANG_FILE);
        $selecttype  = get_string("results_select_{$typename}", self::LANG_FILE);
        $valueheader = '';

        if ($type == ACTION_TYPE_PROFILE) {
            $valueheader        = get_string('results_with_selected_value', self::LANG_FILE);
        }

        $output = '';
        $i = 1;

        $attributes = array('id' => $this->prefixes[$type] .'_cache');
        $mform->addElement('hidden', $this->prefixes[$type] .'_cache', '', $attributes);

        $attributes = array('border' => '1', 'id' => "{$typename}_selection_table");
        $tablehtml = html_writer::start_tag('table', $attributes);
        $tablehtml .= html_writer::start_tag('tr');
        $tablehtml .= html_writer::tag('th', $scoreheader, array('width' => '25%'));
        $tablehtml .= html_writer::tag('th', $assigntype, array('width' => '40%'));
        $tablehtml .= html_writer::tag('th', $valueheader, array('width' => '30%'));
        $tablehtml .= html_writer::end_tag('tr');

        $funcname = "get_assign_to_{$typename}_data";
        if (empty($cache)) {
            $cache = $this->$funcname($resultsid);
        }
        $mform->addElement('html', $tablehtml);

        if (empty($cache) ) {
            //Pre-populate with default values
            $defaults = $this->get_default_data();

            $this->setup_table_type_row($mform, $type, $defaults, true);
        } else {
            // Add score ranges for cached data
            $this->setup_table_type_row($mform, $type, $cache, true);
        }

        // End a table row and second column
        $tablehtml = html_writer::end_tag('table');
        $mform->addElement('html', $tablehtml);

    }

    /**
     * Setup a table row
     *
     * @param object $mform    The moodle form
     * @param int    $type     The datatype of the rows
     * @param array  $dataset  The row data
     * @param bool   $extrarow Whether an extra row should be added.
     * @uses $CFG
     * @uses $OUTPUT
     */
    protected function setup_table_type_row($mform, $type, $dataset = array(), $extrarow) {
        global $CFG, $OUTPUT;

        $typename = $this->types[$type];

        $deletescoretype    = get_string('results_delete_score', self::LANG_FILE);
        $notypeselected     = get_string("results_no_{$typename}_selected", self::LANG_FILE);
        $selecttype         = get_string("results_select_{$typename}", self::LANG_FILE);

        $setdefault = false;
        $prefix = $typename . '_';
        $cache  = 0;
        $defaults = array();
        $configs  = array();

        if ($extrarow) {
            $empty_record = new stdClass();
            $empty_record->min      = '';
            $empty_record->max      = '';
            $empty_record->selected = '';
            $empty_record->value    = '';
            array_push($dataset, $empty_record);
            $cache = 1;
        }

        $i = 0;

        foreach ($dataset as $data) {

            //error checking
            if (!isset($data->min,$data->max)) {
                continue;
            }

            // Start a table row and column
            $tablehtml = html_writer::start_tag('tr');
            $tablehtml .= html_writer::start_tag('td', array('style' => 'text-align:center;'));

            $mform->addElement('html', $tablehtml);

            $group = array();

            // Add minimum field
            $attributes = array('size' => 5, 'maxlength' => 5, 'value' => $data->min);
            if (! (array_key_exists('active', $this->_customdata) && $this->_customdata['active'])) {
                $attributes['disabled'] = 'disabled';
            }
            $group[] = $mform->createElement('text', "{$prefix}{$i}_min", '', $attributes);

            // Add maximum field
            $attributes['value'] = $data->max;
            $group[] = $mform->createElement('text', "{$prefix}{$i}_max", '', $attributes);

            // Add link and icon (Delete link).
            $attributes = array(
                'title' => $deletescoretype,
                'alt' => $deletescoretype,
                'class' => 'elisicon-remove elisiconcolored',
                'onclick' => $this->prefixes[$type].'_object.deleteRow(this); return false;'
            );
            $group[] = $mform->createElement('link', 'delete', '', "#", '', $attributes);

            // Add minimum, maximum and delete to field group
            $mform->addGroup($group, "{$prefix}{$i}_score", '', '', false);

            $tablehtml = html_writer::end_tag('td');
            $tablehtml .= html_writer::start_tag('td', array('style' => 'text-align:center;'));
            $mform->addElement('html', $tablehtml);

            if ($this->rowtypes[$type] == 'picklist') {
                $name = '';
                if (!empty($data->selected)) {
                    $name = $this->get_label_name($typename, $data->selected);
                } else {
                    $name = $notypeselected;
                }

                // Need to add 2 hidden elements - 1 for Moodle forms and 1 For dynamic table.
                $mform->addElement('hidden', "{$prefix}{$i}_selected", $data->selected);

                $attributes     = array('id' => "{$prefix}{$i}_label");
                $attributes = array('size' => 20, 'value' => $name, 'disabled' => 'disabled');

                $attributes     = array(
                    'id'       => "id_{$prefix}{$i}_label",  // Needed for javascript call back
                    'value'    => $name,
                    'name'     => "{$prefix}{$i}_label",
                    'type'     => 'text'
                    // , 'disabled' => 'disabled'
                );
                if (!(array_key_exists('active', $this->_customdata) && $this->_customdata['active'])) {
                    $attributes['disabled'] = 'disabled';
                }
                $output     = html_writer::empty_tag('input', $attributes);

                $attributes     = array(
                    'id' => "id_{$prefix}{$i}_selected", // Needed for javascript call back
                    'value' => $data->selected,
                    'name' => "{$prefix}{$i}_selected",
                    'type' => 'hidden'
                );
                $output    .= html_writer::empty_tag('input', $attributes);

                $url        = "{$typename}selector.php?id=id_{$prefix}{$i}_&callback=add_selection";
                $attributes = array('onclick' => 'show_panel("'.$url.'"); return false;');
                $output    .= html_writer::link('#', $selecttype, $attributes);
                $output    .= html_writer::end_tag('td');
                $output    .= html_writer::start_tag('td');
                $mform->addElement('html', $output);


            } else if ($this->rowtypes[$type] == 'doubleselect') {
                $options = $this->get_profile_fields();

                $attributes = array();
                if (! (array_key_exists('active', $this->_customdata) && $this->_customdata['active'])) {
                    $attributes = array('disabled' => 'disabled');
                }
                $url   = $CFG->wwwroot .'/elis/program/resultsprofileselect.php';
                $frame = "{$prefix}{$i}_frame";
                $value = "{$prefix}{$i}_value";
                $attributes['onchange'] = "replace_content(\"{$url}\",\"{$frame}\",this.value,\"{$value}\");";

                $mform->addElement('select', "{$prefix}{$i}_selected", '', $options, $attributes);

                $attributes = array();
                if (!(array_key_exists('active', $this->_customdata) && $this->_customdata['active'])) {
                    $attributes = array('disabled' => 'disabled');
                }
                $tablehtml  = html_writer::end_tag('td');
                $tablehtml .= html_writer::start_tag('td');
                $tablehtml .= html_writer::start_tag('div', array('id' => $frame));
                $mform->addElement('html', $tablehtml);

                $selected      = $data->selected;
                $selected_form = optional_param("{$prefix}{$i}_selected", 0, PARAM_INT);

                if ($selected_form > 0) {
                    $selected = $selected_form;
                }


                if ($selected != '') {
                    $defaults["{$prefix}{$i}_selected"] = $selected;

                    if (! array_key_exists($selected, $configs)) {
                        $configs[$selected] = $this->get_profile_config($selected);
                    }

                    if ($configs[$selected]['control'] == 'menu') {
                        $choices = explode("\r\n", $configs[$selected]['options']);
                        $options = array_combine($choices, $choices);
                        asort($options);
                        $mform->addElement('select', "{$prefix}{$i}_value", '', $options, $attributes);
                    } else {
                        $mform->addElement('text', "{$prefix}{$i}_value", '', $attributes);
                    }

                    if ($data->value != '') {
                        $defaults["{$prefix}{$i}_value"] = $data->value;
                    }
                }
                $tablehtml  = html_writer::end_tag('div');
                $mform->addElement('html', $tablehtml);
            }

            $tablehtml = html_writer::end_tag('td');
            $tablehtml .= html_writer::end_tag('tr');

            $mform->addElement('html', $tablehtml);
            $mform->setDefaults($defaults);

            $i++;
        }
    }

    protected function format_cache_data() {

        $data = array();

        if (isset($this->_customdata['cache']) && !empty($this->_customdata['cache'])) {

            $cachedata = explode(',', $this->_customdata['cache']);
            $x = 0;
            $i = 0;

            for($i; $i < count($cachedata)-1; $i = $i + 4) {
                $data[$x] = new stdClass();
                $data[$x]->min      = $cachedata[$i];
                $data[$x]->max      = $cachedata[$i+1];
                $data[$x]->selected = $cachedata[$i+2];
                $data[$x]->value    = $cachedata[$i+3];

                $x++;
            }
        }
        return $data;
    }

    /**
     * Get default results engine categories
     *
     * @return array the default categories
     */
    protected function get_default_data() {
        $defaults=get_config('elis_program','results_engine_defaults');
        $defaults=(!empty($defaults))?unserialize($defaults):array();
        foreach ($defaults as $i => $default) {
            if ($i === 'national_average') {
                continue;
            }

            $default=(object)$default;
            $default->selected = '';
            $defaults[$i] = $default;
        }
        return $defaults;
    }

    /**
     * Get results engine actions with track data
     *
     * @param int $resultsid The result id
     * @return array The actions
     * @uses $DB
     */
    protected function get_assign_to_track_data($resultsid = 0) {
        global $DB;

        if (empty($resultsid)) {
            return array();
        }

        $sql = 'SELECT rea.id, rea.minimum AS min, rea.maximum AS max, rea.trackid AS selected, t.name, rea.fielddata as value'
             .' FROM {'.resultsengineaction::TABLE.'} rea'
             .' RIGHT JOIN {'.track::TABLE.'} t ON rea.trackid = t.id'
             .' WHERE rea.resultsid = :resultsengineid AND rea.actiontype=0'
             .' ORDER BY minimum ASC';

        $params = array('resultsengineid' => $resultsid);

        $data = $DB->get_records_sql($sql, $params);

        if (empty($data)) {
            return array();
        }

        return $data;
    }

    /**
     * Get results engine actions with class data
     *
     * @param int $resultsid The result id
     * @return array The actions
     * @uses $DB
     */
    protected function get_assign_to_class_data($resultsid = 0) {
        global $DB;

        if (empty($resultsid)) {
            return array();
        }

        $sql = 'SELECT rea.id, rea.minimum AS min, rea.maximum AS max, rea.classid AS selected,'
             .       ' cls.idnumber AS name, rea.fielddata as value '
             . 'FROM {'.resultsengineaction::TABLE.'} rea '
             . 'RIGHT JOIN {'.pmclass::TABLE.'} cls ON rea.classid = cls.id '
             . 'WHERE rea.resultsid = :resultsengineid AND rea.actiontype=1 '
             . 'ORDER BY minimum ASC';

        $params = array('resultsengineid' => $resultsid);

        $data = $DB->get_records_sql($sql, $params);

        if (empty($data)) {
            return array();
        }

        return $data;
    }

    /**
     * Get results engine actions with profile data
     *
     * @param int $resultsid The result id
     * @return array The actions
     * @uses $DB
     */
    protected function get_assign_to_profile_data($resultsid = 0) {
        global $DB;

        if (empty($resultsid)) {
            return array();
        }

        $sql = 'SELECT rea.id, rea.minimum AS min, rea.maximum AS max, rea.fieldid AS selected,'
             .' f.name AS name, rea.fielddata as value '
             .' FROM {'.resultsengineaction::TABLE.'} rea'
             .' RIGHT JOIN {elis_field} f ON f.id = rea.fieldid'
             .' WHERE rea.resultsid = :resultsengineid AND rea.actiontype=2'
             .' ORDER BY minimum ASC';

        $params = array('resultsengineid' => $resultsid);

        $data = $DB->get_records_sql($sql, $params);

        if (empty($data)) {
            return array();
        }

        return $data;
    }

    /**
     * Definition after data
     *
     * This function will probably be used to setup the default track, class and profile result fields
     *
     * @uses $CFG
     * @uses $COURSE
     */
    public function definition_after_data() {
        return;
    }
}
