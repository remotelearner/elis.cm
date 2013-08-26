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
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::lib('page.class.php'));    // pm_page
require_once(elispm::lib('deprecatedlib.php')); // cm_get_crlmuserid()
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/certificateissued.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('certificate.php'));

/**
 * ELIS page to display certificates.
 */
class certificatelistpage extends pm_page {
    /**
     * @var string The unique name for the page.
     */
    public $pagename      = 'certlist';

    /**
     * @var string The section this page belongs to.
     */
    public $section       = 'curr';

    /**
     * @var array Cache of entity names.
     */
    public $entitynamebuffer = array();

    /**
     * This function check to see if the global certificate config variables
     * are both enabled before allowing the user to perform the default action.
     * Perhaps when all entity types are implemented this function would check
     * the global configuration variables for all entity types
     * @return bool Whether the user can perform the default action.
     */
    public function can_do_default() {
        // This is where we would check if all entity types are disabled.
        if (!empty(elis::$config->elis_program->disablecertificates) &&
                !empty(elis::$config->elis_program->disablecoursecertificates)) {
            return false;
        }

        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:viewownreports', $context);
    }

    /**
     * Get the title for the page.
     * @return string The title.
     */
    public function get_title_default() {
        return get_string('certificatelist', 'elis_program');
    }

    /**
     * Build the default navigation bar.
     * @param object $who Not sure what this is actually supposed to be null seems to be the parameter of choice.
     *                    But there is not documentation in parent functions as to what $who is
     */
    public function build_navbar_default($who = null) {
        $page = new certificatelistpage(array());
        $this->navbar->add($this->get_title_default(), $page->url);
    }

    /**
     * List the certificates available to be printed.
     * TODO: Figure out a better way of displaying all of the cert entity types
     */
    public function display_default() {
        global $CFG, $USER, $OUTPUT;

        // This is for a Moodle user, so get the Curriculum user id.
        $cuserid    = cm_get_crlmuserid($USER->id);
        $link       = '';
        $attributes = array();
        $text       = '';

        if (empty($cuserid)) {
            print_error('notelisuser', 'elis_program');
        }

        if (empty(elis::$config->elis_program->disablecertificates)) {

            $curasses = curriculumstudent::get_completed_for_user($cuserid);
            if (count($curasses) == 0) {
                print_string('certificates_none_earned', 'elis_program');
            } else {

                print_string('certificates_earned', 'elis_program');

                echo html_writer::start_tag('ul');
                foreach ($curasses as $curass) {

                    $attributes['href'] = 'certificate.php?id='.$curass->id;
                    $attributes['target'] = '_blank';
                    $text = $curass->curriculum->name;
                    $link = html_writer::tag('a', $text, $attributes);

                    echo html_writer::tag('li', $link);
                }
                echo html_writer::end_tag('ul');
            }

        }

        if (isset(elis::$config->elis_program->disablecoursecertificates) &&
                empty(elis::$config->elis_program->disablecoursecertificates)) {

            $records = get_user_certificates($cuserid);

            $this->display_entity_certificates($records);

            $records->close();

        }
    }

    /**
     * This function iterates over the different certificate entity types and
     * outputs the links to view the certificates
     * @param recordset $certificatedata Recordset of certificate data
     */
    public function display_entity_certificates($certificatedata) {
        foreach ($certificatedata as $certdata) {
            $this->get_cert_entity_name($certdata);
        }

        // Output the different entity type certificate.
        if (!empty($this->entitynamebuffer[CERTIFICATE_ENTITY_TYPE_COURSE])) {
            echo html_writer::empty_tag('br');
            echo html_writer::empty_tag('br');
            echo get_string('cert_class_complete', 'elis_program');
            echo html_writer::start_tag('ul');

            foreach ($this->entitynamebuffer[CERTIFICATE_ENTITY_TYPE_COURSE] as $entityname) {
                echo $entityname;
            }

            echo html_writer::end_tag('ul');
        }
    }

    /**
     * This function retrieves the name of the entity and stores it into the output buffer
     * @param object $certdata Certificate issued and settings object @see get_user_certificates() for details about this object
     * @return bool Return true is the buffer is filled, otherwise false
     */
    public function get_cert_entity_name($certdata) {
        $name = '';

        if (!isset($certdata->entity_type)) {
            return false;
        }

        switch ($certdata->entity_type) {
            case CERTIFICATE_ENTITY_TYPE_PROGRAM:
                // Insert code to display program certificates.
                break;

            case CERTIFICATE_ENTITY_TYPE_COURSE:
                // Return the name of the course that pertains to the certificate data.
                if (!isset($certdata->entity_id)) {
                    return false;
                }

                // Retrieve the course description name.
                try {
                    $coursedescname = new course($certdata->entity_id);
                    $coursedescname->load();
                    $name = $coursedescname->name;
                } catch (dml_missing_record_exception $e) {
                    debugging($e->getMessage(), DEBUG_DEVELOPER);
                    return false;
                }

                if (!empty($name)) {
                    $url = "entity_certificate.php?id={$certdata->id}&csid={$certdata->csid}";
                    $link = html_writer::link($url, $name, array('target' => '_blank'));
                    $name = html_writer::tag('li', $link);
                    $this->entitynamebuffer[CERTIFICATE_ENTITY_TYPE_COURSE][] = $name;
                }
                break;

            case CERTIFICATE_ENTITY_TYPE_LEARNINGOBJ:
                // Insert code to display learning objective certificates.
                break;

            case CERTIFICATE_ENTITY_TYPE_CLASS:
                // Insert code to display class certificates.
                break;

            default:
                return false;
        }

        return true;
    }

    /**
     * This function gets the name of the class that pertains to the course
     * where the student has already been issued a certificate
     *
     * TODO: Update this function to perform this task better and where I'm not
     * running a query in the page class against the different data class
     * tables.  Maybe add a new function in the class instance data class
     * script?
     *
     * @param object $certdata Certificate issued and settings object @see get_user_certificates() for details about this object
     * @return string|bool IDnumber of class instance (name) of entity or false if more than one record is returned or
     *                     an exception is thrown (dml_missing_record_exception, dml_multiple_records_exception)
     */
    public function get_class_name_by_course($certdata) {
        global $DB;

        if (empty($certdata) || !isset($certdata->cm_userid) ||
            !isset($certdata->entity_id) || !isset($certdata->timeissued)) {
            return false;
        }

        // The below query should go into the student.class.php...?
        $param  = array(
            'userid'     => $certdata->cm_userid,
            'courseid'   => $certdata->entity_id,
            'timeissued' => $certdata->timeissued
        );

        $select = "SELECT cls.idnumber ";
        $from   = "FROM {crlm_class} cls ".
                  "INNER JOIN {crlm_class_enrolment} clsenrol ON cls.id = clsenrol.classid ";
        $where  = "WHERE clsenrol.userid = :userid AND cls.courseid = :courseid AND ".
                  "clsenrol.completetime = :timeissued ";

        $record = $DB->get_record_sql($select.$from.$where, $param);

        if (!empty($record)) {
            return $record->idnumber;
        } else {
            return false;
        }
    }

}