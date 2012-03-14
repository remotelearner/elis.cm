<?php
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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

class context_level_elis_curriculum extends context_level_base {
    protected $table = 'crlm_curriculum';

    public function get_context_info($instanceid, $strictness) {
        $basepath  = '/' . SYSCONTEXTID;
        $basedepth = 1;
        $result = true;

        return array($result, $basepath, $basedepth, null);
    }

    public function get_contextlevel_name() {
        return get_string('program', 'elis_program');
    }

    public function get_context_url($context) {
        return new moodle_url('/elis/program/curriculum.php', array('id'=>$context->instanceid));
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        global $DB;
        $name = '';
        $curriculum = $DB->get_record('crlm_curriculum', array('id' => $context->instanceid));

        if (!empty($curriculum)) {
            if ($withprefix) {
                $name = 'curriculum: ';
            }
            $name .= $curriculum->name;
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        $SQL = 'SELECT *
                  FROM {capabilities}';

        return array($SQL, null);
    }

    public function get_child_contexts($context) {
        global $CFG, $DB, $ACCESSLIB_PRIVATE;

        $cache = $ACCESSLIB_PRIVATE->contexcache;

        // Find
        // - tracks
        $trackcontextlevel = context_level_base::get_custom_context_level('track', 'elis_program');
        $sql = "SELECT ctx.*
                  FROM {context} ctx
                 WHERE ctx.path LIKE ?
                       AND ctx.contextlevel = $trackcontextlevel";
        $params = array("{$context->path}/%");
        $records = $DB->get_recordset_sql($sql, $params);
        $array = array();
        foreach ($records as $rec) {
            $array[$rec->id] = $rec;
            $cache->add($rec);
        }
        return $array;
    }

    public function build_context_path($base, $emptyclause) {
        global $CFG, $DB;

        $a = '{context}';
        eval('$emptyclause = "'.$emptyclause.'";');

        // Curriculum
        $contextlevel = context_level_base::get_custom_context_level('curriculum', 'elis_program');
        $sql = 'UPDATE {context}
                   SET depth=2, path='.$DB->sql_concat("'$base/'", 'id')."
                 WHERE contextlevel = $contextlevel
                       AND EXISTS (SELECT 'x'
                                     FROM {crlm_curriculum} u
                                    WHERE u.id = {context}.instanceid)
                       $emptyclause";
        $DB->execute($sql);
    }
}

class context_level_elis_track extends context_level_base {
    protected $table = 'crlm_track';

    public function get_context_info($instanceid, $strictness) {
        global $CFG, $DB;
        $basepath  = null;
        $basedepth = null;
        $result = true;
        $error_message = null;
        $curcontextlevel = context_level_base::get_custom_context_level('curriculum', 'elis_program');
        $sql = "SELECT ctx.path, ctx.depth
                  FROM {context}       ctx
                  JOIN {crlm_track}    trk
                    ON (trk.curid=ctx.instanceid AND ctx.contextlevel={$curcontextlevel})
                 WHERE trk.id={$instanceid}";
        if ($p = $DB->get_record_sql($sql)) {
            $basepath  = $p->path;
            $basedepth = $p->depth;
        } else if ($trk = $DB->get_record('crlm_track', array('id' => $instanceid), '*', $strictness)) {
            if ($parent = get_context_instance($curcontextlevel, $trk->curid)) {
                $basepath  = $parent->path;
                $basedepth = $parent->depth;
            } else {
                // curriculum does not exist - tracks can not exist without a curriculum
                $error_message = "track ($instanceid) is attached to an invalid curriculum";
                $result = false;
            }
        } else {
            // track does not exist
            $error_message = "incorrect track id ($instanceid)";
            $result = false;
        }

        return array($result, $basepath, $basedepth, $error_message);
    }

    public function get_contextlevel_name() {
        return get_string('track', 'elis_program');
    }

    public function get_context_url($context) {
        return new moodle_url('/elis/program/track.php', array('id'=>$context->instanceid));
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        global $DB;
        $name = '';
        $track = $DB->get_record('crlm_track', array('id' => $context->instanceid));

        if (!empty($track)) {
            if ($withprefix) {
                $name = 'track: ';
            }
            $name .= $track->name;
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        $SQL = 'SELECT *
                  FROM {capabilities}';

        return array($SQL,null);
    }

    public function get_child_contexts($context) {
        //no children by default
        return array();
    }

    public function build_context_path($base, $emptyclause) {
        global $CFG, $DB;

        $a = 'ctx';
        eval('$ctxemptyclause = "'.$emptyclause.'";');

        // Tracks
        $trackcontextlevel = context_level_base::get_custom_context_level('track', 'elis_program');
        $curcontextlevel = context_level_base::get_custom_context_level('curriculum', 'elis_program');
        $sql = 'INSERT INTO {context_temp} (id, path, depth)
                SELECT ctx.id, '.$DB->sql_concat('pctx.path', "'/'", 'ctx.id').", pctx.depth+1
                  FROM {context} ctx
                  JOIN {crlm_track} t ON ctx.instanceid=t.id
                  JOIN {context} pctx ON t.curid=pctx.instanceid
                 WHERE ctx.contextlevel=$trackcontextlevel
                       AND pctx.contextlevel=$curcontextlevel
                           AND NOT EXISTS (SELECT 'x'
                                           FROM {context_temp} temp
                                           WHERE temp.id = ctx.id)
                       $ctxemptyclause";
        $DB->execute($sql);

        context_level_base::flush_context_temp();
    }
}


class context_level_elis_course extends context_level_base {
    protected $table = 'crlm_course';

    public function get_context_info($instanceid, $strictness) {
        $basepath  = '/' . SYSCONTEXTID;
        $basedepth = 1;
        $result = true;

        return array($result, $basepath, $basedepth, null);
    }

    public function get_contextlevel_name() {
        return get_string('course', 'elis_program');
    }

    public function get_context_url($context) {
        return new moodle_url('/elis/program/course.php', array('id'=>$context->instanceid));
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        global $DB;
        $name = '';
        $course = $DB->get_record('crlm_course', array('id' => $context->instanceid));

        if (!empty($course)) {
            if ($withprefix) {
                $name = 'course: ';
            }
            $name .= $course->name;
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        $SQL = "SELECT *
                  FROM {capabilities}";

        return array($SQL, null);
    }

    public function get_child_contexts($context) {
        global $CFG, $DB, $ACCESSLIB_PRIVATE;

        $cache = $ACCESSLIB_PRIVATE->contexcache;

        // Find
        // - classes
        $classcontextlevel = context_level_base::get_custom_context_level('class', 'elis_program');
        $sql = "SELECT ctx.*
                  FROM {context} ctx
                 WHERE ctx.path LIKE ?
                       AND ctx.contextlevel = $classcontextlevel";
        $params = array("{$context->path}/%");
        $records = $DB->get_recordset_sql($sql, $params);
        $array = array();
        foreach ($records as $rec) {
            $array[$rec->id] = $rec;
            $cache->add($rec);
        }
        return $array;
    }

    public function build_context_path($base, $emptyclause) {
        global $CFG, $DB;

        $a = '{context}';
        eval('$emptyclause = "'.$emptyclause.'";');

        // Course
        $contextlevel = context_level_base::get_custom_context_level('course', 'elis_program');
        $sql = 'UPDATE {context}
                   SET depth=2, path='.$DB->sql_concat("'$base/'", 'id')."
                 WHERE contextlevel=$contextlevel
                       AND EXISTS (SELECT 'x'
                                     FROM {crlm_course} u
                                    WHERE u.id = {context}.instanceid)
                       $emptyclause ";
        $DB->execute($sql);
    }
}

class context_level_elis_class extends context_level_base {
    protected $table = 'crlm_class';

    public function get_context_info($instanceid, $strictness) {
        global $CFG, $DB;
        $basepath  = null;
        $basedepth = null;
        $result = true;
        $errormessage = null;
        $crscontextlevel = context_level_base::get_custom_context_level('course', 'elis_program');
        $sql = "SELECT ctx.path, ctx.depth
                  FROM {context}       ctx
                  JOIN {crlm_class}    cls
                    ON (cls.courseid=ctx.instanceid AND ctx.contextlevel={$crscontextlevel})
                 WHERE cls.id={$instanceid}";
        if ($p = $DB->get_record_sql($sql)) {
            $basepath  = $p->path;
            $basedepth = $p->depth;
            $result = true;
        } else if ($cls = $DB->get_record('crlm_class', array('id' => $instanceid), '*', $strictness)) {
            if ($parent = get_context_instance($crscontextlevel, $cls->courseid)) {
                $basepath  = $parent->path;
                $basedepth = $parent->depth;
            } else {
                // course does not exist - classes can not exist without a
                // course
                $errormessage = "class ($instanceid) is attached to an invalid course";
                $result = false;
            }
        } else {
            // class does not exist
            $errormessage = "incorrect class id ($instanceid)";
            $result = false;
            $basepath = '';
            $basedepth = '';
        }

        return array($result, $basepath, $basedepth, $errormessage);
    }

    public function get_context_url($context) {
        return new moodle_url('/elis/program/class.php', array('id'=>$context->instanceid));
    }

    public function get_contextlevel_name() {
        return get_string('class', 'elis_program');
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        global $DB;
        $name = '';
        $cmclass = $DB->get_record('crlm_class', array('id' => $context->instanceid));

        if (!empty($cmclass)) {
            if ($withprefix) {
                $name = 'class: ';
            }
            $name .= $cmclass->idnumber;
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        $SQL = 'SELECT *
                  FROM {capabilities}';

        return array($SQL,null);
    }

    public function get_child_contexts($context) {
        //no children by default
        return array();
    }

    public function build_context_path($base, $emptyclause) {
        global $CFG, $DB;

        $a = 'ctx';
        eval('$ctxemptyclause = "'.$emptyclause.'";');

        // Class
        $classcontextlevel = context_level_base::get_custom_context_level('class', 'elis_program');
        $coursecontextlevel = context_level_base::get_custom_context_level('course', 'elis_program');
        $sql = 'INSERT INTO {context_temp} (id, path, depth)
                SELECT ctx.id, '.$DB->sql_concat('pctx.path', "'/'", 'ctx.id').", pctx.depth+1
                  FROM {context} ctx
                  JOIN {crlm_class} c ON ctx.instanceid=c.id
                  JOIN {context} pctx ON c.courseid=pctx.instanceid
                 WHERE ctx.contextlevel=$classcontextlevel
                       AND pctx.contextlevel=$coursecontextlevel
                           AND NOT EXISTS (SELECT 'x'
                                           FROM {context_temp} temp
                                           WHERE temp.id = ctx.id)
                       $ctxemptyclause";
        $DB->execute($sql);

        context_level_base::flush_context_temp();
    }
}

class context_level_elis_user extends context_level_base {
    protected $table = 'crlm_user';

    public function get_context_info($instanceid, $strictness) {
        $basepath  = '/' . SYSCONTEXTID;
        $basedepth = 1;
        $result = true;

        return array($result, $basepath, $basedepth, null);
    }

    public function get_contextlevel_name() {
        return get_string('user', 'elis_program');
    }

    public function get_context_url($context) {
        return new moodle_url('/elis/program/user.php', array('id'=>$context->instanceid));
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        global $DB;
        $name = '';
        $user = $DB->get_record('crlm_user', array('id' => $context->instanceid));

        if (!empty($user)) {
            if ($withprefix) {
                $name = 'user: ';
            }
            $name .= fullname($user);
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        $SQL = 'SELECT *
                  FROM {capabilities}';

        return array($SQL,null);
    }

    public function get_child_contexts($context) {
        //no children by default
        return array();
    }

    public function build_context_path($base, $emptyclause) {
        global $CFG, $DB;

        $a = '{context}';
        eval('$emptyclause = "'.$emptyclause.'";');

        // User
        $contextlevel = context_level_base::get_custom_context_level('user', 'elis_program');
        $sql = 'UPDATE {context}
                   SET depth=2, path='.$DB->sql_concat("'$base/'", 'id')."
                 WHERE contextlevel=$contextlevel
                       AND EXISTS (SELECT 'x'
                                     FROM {crlm_user} u
                                    WHERE u.id = {context}.instanceid)
                       $emptyclause";
        $DB->execute($sql);
    }
}


class context_level_elis_cluster extends context_level_base {
    protected $table = 'crlm_cluster';

    public function get_context_info($instanceid, $strictness) {
        global $CFG, $DB;
        $basepath  = null;
        $basedepth = null;
        $result = true;
        $errormessage = null;
        $contextlevel = context_level_base::get_custom_context_level('cluster', 'elis_program');
        $sql = "SELECT ctx.path, ctx.depth
                  FROM {context}           ctx
                  JOIN {crlm_cluster}      cc
                    ON (cc.parent=ctx.instanceid AND ctx.contextlevel=$contextlevel)
                 WHERE cc.id={$instanceid}";
        if ($p = $DB->get_record_sql($sql)) {
            $basepath  = $p->path;
            $basedepth = $p->depth;
        } else if ($cluster = $DB->get_record('crlm_cluster', array('id' => $instanceid), '*', $strictness)) {
            if (empty($cluster->parent)) {
                // ok - this is a top cluster
                $basepath  = '/' . SYSCONTEXTID;
                $basedepth = 1;
            } else if ($parent = get_context_instance($contextlevel, $cluster->parent)) {
                $basepath  = $parent->path;
                $basedepth = $parent->depth;
            } else {
                // wrong parent cluster - no big deal, this can be fixed later
                $basepath  = null;
                $basedepth = 0;
            }
        } else {
            // incorrect cluster id
            $errormessage = "incorrect cluster id ($instanceid)";
            $result = false;
        }

        return array($result, $basepath, $basedepth, $errormessage);
    }

    public function get_contextlevel_name() {
        return get_string('cluster', 'elis_program');
    }

    public function get_context_url($context) {
        return new moodle_url('/elis/program/cluster.php', array('id'=>$context->instanceid));
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        global $DB;
        $name = '';
        $cluster = $DB->get_record('crlm_cluster', array('id' => $context->instanceid));

        if (!empty($cluster)) {
            if ($withprefix) {
                $name = 'cluster: ';
            }
            $name .= $cluster->name;
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        $SQL = 'SELECT *
                  FROM {capabilities}';

        return $SQL;
    }

    public function get_child_contexts($context) {
        global $CFG, $DB, $ACCESSLIB_PRIVATE;

        $cache = $ACCESSLIB_PRIVATE->contexcache;

        // Find
        // - sub-clusters
        $sql = 'SELECT ctx.*
                  FROM {context} ctx
                 WHERE ctx.path LIKE ?
                       AND ctx.contextlevel = '.context_level_base::get_custom_context_level('cluster', 'elis_program');
        $params = array("{$context->path}/%");
        $records = $DB->get_recordset_sql($sql);
        $array = array();
        foreach ($records as $rec) {
            $array[$rec->id] = $rec;
            $cache->add($rec);
        }
        return $array;
    }

    public function build_context_path($base, $emptyclause) {
        global $CFG, $DB;

        $a = 'ctx';
        eval('$ctxemptyclause = "'.$emptyclause.'";');
        $a = '{context}';
        eval('$emptyclause = "'.$emptyclause.'";');

        // Cluster
        $contextlevel = context_level_base::get_custom_context_level('cluster', 'elis_program');
        $sql = "UPDATE {context}
                   SET depth=2, path=".$DB->sql_concat("'$base/'", 'id')."
                 WHERE contextlevel=$contextlevel
                       AND EXISTS (SELECT 'x'
                                     FROM {crlm_cluster} u
                                    WHERE u.id = {context}.instanceid
                                      AND u.depth=1)
                       $emptyclause";
        $DB->execute($sql);

        // Deeper clusters - one query per depthlevel
        $maxdepth = $DB->get_field_sql('SELECT MAX(depth) FROM {crlm_cluster}');
        for ($n=2;$n<=$maxdepth;$n++) {
            $sql = 'INSERT INTO {context_temp} (id, path, depth)
                    SELECT ctx.id, '.$DB->sql_concat('pctx.path', "'/'", 'ctx.id').", pctx.depth+1
                      FROM {context} ctx
                      JOIN {crlm_cluster} c ON ctx.instanceid=c.id
                      JOIN {context} pctx ON c.parent=pctx.instanceid
                     WHERE ctx.contextlevel=$contextlevel
                           AND pctx.contextlevel=$contextlevel
                           AND c.depth=$n
                           AND NOT EXISTS (SELECT 'x'
                                           FROM {context_temp} temp
                                           WHERE temp.id = ctx.id)
                           $ctxemptyclause";
            $DB->execute($sql);

            // this is needed after every loop
            // MDL-11532
            context_level_base::flush_context_temp();
        }
    }
}
?>