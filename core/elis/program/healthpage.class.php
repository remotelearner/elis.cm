<?php
/**
 * Health check for ELIS.  Based heavily on /admin/health.php.
 *
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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('page.class.php');

/// The health check page
class healthpage extends pm_page {
    var $pagename = 'health';
    var $section = 'admn';

    const SEVERITY_NOTICE = 'notice';
    const SEVERITY_ANNOYANCE = 'annoyance';
    const SEVERITY_SIGNIFICANT = 'significant';
    const SEVERITY_CRITICAL = 'critical';

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('moodle/site:config', $context);
    }

    function build_navbar_default($who = null) {
        global $CFG, $PAGE;

        $this->navbar->add(get_string('learningplan', 'elis_program'), "{$CFG->wwwroot}/elis/program/");
        $baseurl = $this->url;
        $baseurl->remove_all_params();
        $this->navbar->add(get_string('pluginname', 'tool_health'),
                           $baseurl->out(true, array('s' => $this->pagename)));
    }

    /**
     * Initialize the page variables needed for display.
     */
    function get_page_title_default() {
        return get_string('pluginname', 'tool_health');
    }

    function display_default() {
        global $OUTPUT, $core_health_checks;
        $verbose = $this->optional_param('verbose', false, PARAM_BOOL);
        @set_time_limit(0);

        $issues = array(
            healthpage::SEVERITY_CRITICAL => array(),
            healthpage::SEVERITY_SIGNIFICANT => array(),
            healthpage::SEVERITY_ANNOYANCE => array(),
            healthpage::SEVERITY_NOTICE => array(),
            );
        $problems = 0;

        $healthclasses = $core_health_checks;

        //include health classes from other files
        $plugin_types = array('eliscoreplugins', 'pmplugins');

        foreach ($plugin_types as $plugin_type) {
            $plugins = get_plugin_list($plugin_type);
            foreach ($plugins as $plugin_shortname => $plugin_path) {
                $health_file_path = $plugin_path . '/health.php';
                if (is_readable($health_file_path)) {
                    include_once $health_file_path;
                    $varname = "${plugin_shortname}_health_checks";
                    if (isset($$varname)) {
                        $healthclasses = array_merge($healthclasses, $$varname);
                    }
                }
            }
        }

        if ($verbose) {
            echo get_string('health_checking', 'elis_program');
        }
        foreach ($healthclasses as $classname) {
            $problem = new $classname;
            if ($verbose) {
                echo "<li>$classname";
            }
            if($problem->exists()) {
                $severity = $problem->severity();
                $issues[$severity][$classname] = array(
                    'severity'    => $severity,
                    'description' => $problem->description(),
                    'title'       => $problem->title()
                    );
                ++$problems;
                if ($verbose) {
                    echo " - FOUND";
                }
            }
            if ($verbose) {
                echo '</li>';
            }
            unset($problem);
        }
        if ($verbose) {
            echo '</ul>';
        }

        if($problems == 0) {
            echo '<div id="healthnoproblemsfound">';
            echo get_string('healthnoproblemsfound', 'tool_health');
            echo '</div>';
        } else {
            echo $OUTPUT->heading(get_string('healthproblemsdetected', 'tool_health'));
            foreach($issues as $severity => $healthissues) {
                if(!empty($issues[$severity])) {
                    echo '<dl class="healthissues '.$severity.'">';
                    foreach($healthissues as $classname => $data) {
                        echo '<dt id="'.$classname.'">'.$data['title'].'</dt>';
                        echo '<dd>'.$data['description'];
                        echo '<form action="index.php#solution" method="get">';
                        echo '<input type="hidden" name="s" value="health" />';
                        echo '<input type="hidden" name="action" value="solution" />';
                        echo '<input type="hidden" name="problem" value="'.$classname.'" /><input type="submit" value="'.get_string('healthsolution', 'tool_health').'" />';
                        echo '</form></dd>';
                    }
                    echo '</dl>';
                }
            }
        }
    }

    function display_solution() {
        global $OUTPUT;

        $classname = $this->required_param('problem', PARAM_SAFEDIR);

        //import files needed for other health classes
        $plugin_types = array('eliscoreplugins', 'pmplugins');

        foreach ($plugin_types as $plugin_type) {
            $plugins = get_plugin_list($plugin_type);
            foreach ($plugins as $plugin_shortname => $plugin_path) {
                $health_file_path = $plugin_path . '/health.php';
                if (is_readable($health_file_path)) {
                    include_once $health_file_path;
                }
            }
        }
        $problem = new $classname;
        $data = array(
            'title'       => $problem->title(),
            'severity'    => $problem->severity(),
            'description' => $problem->description(),
            'solution'    => $problem->solution()
            );

        $OUTPUT->heading(get_string('pluginname', 'tool_health'));
        $OUTPUT->heading(get_string('healthproblemsolution', 'tool_health'));
        echo '<dl class="healthissues '.$data['severity'].'">';
        echo '<dt>'.$data['title'].'</dt>';
        echo '<dd>'.$data['description'].'</dd>';
        echo '<dt id="solution" class="solution">'.get_string('healthsolution', 'tool_health').'</dt>';
        echo '<dd class="solution">'.$data['solution'].'</dd></dl>';
        echo '<form id="healthformreturn" action="index.php#'.$classname.'" method="get">';
        echo '<input type="hidden" name="s" value="health" />';
        echo '<input type="submit" value="'.get_string('healthreturntomain', 'tool_health').'" />';
        echo '</form>';
    }
}

class crlm_health_check_base {
    function exists() {
        return false;
    }
    function title() {
        return '???';
    }
    function severity() {
        return healthpage::SEVERITY_NOTICE;
    }
    function description() {
        return '';
    }
    function solution() {
        return '';
    }
}

global $core_health_checks;
$core_health_checks = array(
    'cron_lastruntimes_check',
    'health_duplicate_enrolments',
    'health_stale_cm_class_moodle',
    'health_curriculum_course',
    'health_user_sync',
    'cluster_orphans_check',
    'track_classes_check',
    'completion_export_check',
    'dangling_completion_locks',
    'duplicate_course_los',
    );

/**
 * Checks for duplicate CM enrolment records.
 */
class health_duplicate_enrolments extends crlm_health_check_base {
    function __construct() {
        require_once elispm::lib('data/student.class.php');
        global $DB;

        $sql = "SELECT COUNT('x')
                FROM {".student::TABLE."} enr
                INNER JOIN (
                    SELECT id
                    FROM {".student::TABLE."}
                    GROUP BY userid, classid
                    HAVING COUNT(id) > 1
                ) dup
                ON enr.id = dup.id";
        $this->count = $DB->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return get_string('health_duplicate', 'elis_program');
    }
    function description() {
        return get_string('health_duplicatedesc', 'elis_program', $this->count);
    }
    function solution() {
        $msg = get_string('health_duplicatesoln', 'elis_program');
        return $msg;
    }
}

/**
 * Checks that the crlm_class_moodle table doesn't contain any links to stale
 * CM class records.
 */
class health_stale_cm_class_moodle extends crlm_health_check_base {
    function __construct() {
        require_once elispm::lib('data/classmoodlecourse.class.php');
        require_once elispm::lib('data/pmclass.class.php');
        global $DB;
        $sql = "SELECT COUNT(*)
                  FROM {".classmoodlecourse::TABLE."} clsmdl
             LEFT JOIN {".pmclass::TABLE."} cls on clsmdl.classid = cls.id
                 WHERE cls.id IS NULL";
        $this->count = $DB->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return get_string('health_stale', 'elis_program');
    }
    function description() {
        global $CFG;
        return get_string('health_staledesc', 'elis_program',
                   array('count' => $this->count,
                         'table' => $CFG->prefix . classmoodlecourse::TABLE));
    }
    function solution() {
        global $CFG;

        $msg = get_string('health_stalesoln', 'elis_program').
                "<br/> USE {$CFG->dbname}; <br/>".
                " DELETE FROM {$CFG->prefix}". classmoodlecourse::TABLE ." WHERE classid NOT IN (
                SELECT id FROM {$CFG->prefix}". pmclass::TABLE ." );";
        return $msg;
    }
}

/**
 * Checks that the crlm_curriculum_course table doesn't contain any links to
 * stale CM course records.
 */
class health_curriculum_course extends crlm_health_check_base {
    function __construct() {
        require_once elispm::lib('data/curriculumcourse.class.php');
        require_once elispm::lib('data/course.class.php');
        global $DB;
        $sql = "SELECT COUNT(*)
                  FROM {".curriculumcourse::TABLE."} curcrs
             LEFT JOIN {".course::TABLE."} crs on curcrs.courseid = crs.id
                 WHERE crs.id IS NULL";
        $this->count = $DB->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return get_string('health_curriculum', 'elis_program');
    }
    function description() {
        global $CFG;
        return get_string('health_curriculumdesc', 'elis_program',
                   array('count' => $this->count,
                         'table' => $CFG->prefix . curriculumcourse::TABLE));
    }
    function solution() {
        global $CFG;

        $msg = get_string('health_curriculumsoln', 'elis_program').
                "<br/> USE {$CFG->dbname}; <br/>".
                "DELETE FROM {$CFG->prefix}". curriculumcourse::TABLE ." WHERE courseid NOT IN (
                 SELECT id FROM {$CFG->prefix}". course::TABLE ." );";
        return $msg;

    }
}

/**
 * Checks if there are more Moodle users than ELIS users
 */
class health_user_sync extends crlm_health_check_base {
    function __construct() {
        global $CFG, $DB;
        $params = array($CFG->mnet_localhost_id, $CFG->mnet_localhost_id);
        $sql = "SELECT COUNT('x')
                  FROM {user}
                 WHERE username != 'guest'
                   AND deleted = 0
                   AND confirmed = 1
                   AND mnethostid = ?
                   AND idnumber != ''
                   AND firstname != ''
                   AND lastname != ''
                   AND email != ''
                   AND country != ''
                   AND NOT EXISTS (
                         SELECT 'x'
                           FROM {".user::TABLE."} cu
                          WHERE cu.idnumber = {user}.idnumber
                     )
                   AND NOT EXISTS (
                       SELECT 'x'
                         FROM {".user::TABLE."} cu
                        WHERE cu.username = {user}.username
                          AND {user}.mnethostid = ?
                     )";

        $this->count = $DB->count_records_sql($sql, $params);

        $sql = "SELECT COUNT('x')
                  FROM {user} usr
                 WHERE deleted = 0
                   AND idnumber IN (
                         SELECT idnumber
                           FROM {user}
                          WHERE username != 'guest'
                            AND deleted = 0
                            AND confirmed = 1
                            AND mnethostid = ?
                            AND id != usr.id
                     )";

        $this->dupids = $DB->count_records_sql($sql, $params);
    }

    function exists() {
        return $this->count > 0 || $this->dupids > 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return get_string('health_user_sync', 'elis_program');
    }
    function description() {
        $msg = '';
        if ($this->count > 0) {
            $msg = get_string('health_user_syncdesc', 'elis_program', $this->count);
        }
        if ($this->dupids > 0) {
            if (!empty($msg)) {
                $msg .= "<br/>\n";
            }
            $msg .= get_string('health_user_dupiddesc', 'elis_program', $this->dupids);
        }
        return $msg;
    }
    function solution() {
        global $CFG;

        $msg = '';
        if ($this->dupids > 0) {
            $msg = get_string('health_user_dupidsoln', 'elis_program');
        }
        if ($this->count > $this->dupids) {
            // ELIS-3963: Only run migrate script if more mismatches then dups
            if (!empty($msg)) {
                $msg .= "<br/>\n";
            }
            $msg .= get_string('health_user_syncsoln', 'elis_program', $CFG->wwwroot);
        }
        return $msg;
    }
}

class cluster_orphans_check extends crlm_health_check_base {
    function __construct() {
        global $DB;

        //needed for db table constants
        require_once(elispm::lib('data/userset.class.php'));

        $this->parentBad = array();

        $sql = "SELECT child.name
                FROM
                {".userset::TABLE."} child
                WHERE NOT EXISTS (
                    SELECT *
                    FROM {".userset::TABLE."} parent
                    WHERE child.parent = parent.id
                )
                AND child.parent != 0";

        if ($clusters = $DB->get_recordset_sql($sql)) {
            foreach ($clusters as $cluster) {
                $this->parentBad[] = $cluster->name;
            }
            $clusters->close();
        }
    }

    function exists() {
        $returnVal = (count($this->parentBad) > 0) ? true : false;
        return $returnVal;
    }

    function title() {
        return get_string('health_cluster_orphans', 'elis_program');
    }

    function severity() {
        return healthpage::SEVERITY_ANNOYANCE;
    }

    function description() {
        if (count($this->parentBad) > 0) {
            $msg = get_string('health_cluster_orphansdesc', 'elis_program', array('count'=>count($this->parentBad)));
            foreach ($this->parentBad as $parentName) {
                $msg .= '<li>'.$parentName.'</li>';
            }
            $msg .= '</ul>';
        } else {
            $msg =  get_string('health_cluster_orphansdescnone', 'elis_program'); // We should not reach here but put in just in case
        }

        return $msg;
    }

    function solution() {
        global $CFG;
        $msg = get_string('health_cluster_orphanssoln', 'elis_program', $CFG->dirroot);
        return $msg;
    }
}

class track_classes_check extends crlm_health_check_base {
    function __construct() {
        global $DB;

        //needed for db table constants
        require_once(elispm::lib('data/track.class.php'));
        require_once(elispm::lib('data/curriculumcourse.class.php'));

        $this->unattachedClasses = array();

        $sql = 'SELECT trkcls.id, trkcls.trackid, trkcls.courseid, trkcls.classid, trk.curid
                FROM {'. trackassignment::TABLE .'} trkcls
                JOIN {'. track::TABLE .'} trk ON trk.id = trkcls.trackid
                JOIN {'. pmclass::TABLE .'} cls ON trkcls.classid = cls.id
                WHERE NOT EXISTS (
                    SELECT *
                    FROM {'. curriculumcourse::TABLE .'} curcrs
                    WHERE trk.curid = curcrs.curriculumid
                    AND cls.courseid = curcrs.courseid
                )';

        if (($trackclasses = $DB->get_recordset_sql($sql)) && $trackclasses->valid()) {
            foreach ($trackclasses as $trackclass) {
                $this->unattachedClasses[] = $trackclass->id;
            }
            $trackclasses->close();
        }
    }

    function exists() {
        $returnVal = (count($this->unattachedClasses) > 0) ? true : false;
        return $returnVal;
    }

    function title() {
        return get_string('health_trackcheck', 'elis_program');
    }

    function severity() {
        return healthpage::SEVERITY_SIGNIFICANT;
    }

    function description() {
        $msg = get_string('health_trackcheckdesc', 'elis_program', count($this->unattachedClasses));

        return $msg;
    }

    function solution() {
        global $CFG;
        $msg = get_string('health_trackchecksoln', 'elis_program', $CFG->wwwroot);
        return $msg;
    }
}

/**
 * Checks if the completion export block is present.
 */
class completion_export_check extends crlm_health_check_base {
    function exists() {
        global $CFG;
        $exists = is_dir($CFG->dirroot.'/blocks/completion_export');
        return is_dir($CFG->dirroot.'/blocks/completion_export');
    }

    function title() {
        return get_string('health_completion', 'elis_program');
    }

    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }

    function description() {
        return get_string('health_completiondesc', 'elis_program');
    }

    function solution() {
        global $CFG;
        return get_string('health_completionsoln', 'elis_program', $CFG);
    }
}

/**
 * Checks if the completion export block is present.
 */
class cron_lastruntimes_check extends crlm_health_check_base {
    private $blocks = array(); // empty array for none; 'curr_admin' ?
    private $plugins = array(); // TBD: 'elis_program', 'elis_core' ?

    function exists() {
        global $DB;
        $threshold = time() - DAYSECS;
        foreach ($this->blocks as $block) {
            $lastcron = $DB->get_field('block', 'lastcron', array('name' => $block));
            if ($lastcron < $threshold) {
                return true;
            }
        }
        foreach ($this->plugins as $plugin) {
            $lastcron = $DB->get_field('config_plugins', 'value', array('plugin' => $plugin, 'name' => 'lastcron'));
            if ($lastcron < $threshold) {
                return true;
            }
        }
        $lasteliscron = $DB->get_field('elis_scheduled_tasks', 'MAX(lastruntime)', array());
        if ($lasteliscron < $threshold) {
            return true;
        }
        return false;
    }

    function title() {
        return get_string('health_cron_title', 'elis_program');
    }

    function severity() {
        return healthpage::SEVERITY_NOTICE;
    }

    function description() {
        global $DB;
        $description = '';
        $threshold = time() - DAYSECS;
        foreach ($this->blocks as $block) {
            $lastcron = $DB->get_field('block', 'lastcron', array('name' => $block));
            if ($lastcron < $threshold) {
                $a = new stdClass;
                $a->name = $block;
                $a->lastcron = $lastcron ? userdate($lastcron) : get_string('cron_notrun', 'elis_program');
                $description .= get_string('health_cron_block', 'elis_program', $a);
            }
        }
        foreach ($this->plugins as $plugin) {
            $lastcron = $DB->get_field('config_plugins', 'value', array('plugin' => $plugin, 'name' => 'lastcron'));
            if ($lastcron < $threshold) {
                $a = new stdClass;
                $a->name = $plugin;
                $a->lastcron = $lastcron ? userdate($lastcron) : get_string('cron_notrun', 'elis_program');
                $description .= get_string('health_cron_plugin', 'elis_program', $a);
            }
        }
        $lasteliscron = $DB->get_field('elis_scheduled_tasks', 'MAX(lastruntime)', array());
        if ($lasteliscron < $threshold) {
            $lastcron = $lasteliscron ? userdate($lasteliscron) : get_string('cron_notrun', 'elis_program');
            $description .= get_string('health_cron_elis', 'elis_program', $lastcron);
        }
        return $description;
    }

    function solution() {
        return get_string('health_cron_soln', 'elis_program');
    }
}

/**
 * Checks for duplicate PM profile records.
 */
class duplicate_moodle_profile extends crlm_health_check_base {
    function __construct() {
        global $DB;
        $concat = $DB->sql_concat('fieldid', "'/'", 'userid');
        $sql = "SELECT $concat, COUNT(*)-1 AS dup
                  FROM {user_info_data} dat
              GROUP BY fieldid, userid
                HAVING COUNT(*) > 1";
        $this->counts = $DB->get_recordset_sql($sql);
    }
    function exists() {
        return ($this->counts->valid()===true) ? true : false;
    }
    function severity() {
        return healthpage::SEVERITY_ANNOYANCE;
    }
    function title() {
        return get_string('health_dupmoodleprofile', 'elis_program');
    }
    function description() {
        $count = 0;
        foreach ($this->counts as $dup) {
            $count += $dup->dup;
        }
        $this->counts->close();
        return get_string('health_dupmoodleprofiledesc', 'elis_program', $count);
    }
    function solution() {
        global $CFG;
        return get_string('health_dupmoodleprofilesoln', 'elis_program', $CFG->dirroot);
    }
}

/**
 * Checks for any passing completion scores that are unlocked and linked to Moodle grade items which do not exist.
 */
class dangling_completion_locks extends crlm_health_check_base {
    function __construct() {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/elis/program/lib/setup.php');
        require_once elispm::lib('data/student.class.php');

        // Check for unlocked, passed completion scores which are not associated with a valid Moodle grade item
        $sql = "SELECT COUNT('x')
                FROM {".student_grade::TABLE."} ccg
                INNER JOIN {".coursecompletion::TABLE."} ccc ON ccc.id = ccg.completionid
                INNER JOIN {".classmoodlecourse::TABLE."} ccm ON ccm.classid = ccg.classid
                INNER JOIN {course} c ON c.id = ccm.moodlecourseid
                LEFT JOIN {grade_items} gi ON (gi.idnumber = ccc.idnumber AND gi.courseid = c.id)
                WHERE ccg.locked = 0
                AND ccc.idnumber != ''
                AND ccg.grade >= ccc.completion_grade
                AND gi.id IS NULL";

        $this->count = $DB->count_records_sql($sql);

/*
        // Check for unlocked, passed completion scores which are associated with a valid Moodle grade item
        // XXX - NOTE: this is not currently being done as it may be that these values were manually unlocked on purpose
        // XXX - NOTE: this is from 1.9 so if / when using this query, update query to 2.x standard
        $sql = "SELECT COUNT('x')
                FROM {$CURMAN->db->prefix_table(USRTABLE)} cu
                INNER JOIN {$CURMAN->db->prefix_table(STUTABLE)} cce ON cce.userid = cu.id
                INNER JOIN {$CURMAN->db->prefix_table(GRDTABLE)} ccg ON (ccg.userid = cce.userid AND ccg.classid = cce.classid)
                INNER JOIN {$CURMAN->db->prefix_table(CRSCOMPTABLE)} ccc ON ccc.id = ccg.completionid
                INNER JOIN {$CURMAN->db->prefix_table(CLSMOODLETABLE)} ccm ON ccm.classid = ccg.classid
                INNER JOIN {$CFG->prefix}user u ON u.idnumber = cu.idnumber
                INNER JOIN {$CFG->prefix}course c ON c.id = ccm.moodlecourseid
                INNER JOIN {$CFG->prefix}grade_items gi ON (gi.courseid = c.id AND gi.idnumber = ccc.idnumber)
                INNER JOIN {$CFG->prefix}grade_grades gg ON (gg.itemid = gi.id AND gg.userid = u.id)
                WHERE ccg.locked = 0
                AND ccg.grade >= ccc.completion_grade
                AND gg.finalgrade >= ccc.completion_grade
                AND ccc.idnumber != ''
                AND gi.itemtype != 'course'
                AND ccg.timemodified > gg.timemodified";

        $this->count += $CURMAN->db->count_records_sql($sql);
*/
    }

    function exists() {
        return $this->count != 0;
    }

    function severity() {
        return healthpage::SEVERITY_SIGNIFICANT;
    }

    function title() {
        return get_string('health_danglingcompletionlocks','elis_program');
    }

    function description() {
        return get_string('health_danglingcompletionlocksdesc','elis_program', $this->count);
    }

    function solution() {
        $msg = get_string('health_danglingcompletionlockssoln','elis_program');
        return $msg;
    }
}

/**
 * Checks for duplicate course completion elements
 */
class duplicate_course_los extends crlm_health_check_base {
    var $count; // count of max course with duplicate completion elements
    function __construct() {
        global $DB;
        $sql = "SELECT MAX(count) FROM (SELECT COUNT('x') AS count FROM {crlm_course_completion} GROUP BY courseid, idnumber) duplos";
        $this->count = $DB->get_field_sql($sql);
    }
    function exists() {
        return($this->count > 1);
    }
    function severity() {
        return healthpage::SEVERITY_SIGNIFICANT; // ANNOYANCE ???
    }
    function title() {
        return get_string('health_dupcourselos', 'elis_program');
    }
    function description() {
        return get_string('health_dupcourselosdesc', 'elis_program');
    }
    function solution() {
        return get_string('health_dupcourselossoln', 'elis_program');
    }
}

