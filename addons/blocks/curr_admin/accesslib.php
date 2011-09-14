<?php
class context_level_elis_curriculum extends context_level_base {
    protected $table = 'crlm_curriculum';

    public function get_context_info($instanceid) {
        $basepath  = '/' . SYSCONTEXTID;
        $basedepth = 1;
        $result = true;

        return array($result, $basepath, $basedepth);
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        $name = '';
        $curriculum = get_record('crlm_curriculum', 'id', $context->instanceid);

        if (!empty($curriculum)) {
            if ($withprefix) {
                $name = 'curriculum: ';
            }
            $name .= $curriculum->name;
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        global $CFG;

        $SQL = "SELECT *
                  FROM {$CFG->prefix}capabilities";

        return $SQL;
    }

    public function get_child_contexts($context) {
        global $CFG, $context_cache;

        // Find
        // - tracks
        $trackcontextlevel = context_level_base::get_custom_context_level('track', 'block_curr_admin');
        $sql = " SELECT ctx.*
                 FROM {$CFG->prefix}context ctx
                 WHERE ctx.path LIKE '{$context->path}/%'
                       AND ctx.contextlevel = $trackcontextlevel
        ";
        $rs  = get_recordset_sql($sql);
        $records = array();
        while ($rec = rs_fetch_next_record($rs)) {
            $records[$rec->id] = $rec;
            $context_cache[$rec->contextlevel][$rec->instanceid] = $rec;
        }
        rs_close($rs);
        return $records;
    }

    public function get_component_string($component, $contextlevel) {
        $string = 'curriculum';

        return $string;
    }

    public function build_context_path($base, $emptyclause, $feedback=false) {
        global $CFG;

        $a = $CFG->prefix.'context';
        eval('$emptyclause = "'.$emptyclause.'";');

        // Curriculum
        $contextlevel = context_level_base::get_custom_context_level('curriculum', 'block_curr_admin');
        $sql = "UPDATE {$CFG->prefix}context
                   SET depth=2, path=".sql_concat("'$base/'", 'id')."
                 WHERE contextlevel=$contextlevel
                       AND EXISTS (SELECT 'x'
                                     FROM {$CFG->prefix}crlm_curriculum u
                                    WHERE u.id = {$CFG->prefix}context.instanceid)
                       $emptyclause ";
        execute_sql($sql, $feedback);
    }
}

class context_level_elis_track extends context_level_base {
    protected $table = 'crlm_track';

    public function get_context_info($instanceid) {
        global $CFG;
        $basepath  = null;
        $basedepth = null;
        $result = true;
        $curcontextlevel = context_level_base::get_custom_context_level('curriculum', 'block_curr_admin');
        $sql = "SELECT ctx.path, ctx.depth
                  FROM {$CFG->prefix}context       ctx
                  JOIN {$CFG->prefix}crlm_track    trk
                    ON (trk.curid=ctx.instanceid AND ctx.contextlevel={$curcontextlevel})
                 WHERE trk.id={$instanceid}";
        if ($p = get_record_sql($sql)) {
            $basepath  = $p->path;
            $basedepth = $p->depth;
        } else if ($trk = get_record('crlm_track', 'id', $instanceid)) {
            if ($parent = get_context_instance($curcontextlevel, $trk->curid)) {
                $basepath  = $parent->path;
                $basedepth = $parent->depth;
            } else {
                // course does not exist - modules can not exist without a course
                $result = false;
            }
        } else {
            // cm does not exist
            $result = false;
        }

        return array($result, $basepath, $basedepth);
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        $name = '';
        $track = get_record('crlm_track', 'id', $context->instanceid);

        if (!empty($track)) {
            if ($withprefix) {
                $name = 'track: ';
            }
            $name .= $track->name;
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        global $CFG;

        $SQL = "SELECT *
                  FROM {$CFG->prefix}capabilities";

        return $SQL;
    }

    public function get_child_contexts($context) {
        //no children by default
        return array();
    }

    public function get_component_string($component, $contextlevel) {
        $string = 'track';

        return $string;
    }

    public function build_context_path($base, $emptyclause, $feedback=false) {
        global $CFG;

        $a = 'ctx';
        eval('$ctxemptyclause = "'.$emptyclause.'";');

        // Tracks
        $trackcontextlevel = context_level_base::get_custom_context_level('track', 'block_curr_admin');
        $curcontextlevel = context_level_base::get_custom_context_level('curriculum', 'block_curr_admin');
        $sql = "INSERT INTO {$CFG->prefix}context_temp (id, path, depth)
                SELECT ctx.id, ".sql_concat('pctx.path', "'/'", 'ctx.id').", pctx.depth+1
                  FROM {$CFG->prefix}context ctx
                  JOIN {$CFG->prefix}crlm_track t ON ctx.instanceid=t.id
                  JOIN {$CFG->prefix}context pctx ON t.curid=pctx.instanceid
                 WHERE ctx.contextlevel=$trackcontextlevel
                       AND pctx.contextlevel=$curcontextlevel
                           AND NOT EXISTS (SELECT 'x'
                                           FROM {$CFG->prefix}context_temp temp
                                           WHERE temp.id = ctx.id)
                       $ctxemptyclause";
        execute_sql($sql, $feedback);

        context_level_base::flush_context_temp($feedback);
    }
}


class context_level_elis_course extends context_level_base {
    protected $table = 'crlm_course';

    public function get_context_info($instanceid) {
        $basepath  = '/' . SYSCONTEXTID;
        $basedepth = 1;
        $result = true;

        return array($result, $basepath, $basedepth);
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        $name = '';
        $course = get_record('crlm_course', 'id', $context->instanceid);

        if (!empty($course)) {
            if ($withprefix) {
                $name = 'course: ';
            }
            $name .= $course->name;
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        global $CFG;

        $SQL = "SELECT *
                  FROM {$CFG->prefix}capabilities";

        return $SQL;
    }

    public function get_child_contexts($context) {
        global $CFG, $context_cache;

        // Find
        // - classes
        $classcontextlevel = context_level_base::get_custom_context_level('class', 'block_curr_admin');
        $sql = " SELECT ctx.*
                 FROM {$CFG->prefix}context ctx
                 WHERE ctx.path LIKE '{$context->path}/%'
                       AND ctx.contextlevel = $classcontextlevel
        ";
        $rs  = get_recordset_sql($sql);
        $records = array();
        while ($rec = rs_fetch_next_record($rs)) {
            $records[$rec->id] = $rec;
            $context_cache[$rec->contextlevel][$rec->instanceid] = $rec;
        }
        rs_close($rs);
        return $records;
    }

    public function get_component_string($component, $contextlevel) {
        $string = 'course';

        return $string;
    }

    public function build_context_path($base, $emptyclause, $feedback=false) {
        global $CFG;

        $a = $CFG->prefix.'context';
        eval('$emptyclause = "'.$emptyclause.'";');

        // Course
        $contextlevel = context_level_base::get_custom_context_level('course', 'block_curr_admin');
        $sql = "UPDATE {$CFG->prefix}context
                   SET depth=2, path=".sql_concat("'$base/'", 'id')."
                 WHERE contextlevel=$contextlevel
                       AND EXISTS (SELECT 'x'
                                     FROM {$CFG->prefix}crlm_course u
                                    WHERE u.id = {$CFG->prefix}context.instanceid)
                       $emptyclause ";
        execute_sql($sql, $feedback);
    }
}

class context_level_elis_class extends context_level_base {
    protected $table = 'crlm_class';

    public function get_context_info($instanceid) {
        global $CFG;
        $basepath  = null;
        $basedepth = null;
        $result = true;
        $crscontextlevel = context_level_base::get_custom_context_level('course', 'block_curr_admin');
        $sql = "SELECT ctx.path, ctx.depth
                  FROM {$CFG->prefix}context       ctx
                  JOIN {$CFG->prefix}crlm_class    cls
                    ON (cls.courseid=ctx.instanceid AND ctx.contextlevel={$crscontextlevel})
                 WHERE cls.id={$instanceid}";
        if ($p = get_record_sql($sql)) {
            $basepath  = $p->path;
            $basedepth = $p->depth;
            $result = true;
        } else if ($cls = get_record('crlm_class', 'id', $instanceid)) {
            if ($parent = get_context_instance($crscontextlevel, $cls->courseid)) {
                $basepath  = $parent->path;
                $basedepth = $parent->depth;
            } else {
                // course does not exist - modules can not exist without a course
                $result = false;
            }
        } else {
            // cm does not exist
            $result = false;
            $basepath = '';
            $basedepth = '';
        }

        return array($result, $basepath, $basedepth);
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        $name = '';
        $cmclass = get_record('crlm_class', 'id', $context->instanceid);

        if (!empty($cmclass)) {
            if ($withprefix) {
                $name = 'track: ';
            }
            $name .= $cmclass->idnumber;
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        global $CFG;

        $SQL = "SELECT *
                  FROM {$CFG->prefix}capabilities";

        return $SQL;
    }

    public function get_child_contexts($context) {
        //no children by default
        return array();
    }

    public function get_component_string($component, $contextlevel) {
        $string = 'class';

        return $string;
    }

    public function build_context_path($base, $emptyclause, $feedback=false) {
        global $CFG;

        $a = 'ctx';
        eval('$ctxemptyclause = "'.$emptyclause.'";');

        // Class
        $classcontextlevel = context_level_base::get_custom_context_level('class', 'block_curr_admin');
        $coursecontextlevel = context_level_base::get_custom_context_level('course', 'block_curr_admin');
        $sql = "INSERT INTO {$CFG->prefix}context_temp (id, path, depth)
                SELECT ctx.id, ".sql_concat('pctx.path', "'/'", 'ctx.id').", pctx.depth+1
                  FROM {$CFG->prefix}context ctx
                  JOIN {$CFG->prefix}crlm_class c ON ctx.instanceid=c.id
                  JOIN {$CFG->prefix}context pctx ON c.courseid=pctx.instanceid
                 WHERE ctx.contextlevel=$classcontextlevel
                       AND pctx.contextlevel=$coursecontextlevel
                           AND NOT EXISTS (SELECT 'x'
                                           FROM {$CFG->prefix}context_temp temp
                                           WHERE temp.id = ctx.id)
                       $ctxemptyclause";
        execute_sql($sql, $feedback);

        context_level_base::flush_context_temp($feedback);
    }
}

class context_level_elis_user extends context_level_base {
    protected $table = 'crlm_user';

    public function get_context_info($instanceid) {
        $basepath  = '/' . SYSCONTEXTID;
        $basedepth = 1;
        $result = true;

        return array($result, $basepath, $basedepth);
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        $name = '';
        $user = get_record('crlm_user', 'id', $context->instanceid);

        if (!empty($user)) {
            if ($withprefix) {
                $name = 'user: ';
            }
            $name .= fullname($user);
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        global $CFG;

        $SQL = "SELECT *
                  FROM {$CFG->prefix}capabilities";

        return $SQL;
    }

    public function get_child_contexts($context) {
        //no children by default
        return array();
    }

    public function get_component_string($component, $contextlevel) {
        $string = 'user';

        return $string;
    }

    public function build_context_path($base, $emptyclause, $feedback=false) {
        global $CFG;

        $a = $CFG->prefix.'context';
        eval('$emptyclause = "'.$emptyclause.'";');

        // User
        $contextlevel = context_level_base::get_custom_context_level('user', 'block_curr_admin');
        $sql = "UPDATE {$CFG->prefix}context
                   SET depth=2, path=".sql_concat("'$base/'", 'id')."
                 WHERE contextlevel=$contextlevel
                       AND EXISTS (SELECT 'x'
                                     FROM {$CFG->prefix}crlm_user u
                                    WHERE u.id = {$CFG->prefix}context.instanceid)
                       $emptyclause ";
        execute_sql($sql, $feedback);
    }
}


class context_level_elis_cluster extends context_level_base {
    protected $table = 'crlm_cluster';

    public function get_context_info($instanceid) {
        global $CFG;
        $basepath  = null;
        $basedepth = null;
        $result = true;
        $contextlevel = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
        $sql = "SELECT ctx.path, ctx.depth
                  FROM {$CFG->prefix}context           ctx
                  JOIN {$CFG->prefix}crlm_cluster      cc
                    ON (cc.parent=ctx.instanceid AND ctx.contextlevel=$contextlevel)
                 WHERE cc.id={$instanceid}";
        if ($p = get_record_sql($sql)) {
            $basepath  = $p->path;
            $basedepth = $p->depth;
        } else if ($cluster = get_record('crlm_cluster', 'id', $instanceid)) {
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
            $result = false;
        }

        return array($result, $basepath, $basedepth);
    }

    public function print_context_name($context, $withprefix = true, $short = false) {
        $name = '';
        $cluster = get_record('crlm_cluster', 'id', $context->instanceid);

        if (!empty($cluster)) {
            if ($withprefix) {
                $name = 'cluster: ';
            }
            $name .= $cluster->name;
        }

        return $name;
    }

    public function fetch_context_capabilities_sql($context) {
        global $CFG;

        $SQL = "SELECT *
                  FROM {$CFG->prefix}capabilities";

        return $SQL;
    }

    public function get_child_contexts($context) {
        global $CFG, $context_cache;

        // Find
        // - sub-clusters
        $sql = " SELECT ctx.*
                 FROM {$CFG->prefix}context ctx
                 WHERE ctx.path LIKE '{$context->path}/%'
                       AND ctx.contextlevel = ".context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
        $rs  = get_recordset_sql($sql);
        $records = array();
        while ($rec = rs_fetch_next_record($rs)) {
            $records[$rec->id] = $rec;
            $context_cache[$rec->contextlevel][$rec->instanceid] = $rec;
        }
        rs_close($rs);
        return $records;
    }

    public function get_component_string($component, $contextlevel) {
        $string = 'cluster';

        return $string;
    }

    public function build_context_path($base, $emptyclause, $feedback=false) {
        // FIXME: support sub-clusters
        global $CFG;

        $a = 'ctx';
        eval('$ctxemptyclause = "'.$emptyclause.'";');
        $a = $CFG->prefix.'context';
        eval('$emptyclause = "'.$emptyclause.'";');

        // Cluster
        $contextlevel = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');
        $sql = "UPDATE {$CFG->prefix}context
                   SET depth=2, path=".sql_concat("'$base/'", 'id')."
                 WHERE contextlevel=$contextlevel
                       AND EXISTS (SELECT 'x'
                                     FROM {$CFG->prefix}crlm_cluster u
                                    WHERE u.id = {$CFG->prefix}context.instanceid
                                      AND u.depth=1)
                       $emptyclause ";
        execute_sql($sql, $feedback);

        // Deeper clusters - one query per depthlevel
        $maxdepth = get_field_sql("SELECT MAX(depth)
                                   FROM {$CFG->prefix}crlm_cluster");
        for ($n=2;$n<=$maxdepth;$n++) {
            $sql = "INSERT INTO {$CFG->prefix}context_temp (id, path, depth)
                    SELECT ctx.id, ".sql_concat('pctx.path', "'/'", 'ctx.id').", pctx.depth+1
                      FROM {$CFG->prefix}context ctx
                      JOIN {$CFG->prefix}crlm_cluster c ON ctx.instanceid=c.id
                      JOIN {$CFG->prefix}context pctx ON c.parent=pctx.instanceid
                     WHERE ctx.contextlevel=$contextlevel
                           AND pctx.contextlevel=$contextlevel
                           AND c.depth=$n
                           AND NOT EXISTS (SELECT 'x'
                                           FROM {$CFG->prefix}context_temp temp
                                           WHERE temp.id = ctx.id)
                           $ctxemptyclause";
            execute_sql($sql, $feedback);

            // this is needed after every loop
            // MDL-11532
            context_level_base::flush_context_temp($feedback);
        }
    }
}
?>