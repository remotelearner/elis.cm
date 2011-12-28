<?php
/**
 * Health check for ELIS.  Based heavily on /admin/health.php.
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once CURMAN_DIRLOCATION . '/lib/newpage.class.php';

/// The health check page
class healthpage extends newpage {
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

    function get_navigation_default() {
        return array(
            array('name' => get_string('healthcenter'),
                  'link' => $this->get_url()),
            );
    }

    function get_title_default() {
        return get_string('healthcenter');
    }

    function action_default() {
        global $core_health_checks;
        $verbose = $this->optional_param('verbose', false, PARAM_BOOL);

        $issues = array(
            healthpage::SEVERITY_CRITICAL => array(),
            healthpage::SEVERITY_SIGNIFICANT => array(),
            healthpage::SEVERITY_ANNOYANCE => array(),
            healthpage::SEVERITY_NOTICE => array(),
            );
        $problems = 0;

        $healthclasses = $core_health_checks;

        $plugins = get_list_of_plugins('curriculum/plugins');
        foreach ($plugins as $plugin) {
            if (is_readable(CURMAN_DIRLOCATION . '/plugins/' . $plugin . '/health.php')) {
                include_once(CURMAN_DIRLOCATION . '/plugins/' . $plugin . '/health.php');
                $varname = "${plugin}_health_checks";
                if (isset($$varname)) {
                    $healthclasses = array_merge($healthclasses, $$varname);
                }
            }
        }

        if ($verbose) {
            echo "Checking...\n<ul>\n";
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
            echo get_string('healthnoproblemsfound');
            echo '</div>';
        } else {
            print_heading(get_string('healthproblemsdetected'));
            foreach($issues as $severity => $healthissues) {
                if(!empty($issues[$severity])) {
                    echo '<dl class="healthissues '.$severity.'">';
                    foreach($healthissues as $classname => $data) {
                        echo '<dt id="'.$classname.'">'.$data['title'].'</dt>';
                        echo '<dd>'.$data['description'];
                        echo '<form action="index.php#solution" method="get">';
                        echo '<input type="hidden" name="s" value="health" />';
                        echo '<input type="hidden" name="action" value="solution" />';
                        echo '<input type="hidden" name="problem" value="'.$classname.'" /><input type="submit" value="'.get_string('viewsolution').'" />';
                        echo '</form></dd>';
                    }
                    echo '</dl>';
                }
            }
        }
    }

    function action_solution() {
        $classname = $this->required_param('problem', PARAM_SAFEDIR);
        $plugins = get_list_of_plugins('curriculum/plugins');
        foreach ($plugins as $plugin) {
            if (is_readable(CURMAN_DIRLOCATION . '/plugins/' . $plugin . '/health.php')) {
                include_once(CURMAN_DIRLOCATION . '/plugins/' . $plugin . '/health.php');
            }
        }
        $problem = new $classname;
        $data = array(
            'title'       => $problem->title(),
            'severity'    => $problem->severity(),
            'description' => $problem->description(),
            'solution'    => $problem->solution()
            );

        print_heading(get_string('healthcenter'));
        print_heading(get_string('healthproblemsolution'));
        echo '<dl class="healthissues '.$data['severity'].'">';
        echo '<dt>'.$data['title'].'</dt>';
        echo '<dd>'.$data['description'].'</dd>';
        echo '<dt id="solution" class="solution">'.get_string('healthsolution').'</dt>';
        echo '<dd class="solution">'.$data['solution'].'</dd></dl>';
        echo '<form id="healthformreturn" action="index.php#'.$classname.'" method="get">';
        echo '<input type="hidden" name="s" value="health" />';
        echo '<input type="submit" value="'.get_string('healthreturntomain').'" />';
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
    'health_duplicate_enrolments',
    'health_stale_cm_class_moodle',
    'health_curriculum_course',
    'health_user_sync',
    'cluster_orphans_check',
    'track_classes_check',
    'completion_export_check',
    'duplicate_moodle_profile',
    );

/**
 * Checks for duplicate CM enrolment records.
 */
class health_duplicate_enrolments extends crlm_health_check_base {
    function __construct() {
        require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
        global $CURMAN;
        $sql = "SELECT COUNT(*)
                  FROM {$CURMAN->db->prefix_table(STUTABLE)} enr
                 WHERE EXISTS (SELECT *
                                 FROM {$CURMAN->db->prefix_table(STUTABLE)} enr2
                                WHERE enr.userid = enr2.userid
                                  AND enr.classid = enr2.classid
                                  AND enr.id > enr2.id)";
        $this->count = $CURMAN->db->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return 'Duplicate class enrolment records';
    }
    function description() {
        return "There were {$this->count} duplicate class enrolment records in the ELIS enrolments table.";
    }
    function solution() {
        $msg = 'The duplicate class enrolments need to be removed directly from the database.  <b>DO NOT</b> try to remove them via the UI.<br/><br/>'
             . 'Run the script fix_duplicate_enrolments.php to remove all duplicate class enrolments.';
        return $msg;
    }
}

/**
 * Checks that the crlm_class_moodle table doesn't contain any links to stale
 * CM class records.
 */
class health_stale_cm_class_moodle extends crlm_health_check_base {
    function __construct() {
        require_once CURMAN_DIRLOCATION . '/lib/classmoodlecourse.class.php';
        require_once CURMAN_DIRLOCATION . '/lib/cmclass.class.php';
        global $CURMAN;
        $sql = "SELECT COUNT(*)
                  FROM {$CURMAN->db->prefix_table(CLSMDLTABLE)} clsmdl
             LEFT JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cls on clsmdl.classid = cls.id
                 WHERE cls.id IS NULL";
        $this->count = $CURMAN->db->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return 'Stale CM Class - Moodle Course record';
    }
    function description() {
        return "There were {$this->count} records in the crlm_class_moodle table referencing nonexistent CM classes.";
    }
    function solution() {
        global $CURMAN;
        $msg = "These records need to be removed from the database.<br/>Suggested SQL:
                DELETE FROM {$CURMAN->db->prefix_table(CLSMOODLETABLE)} WHERE classid NOT IN (
                SELECT id FROM {$CURMAN->db->prefix_table(CLSTABLE)} )";
        return $msg;
    }
}

/**
 * Checks that the crlm_curriculum_course table doesn't contain any links to
 * stale CM course records.
 */
class health_curriculum_course extends crlm_health_check_base {
    function __construct() {
        require_once CURMAN_DIRLOCATION . '/lib/curriculumcourse.class.php';
        require_once CURMAN_DIRLOCATION . '/lib/course.class.php';
        global $CURMAN;
        $sql = "SELECT COUNT(*)
                  FROM {$CURMAN->db->prefix_table(CURCRSTABLE)} curcrs
             LEFT JOIN {$CURMAN->db->prefix_table(CRSTABLE)} crs on curcrs.courseid = crs.id
                 WHERE crs.id IS NULL";
        $this->count = $CURMAN->db->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return 'Stale CM Course - Moodle Curriculum record';
    }
    function description() {
        global $CURMAN;
        return "There are {$this->count} records in the {$CURMAN->db->prefix_table(CURCRSTABLE)} table referencing nonexistent CM courses.";
    }
    function solution() {
        global $CURMAN;
        $msg = "These records need to be removed from the database.<br/>Suggested SQL:
                DELETE FROM {$CURMAN->db->prefix_table(CURCRSTABLE)} WHERE courseid NOT IN (
                SELECT id FROM {$CURMAN->db->prefix_table(CRSTABLE)} )";
        return $msg;

    }
}

/**
 * Checks if there are more Moodle users than ELIS users
 */
class health_user_sync extends crlm_health_check_base {
    function __construct() {
        global $CFG, $CURMAN;

        $sql = "SELECT COUNT(*) FROM {$CFG->prefix}user WHERE
                username != 'guest'
                AND deleted = 0
                AND confirmed = 1
                AND mnethostid = {$CFG->mnet_localhost_id}
                AND idnumber != ''
                AND NOT EXISTS (SELECT 'x'
                                FROM {$CFG->prefix}crlm_user cu
                                WHERE cu.idnumber = {$CFG->prefix}user.idnumber)";

        $this->count = $CURMAN->db->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
        return 'User Records Mismatch - Synchronize Users';
    }
    function description() {
        return "There are {$this->count} extra user records for Moodle which don't exist for ELIS.";
    }
    function solution() {
        global $CFG;
        $msg = 'Users need to be synchronized by running the script which is linked below.<br/><br/>'.
               'This process can take a long time, we recommend you run it during non-peak hours, and leave this window open until you see a success message. '.
               'If the script times out (stops loading before indicating success), please open a support ticket to have this run for you.<br/><br/>'.
               '<a href="'.$CFG->wwwroot.'/curriculum/scripts/migrate_moodle_users.php">Fix this now</a>';
        return $msg;
    }
}

class cluster_orphans_check extends crlm_health_check_base {
    function __construct() {
        global $CURMAN;
        $this->parentBad = array();

        $clusters = cluster_get_listing('id', 'ASC', 0);
        foreach ($clusters as $clusid => $clusdata) {
            if ($clusdata->parent > 0) {
                $select = "id='{$clusdata->parent}'";
                $parentCnt = $CURMAN->db->count_records_select(CLSTTABLE, $select);
                if ($parentCnt < 1) {
                    $this->parentBad[] = $clusdata->name;
                }
            }
        }
    }

    function exists() {
        $returnVal = (count($this->parentBad) > 0) ? true : false;
        return $returnVal;
    }

    function title() {
        return 'Orphaned clusters found!';
    }

    function severity() {
        return healthpage::SEVERITY_ANNOYANCE;
    }

    function description() {
        if (count($this->parentBad) > 0) {
            $msg = 'There are '.count($this->parentBad).' sub-clusters which have had their parent clusters deleted.<br/><ul>';
            foreach ($this->parentBad as $parentName) {
                $msg .= '<li>'.$parentName.'</li>';
            }
            $msg .= '</ul>';
        } else {
            $msg = 'There were no orphaned clusters found.'; // We should not reach here but put in just in case
        }

        return $msg;
    }

    function solution() {
        global $CFG;
        $msg = 'From the command line change to the directory '.$CFG->dirroot.'/curriculum/scripts<br/>
                Run the script fix_cluster_orphans.php to convert all clusters with missing parent clusters to top-level.';
        return $msg;
    }
}

class track_classes_check extends crlm_health_check_base {
    function __construct() {
        global $CURMAN;
        $this->unattachedClasses = array();

        $sql = "SELECT trkcls.id, trkcls.trackid, trkcls.courseid, trkcls.classid, trk.curid
                FROM {$CURMAN->db->prefix_table('crlm_track_class')} trkcls
                JOIN {$CURMAN->db->prefix_table('crlm_track')} trk ON trk.id = trkcls.trackid";
        $classes = $CURMAN->db->get_records_sql($sql);

        if (is_array($classes)) {
            foreach ($classes as $trackClassId=>$trackClassObj) {
                $select = "curriculumid = {$trackClassObj->curid} AND courseid = {$trackClassObj->courseid}";
                $cnt = $CURMAN->db->count_records_select(CURCRSTABLE, $select);
                if ($cnt < 1) {
                    $this->unattachedClasses[] = $trackClassObj->id;
                }
            }
        }
    }

    function exists() {
        $returnVal = (count($this->unattachedClasses) > 0) ? true : false;
        return $returnVal;
    }

    function title() {
        return 'Unassociated classes found in tracks';
    }

    function severity() {
        return healthpage::SEVERITY_SIGNIFICANT;
    }

    function description() {
        if (count($this->unattachedClasses) > 0) {
            $msg = 'Found '.count($this->unattachedClasses).' classes that are attached to tracks when associated courses are not attached to the curriculum.';
        } else {
            $msg = 'There were no issues found.'; // We should not reach here but put in just in case
        }

        return $msg;
    }

    function solution() {
        global $CFG;
        $msg = 'Need to remove all classes in tracks that do not have an associated course in its associated curriculum by running the script linked below.<br/><br/>' .
               '<a href="'.$CFG->wwwroot.'/curriculum/scripts/fix_track_classes.php">Fix this now</a>';
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
        return 'Completion export';
    }

    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }

    function description() {
        return 'The Completion Export block, which conflicts with Integration Point, is present.';
    }

    function solution() {
        global $CFG;
        return "The completion export block should be automatically removed when the site is properly upgraded via CVS or git.  If it is still present, go to the <a href=\"{$CFG->wwwroot}/admin/blocks.php\">Manage blocks</a> page and delete the completion export block, and then remove the <tt>{$CFG->dirroot}/blocks/completion_export</tt> directory.";
    }
}

/**
 * Checks for duplicate CM enrolment records.
 */
class duplicate_moodle_profile extends crlm_health_check_base {
    function __construct() {
        require_once CURMAN_DIRLOCATION . '/lib/student.class.php';
        global $CURMAN;
        $concat = sql_concat('fieldid', "'/'", 'userid');
        $sql = "SELECT $concat, COUNT(*)-1 AS dup
                  FROM {$CURMAN->db->prefix_table('user_info_data')} dat
              GROUP BY fieldid, userid
                HAVING COUNT(*) > 1";
        $this->counts = $CURMAN->db->get_records_sql($sql);
    }
    function exists() {
        return !empty($this->counts);
    }
    function severity() {
        return healthpage::SEVERITY_ANNOYANCE;
    }
    function title() {
        return 'Duplicate Moodle profile field records';
    }
    function description() {
        $count = array_reduce($this->counts, create_function('$a,$b', 'return $a + $b->dup;'), 0);
        return "There were {$count} duplicate Moodle profile field records.";
    }
    function solution() {
        $msg = 'Run the script fix_duplicate_moodle_profile.php to remove all duplicate profile field records.';
        return $msg;
    }
}
?>
