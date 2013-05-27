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
 * @package    elis
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') or die();

require_once(elispm::lib('lib.php'));
require_once(elispm::lib('page.class.php'));
require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('contexts.php')); // TBD.
require_once(elispm::file('pmclasspage.class.php'));

/**
 * Student enrolment page.
 */
class studentpage extends deepsightpage {
    const LANG_FILE = 'elis_program';
    public $data_class = 'student';
    public $pagename = 'stu';
    public $tab_page = 'pmclasspage';
    public $default_tab = 'studentpage';
    public $section = 'curr';
    public $parent_data_class = 'pmclass';
    public $context;

    /**
     * Constructor.
     */
    public function __construct(array $params = null) {
        $this->context = $this->get_context();
        $this->tabs = array( // TBD: 'currcourse_edit' -> 'edit'.
            array(
                'tab_id' => 'currcourse_edit',
                'page' => get_class($this),
                'params' => array('action' => 'currcourse_edit'),
                'name' => get_string('edit', self::LANG_FILE),
                'showtab' => true,
                'showbutton' => true,
                'image' => 'edit'
            ),
            array(
                'tab_id' => 'delete',
                'page' => get_class($this),
                'params' => array('action' => 'delete'),
                'name' => get_string('delete', self::LANG_FILE),
                'showbutton' => true,
                'image' => 'delete'
            ),
        );

        parent::__construct($params);
    }

    /**
     * Get the context for the current class.
     *
     * @return context_elis_class The context object.
     */
    protected function get_context() {
        if (!isset($this->context)) {
            $id = optional_param('id', null, PARAM_INT);
            if (!empty($id)) {
                $this->context = context_elis_class::instance($id);
            }
        }
        return $this->context;
    }

    /**
     * Construct the assigned datatable.
     *
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_assigned_table($uniqid = null) {
        global $DB;
        $classid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=assigned&id='.$classid;
        $table = new deepsight_datatable_enrolled($DB, 'assigned', $endpoint, $uniqid, $classid);
        return $table;
    }

    /**
     * Construct the unassigned datatable.
     *
     * @param string $uniqid A unique ID to assign to the datatable object.
     * @return deepsight_datatable The datatable object.
     */
    protected function construct_unassigned_table($uniqid = null) {
        global $DB;
        $classid = $this->required_param('id', PARAM_INT);
        $endpoint = qualified_me().'&action=deepsight_response&tabletype=unassigned&id='.$classid;
        $table = new deepsight_datatable_enrolments($DB, 'unassigned', $endpoint, $uniqid, $classid);
        return $table;
    }

    /**
     * Whether the user has access to see the main page (assigned list)
     *
     * @return bool Whether the user has access.
     */
    public function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        $pmclasspage = new pmclasspage(array('id' => $id));
        return $pmclasspage->can_do();
    }

    /**
     * Determine whether the current user can enrol students into the class.
     *
     * @return bool Whether the user can enrol users into the class or not.
     */
    public function can_do_add() {
        $id = $this->required_param('id');
        return pmclasspage::can_enrol_into_class($id);
    }

    /**
     * Enrol action permission is handled at the action-object level.
     *
     * @return true
     */
    public function can_do_action_enrol() {
        return true;
    }

    /**
     * Edit action permission is handled at the action-object level.
     *
     * @return true
     */
    public function can_do_action_edit() {
        return true;
    }

    /**
     * Unenrol action permission is handled at the action-object level.
     *
     * @return true
     */
    public function can_do_action_unenrol() {
        return true;
    }

    /**
     * Displays the count of users passed, failed, and not complete above the datatable.
     */
    public function display_default() {
        $classid = $this->required_param('id', PARAM_INT);
        $class = new pmclass($classid);

        echo '<div style="display:inline-block;width:100%;margin-bottom:10px">';
        $this->print_num_items($classid, $class->maxstudents);
        echo '</div>';
        parent::display_default();
    }

    /**
     * Override print_num_items to display the max number of students allowed in this class
     *
     * @param int $numitems max number of students
     */
    public function print_num_items($classid, $max) {
        $pmclass = new pmclass($classid);
        $students = $pmclass->get_completion_counts($classid);

        $langfailed = get_string('num_students_failed', static::LANG_FILE);
        $langpassed = get_string('num_students_passed', static::LANG_FILE);
        $langnotcomplete = get_string('num_students_not_complete', static::LANG_FILE);
        $langmaxstudents = get_string('num_max_students', static::LANG_FILE);

        if (!empty($students[STUSTATUS_FAILED])) {
            // echo '<div style="float:right;">'.$langfailed.': '.$students[STUSTATUS_FAILED].'</div><br />';
        }

        if (!empty($students[STUSTATUS_PASSED])) {
            // echo '<div style="float:right;">'.$langpassed.': '.$students[STUSTATUS_PASSED].'</div><br />';
        }

        if (!empty($students[STUSTATUS_NOTCOMPLETE])) {
            // echo '<div style="float:right;">'.$langnotcomplete.': '.$students[STUSTATUS_NOTCOMPLETE].'</div><br />';
        }

        if (!empty($max)) {
            echo '<div style="float:right;">'.$langmaxstudents.': '.$max.'</div><br />';
        }
    }

    /**
     * Get the strings to use for the "assigned" and "unassigned" headers.
     *
     * @return array An array consisting of the assigned header, and the unassigned header - in that order.
     */
    protected function get_assigned_strings() {
        return array(get_string('ds_assigned_enrol', 'elis_program'), get_string('ds_unassigned_enrol', 'elis_program'));
    }
}
