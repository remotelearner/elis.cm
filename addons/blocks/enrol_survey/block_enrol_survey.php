<?php //$Id$
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage enrol_survey
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/blocks/enrol_survey/lib.php');
require_once($CFG->dirroot .'/elis/program/lib/deprecatedlib.php'); // cm_get_crlmuserid()

class block_enrol_survey extends block_base {
    /**
     * block initializations
     */
    public function init() {
        $this->title   = get_string('title', 'block_enrol_survey');
        $this->version = '2010060700';
        $this->cron = HOURSECS;
    }

    /**
     * block contents
     *
     * @return object
     */
    public function get_content() {
        global $CFG, $COURSE, $USER, $PAGE;

        if ($this->content !== NULL) {
            return $this->content;
        }

        if ($COURSE->id == SITEID) {
            $context = get_context_instance(CONTEXT_SYSTEM);
        } else {
            $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $mymoodle = 0;
        if(strcmp('my-index',$PAGE->pagetype) == 0) {
          $mymoodle = 1;
        }

        if (has_capability('block/enrol_survey:edit', $context)) {
            $editpage = get_string('editpage', 'block_enrol_survey');

            $this->content->text .= "<a
href=\"{$CFG->wwwroot}/blocks/enrol_survey/edit_survey.php?id={$this->instance->id}&courseid={$COURSE->id}&mymoodle={$mymoodle}\">$editpage</a><br
/>";
        }

        if (has_capability('block/enrol_survey:take', $context) &&
            cm_get_crlmuserid($USER->id) !== false) {
            // MUST have ELIS user record to take survey!
            if(!empty($this->config->force_user) && !is_survey_taken($USER->id, $this->instance->id)) {
                redirect("{$CFG->wwwroot}/blocks/enrol_survey/survey.php?id={$this->instance->id}");
            }

            $takepage = get_string('takepage', 'block_enrol_survey');
            $this->content->text .= "<a
href=\"{$CFG->wwwroot}/blocks/enrol_survey/survey.php?id={$this->instance->id}&courseid={$COURSE->id}&mymoodle={$mymoodle}\">$takepage</a><br
/>";
        }

        // $this->content->text .= "<br/> crontime = {$this->config->cron_time}";
        return $this->content;
    }

    function specialization() {
        if (!isset($this->config)) {
            return;
        }
        if (!empty($this->config->title)) {
            $this->title = $this->config->title;
        } else {
            $this->config->title = get_string('title', 'block_enrol_survey');
        }
    }

    /**
     * allow the block to have a configuration page
     *
     * @return boolean
     */
    public function has_config() {
        return false;
    }

    /**
     * allow more than one instance of the block on a page
     *
     * @return boolean
     */
    public function instance_allow_multiple() {
        //allow more than one instance on a page
        return true;
    }

    /**
     * allow instances to have their own configuration
     *
     * @return boolean
     */
    function instance_allow_config() {
        //allow instances to have their own configuration
        return true;
    }

    /**
     * locations where block can be displayed
     *
     * @return array
     */
    public function applicable_formats() {
        return array('all' => true);
    }

    /**
     *  runs the survey at the specified time interval
     * @param bool $manual
     * @uses $CFG
     * @uses $DB
     */
    function cron($manual = false) {
        global $CFG, $DB;

        $now = time();

        $sql = "SELECT * FROM {block_instances}
                WHERE blockname = 'enrol_survey' "; // ***TBD***
        $block_instances = $DB->get_records_sql($sql);
        if (!empty($block_instances)) {
            foreach ($block_instances as $survey) {
                $block = block_instance('enrol_survey', $survey);

                if (!empty($block->config) && !empty($block->config->cron_time)) {
                    if (!isset($block->config->last_cron)) {
                        $block->config->last_cron = 0;
                    }

                    if (($block->config->last_cron + $block->config->cron_time) <= $now) {
                        $block->config->last_cron = $now;

                        $DB->delete_records('block_enrol_survey_taken', array('blockinstanceid' => $survey->id));

                        $block->instance_config_save($block->config);
                    }
                }
            }
        }

        return true;
    }
}

