<?php
/**
 * Health check for ELIS.  Based heavily on /admin/health.php.
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
    'cron_lastruntimes_check',
    'health_duplicate_enrolments',
    'health_stale_cm_class_moodle',
    'health_curriculum_course',
    'health_user_sync',
    'cluster_orphans_check',
    'track_classes_check',
    'completion_export_check',
    'duplicate_moodle_profile',
    'dangling_completion_locks'
);


/**
 * Checks for any passing completion scores that are unlocked and linked to Moodle grade items which do not exist.
 */
class dangling_completion_locks extends crlm_health_check_base {
    function __construct() {
        global $CURMAN, $CFG;

        require_once CURMAN_DIRLOCATION.'/lib/student.class.php';

        // Check for unlocked, passed completion scores which are not associated with a valid Moodle grade item
        $sql = "SELECT COUNT('x')
                FROM {$CURMAN->db->prefix_table(GRDTABLE)} ccg
                INNER JOIN {$CURMAN->db->prefix_table(CRSCOMPTABLE)} ccc ON ccc.id = ccg.completionid
                INNER JOIN {$CURMAN->db->prefix_table(CLSMOODLETABLE)} ccm ON ccm.classid = ccg.classid
                INNER JOIN {$CFG->prefix}course c ON c.id = ccm.moodlecourseid
                LEFT JOIN {$CFG->prefix}grade_items gi ON (gi.idnumber = ccc.idnumber AND gi.courseid = c.id)
                WHERE ccg.locked = 0
                AND ccc.idnumber != ''
                AND ccg.grade >= ccc.completion_grade
                AND gi.id IS NULL";

        $this->count = $CURMAN->db->count_records_sql($sql);
/*
        // Check for unlocked, passed completion scores which are associated with a valid Moodle grade item
        // XXX - NOTE: this is not currently being done as it may be that these values were manually unlocked on purpose
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
        return get_string('health_danglingcompletionlocks','block_curr_admin');
    }
    function description() {
        return get_string('health_danglingcompletionlocksdesc','block_curr_admin', $this->count);
    }
    function solution() {
        $msg = get_string('health_danglingcompletionlockssoln','block_curr_admin');
        return $msg;
    }
}


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
        return get_string('health_dupenrol','block_curr_admin');
    }
    function description() {
        return get_string('health_dupenroldesc','block_curr_admin', $this->count);
    }
    function solution() {
        $msg = get_string('health_dupenrolsoln','block_curr_admin');
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
        return get_string('health_stalecmclass','block_curr_admin');
    }
    function description() {
        return get_string('health_stalecmclassdesc','block_curr_admin', $this->count);
    }
    function solution() {
        global $CURMAN;
        $msg = get_string('health_stalecmclasssoln','block_curr_admin', array($CURMAN->db->prefix_table(CLSMOODLETABLE),$CURMAN->db->prefix_table(CLSTABLE)));
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
        return get_string('health_stalecmcourse','block_curr_admin');
    }
    function description() {
        global $CURMAN;
        return get_string('health_stalecmcoursedesc','block_curr_admin', array($this->count, $CURMAN->db->prefix_table(CURCRSTABLE)));
    }
    function solution() {
        global $CURMAN;
        $msg = get_string('health_stalecmcoursesoln','block_curr_admin', array($CURMAN->db->prefix_table(CURCRSTABLE), $CURMAN->db->prefix_table(CRSTABLE)));"";
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
                AND firstname != ''
                AND lastname != ''
                AND email != ''
                AND country != ''
                AND NOT EXISTS (SELECT 'x'
                                FROM {$CFG->prefix}crlm_user cu
                                WHERE cu.idnumber = {$CFG->prefix}user.idnumber)
                AND NOT EXISTS (SELECT 'x'
                                FROM {$CFG->prefix}crlm_user cu
                                WHERE cu.username = {$CFG->prefix}user.username)";

        $this->count = $CURMAN->db->count_records_sql($sql);

        $sql = "SELECT COUNT(*) FROM {$CFG->prefix}user usr
                WHERE deleted = 0
                  AND idnumber IN (
                  SELECT idnumber FROM {$CFG->prefix}user
                  WHERE username != 'guest' AND deleted = 0
                  AND confirmed = 1 AND mnethostid = {$CFG->mnet_localhost_id} AND id != usr.id)";

        $this->dupids = $CURMAN->db->count_records_sql($sql);
    }
    function exists() {
        return $this->count != 0 || $this->dupids > 0;
    }
    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }
    function title() {
//        return 'User Records Mismatch - Synchronize Users';

        return get_string('health_user_sync', 'block_curr_admin');
    }
    function description() {
//        return "There are {$this->count} extra user records for Moodle which don't exist for ELIS.";
        $msg = '';
        if ($this->count > 0) {
            $msg = get_string('health_user_syncdesc', 'block_curr_admin', $this->count);
        }
        if ($this->dupids > 0) {
            if (!empty($msg)) {
                $msg .= "<br/>\n";
            }
            $msg .= get_string('health_user_dupiddesc', 'block_curr_admin', $this->dupids);
        }
        return $msg;
    }
    function solution() {
        global $CFG;

        $msg = '';
        if ($this->dupids > 0) {
            $msg = get_string('health_user_dupidsoln', 'block_curr_admin');
        }
        if ($this->count > $this->dupids) {
            // ELIS-3963: Only run migrate script if more mismatches then dups
            if (!empty($msg)) {
                $msg .= "<br/>\n";
            }
            $msg .= get_string('health_user_syncsoln', 'block_curr_admin', $CFG->wwwroot);
        }
        return $msg;
    }
}

class cluster_orphans_check extends crlm_health_check_base {
    function __construct() {
        global $CURMAN;
        $this->parentBad = array();

        if ($clusters = cluster_get_listing('id', 'ASC', 0)) {
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
    }

    function exists() {
        $returnVal = (count($this->parentBad) > 0) ? true : false;
        return $returnVal;
    }

    function title() {
        return get_string('health_clusterorphan', 'block_curr_admin');
    }

    function severity() {
        return healthpage::SEVERITY_ANNOYANCE;
    }

    function description() {
        if (count($this->parentBad) > 0) {
            $msg =  get_string('health_clusterorphandesc', 'block_curr_admin', count($this->parentBad));
            $msg .= '<br/><ul>';
            foreach ($this->parentBad as $parentName) {
                $msg .= '<li>'.$parentName.'</li>';
            }
            $msg .= '</ul>';
        } else {
            $msg = get_string('health_clusterorphandescnone', 'block_curr_admin'); // We should not reach here but put in just in case
        }

        return $msg;
    }

    function solution() {
        global $CFG;
        $msg = get_string('health_clusterorphansoln', 'block_curr_admin', $CFG->dirroot);
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
        return get_string('health_lostclass', 'block_curr_admin');
    }

    function severity() {
        return healthpage::SEVERITY_SIGNIFICANT;
    }

    function description() {
        if (count($this->unattachedClasses) > 0) {
            $msg = get_string('health_lostclassdesc', 'block_curr_admin', count($this->unattachedClasses));
            '';
        } else {
            $msg = get_string('health_lostclassdescnone', 'block_curr_admin'); // We should not reach here but put in just in case
        }

        return $msg;
    }

    function solution() {
        global $CFG;
        $msg = get_string('health_lostclasssoln', 'block_curr_admin', $CFG->wwwroot);
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
        return get_string('health_compexport', 'block_curr_admin');
    }

    function severity() {
        return healthpage::SEVERITY_CRITICAL;
    }

    function description() {
        return get_string('health_compexportdesc', 'block_curr_admin');
    }

    function solution() {
        global $CFG;
        return get_string('health_compexportsoln', 'block_curr_admin', array($CFG->wwwroot,$CFG->dirroot));
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
        return get_string('health_dupmenrol', 'block_curr_admin');
    }
    function description() {
        $count = array_reduce($this->counts, create_function('$a,$b', 'return $a + $b->dup;'), 0);
        return get_string('health_dupmenroldesc', 'block_curr_admin', $count);
    }
    function solution() {
        $msg = get_string('health_dupmenrolsoln', 'block_curr_admin');
        '';
        return $msg;
    }
}

/**
 * Checks the last cron run times of components
 */
class cron_lastruntimes_check extends crlm_health_check_base {
    private $blocks = array('curr_admin'); // empty array for none ?
    private $plugins = array(); // TBD

    function exists() {
        $threshold = time() - DAYSECS;
        foreach ($this->blocks as $block) {
            $lastcron = get_field('block', 'lastcron', 'name', $block);
            if ($lastcron < $threshold) {
                return true;
            }
        }
        foreach ($this->plugins as $plugin) {
            $lastcron = get_field('config_plugins', 'value', 'plugin', $plugin, 'name', 'lastcron');
            if ($lastcron < $threshold) {
                return true;
            }
        }
        $lasteliscron = get_field('elis_scheduled_tasks', 'MAX(lastruntime)', '', '');
        if ($lasteliscron < $threshold) {
            return true;
        }
        return false;
    }

    function title() {
        return get_string('health_cron_title', 'block_curr_admin');
    }

    function severity() {
        return healthpage::SEVERITY_NOTICE;
    }

    function description() {
        $description = '';
        $threshold = time() - DAYSECS;
        foreach ($this->blocks as $block) {
            $lastcron = get_field('block', 'lastcron', 'name', $block);
            if ($lastcron < $threshold) {
                $a = new stdClass;
                $a->name = $block;
                $a->lastcron = $lastcron ? userdate($lastcron) : get_string('cron_notrun', 'block_curr_admin');
                $description .= get_string('health_cron_block', 'block_curr_admin', $a);
            }
        }
        foreach ($this->plugins as $plugin) {
            $lastcron = get_field('config_plugins', 'value', 'plugin', $plugin, 'name', 'lastcron');
            if ($lastcron < $threshold) {
                $a = new stdClass;
                $a->name = $plugin;
                $a->lastcron = $lastcron ? userdate($lastcron) : get_string('cron_notrun', 'block_curr_admin');
                $description .= get_string('health_cron_plugin', 'block_curr_admin', $a);
            }
        }
        $lasteliscron = get_field('elis_scheduled_tasks', 'MAX(lastruntime)', '', '');
        if ($lasteliscron < $threshold) {
            $lastcron = $lasteliscron ? userdate($lasteliscron) : get_string('cron_notrun', 'block_curr_admin');
            $description .= get_string('health_cron_elis', 'block_curr_admin', $lastcron);
        }
        return $description;
    }

}

?>
