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


define('CONTEXT_ELIS_PROGRAM', 1001);
define('CONTEXT_ELIS_TRACK',   1002);
define('CONTEXT_ELIS_COURSE',  1003);
define('CONTEXT_ELIS_CLASS',   1004);
define('CONTEXT_ELIS_USER',    1005);
define('CONTEXT_ELIS_USERSET', 1006);

require_once($CFG->dirroot . '/elis/core/accesslib.php');

context_elis_helper::$alllevels = array(
        CONTEXT_ELIS_PROGRAM => 'context_elis_program',
        CONTEXT_ELIS_TRACK   => 'context_elis_track',
        CONTEXT_ELIS_COURSE  => 'context_elis_course',
        CONTEXT_ELIS_CLASS   => 'context_elis_class',
        CONTEXT_ELIS_USER    => 'context_elis_user',
        CONTEXT_ELIS_USERSET => 'context_elis_userset',
    );
/**
 * This map array is used for historical purposes - where the context level names are ingrained into code.
 * These names should not be used going forward, you should instead use the constants directly.
 * @var type
 */
context_elis_helper::$namelevelmap = array(
        'curriculum'        => CONTEXT_ELIS_PROGRAM,
        'track'             => CONTEXT_ELIS_TRACK,
        'course'            => CONTEXT_ELIS_COURSE,
        'class'             => CONTEXT_ELIS_CLASS,
        'user'              => CONTEXT_ELIS_USER,
        'cluster'           => CONTEXT_ELIS_USERSET
    );


context_elis::$alllevels = context_elis_helper::$alllevels;
context_elis::$namelevelmap = context_elis_helper::$namelevelmap;
context_elis::$component = 'elis_program';

/**
 * ELIS program context
 */
class context_elis_program extends context_elis {
    /**
     * Please use context_elis_program::instance($programid) if you need the instance of this context.
     * Alternatively if you know only the context id use context::instance_by_id($contextid)
     *
     * @param stdClass $record
     */
    protected function __construct(stdClass $record) {
        parent::__construct($record);
        if ($record->contextlevel != CONTEXT_ELIS_PROGRAM) {
            throw new coding_exception('Invalid $record->contextlevel in context_elis_program constructor.');
        }
    }

    /**
     * Returns human readable context level name.
     *
     * @static
     * @return string the human readable context level name.
     */
    public static function get_level_name() {
        return get_string('curriculum', 'elis_program');
    }

    /**
     * Returns human readable context identifier.
     *
     * @param boolean $withprefix whether to prefix the name of the context with Category
     * @param boolean $short does not apply to course categories
     * @return string the human readable context name.
     */
    public function get_context_name($withprefix = true, $short = false) {
        global $DB;

        $name = '';
        if ($program = $DB->get_record(curriculum::TABLE, array('id'=>$this->_instanceid))) {
            if ($withprefix){
                $name = get_string('curriculum', 'elis_program').': ';
            }
            $name .= format_string($program->name, true, array('context' => $this));
        }
        return $name;
    }

    /**
     * Returns the most relevant URL for this context.
     *
     * @return moodle_url
     */
    public function get_url() {
        $params = array(
            's'      => 'cur',
            'action' => 'view',
            'id'     => $this->_instanceid
        );
        return new moodle_url('/elis/program/index.php', $params);
    }

    /**
     * Returns array of relevant context capability records.
     *
     * @return array
     */
    public function get_capabilities() {
        global $DB;

        $sort = 'ORDER BY contextlevel,component,name';   // To group them sensibly for display

        $params = array();
        $sql = "SELECT *
                  FROM {capabilities}
                 WHERE contextlevel = ".CONTEXT_ELIS_PROGRAM;

        return $DB->get_records_sql($sql.' '.$sort, $params);
    }

    /**
     * Returns ELIS program context instance.
     *
     * @static
     * @param int $instanceid
     * @param int $strictness
     * @return context_coursecat context instance
     */
    public static function instance($instanceid, $strictness = MUST_EXIST) {
        global $DB;

        if ($context = context::cache_get(CONTEXT_ELIS_PROGRAM, $instanceid)) {
            return $context;
        }

        if (!$record = $DB->get_record('context', array('contextlevel'=>CONTEXT_ELIS_PROGRAM, 'instanceid'=>$instanceid))) {
            if ($program = $DB->get_record(curriculum::TABLE, array('id'=>$instanceid), 'id,idnumber', $strictness)) {
                $record = context::insert_context_record(CONTEXT_ELIS_PROGRAM, $program->id, '/'.SYSCONTEXTID);
            }
        }

        if ($record) {
            $context = new context_elis_program($record);
            context::cache_add($context);
            return $context;
        }

        return false;
    }

    /**
     * Returns immediate child contexts of program and all tracks
     *
     * @return array
     */
    public function get_child_contexts() {
        global $DB;

        $result = array();

        $sql = "SELECT ctx.*
                  FROM {context} ctx
                 WHERE ctx.path LIKE ? AND (ctx.depth = ? OR ctx.contextlevel = ?)";
        $params = array($this->_path.'/%', $this->depth+1, CONTEXT_ELIS_PROGRAM);
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $result[$record->id] = context_elis::create_instance_from_record($record);
        }
        unset($records);

        return $result;
    }

    /**
     * Create missing context instances at ELIS program context level
     * @static
     */
    protected static function create_level_instances() {
        global $DB;

        $sql = "INSERT INTO {context} (contextlevel, instanceid)
                SELECT ".CONTEXT_ELIS_PROGRAM.", ep.id
                  FROM {".curriculum::TABLE."} ep
                 WHERE NOT EXISTS (SELECT 'x'
                                     FROM {context} cx
                                    WHERE ep.id = cx.instanceid AND cx.contextlevel=".CONTEXT_ELIS_PROGRAM.")";
        $DB->execute($sql);
    }

    /**
     * Returns sql necessary for purging of stale context instances.
     *
     * @static
     * @return string cleanup SQL
     */
    protected static function get_cleanup_sql() {
        $sql = "
                  SELECT c.*
                    FROM {context} c
         LEFT OUTER JOIN {".curriculum::TABLE."} ep ON c.instanceid = cc.id
                   WHERE ep.id IS NULL AND c.contextlevel = ".CONTEXT_ELIS_PROGRAM."
               ";

        return $sql;
    }

    /**
     * Rebuild context paths and depths at ELIS program context level.
     *
     * @static
     * @param $force
     */
    protected static function build_paths($force) {
        global $DB;

        if ($force or $DB->record_exists_select('context', "contextlevel = ".CONTEXT_ELIS_PROGRAM." AND (depth = 0 OR path IS NULL)")) {
            if ($force) {
                $ctxemptyclause = $emptyclause = '';
            } else {
                $ctxemptyclause = "AND (ctx.path IS NULL OR ctx.depth = 0)";
                $emptyclause    = "AND ({context}.path IS NULL OR {context}.depth = 0)";
            }

            $base = '/'.SYSCONTEXTID;

            // Normal top level categories
            $sql = "UPDATE {context}
                       SET depth=2,
                           path=".$DB->sql_concat("'$base/'", 'id')."
                     WHERE contextlevel=".CONTEXT_ELIS_PROGRAM."
                           AND EXISTS (SELECT 'x'
                                         FROM {course_categories} ep
                                        WHERE ep.id = {context}.instanceid)
                           $emptyclause";
            $DB->execute($sql);
        }
    }
}

/**
 * ELIS track context
 */
class context_elis_track extends context_elis {
    /**
     * Please use context_elis_track::instance($trackid) if you need the instance of this context.
     * Alternatively if you know only the context id use context::instance_by_id($contextid)
     *
     * @param stdClass $record
     */
    protected function __construct(stdClass $record) {
        parent::__construct($record);
        if ($record->contextlevel != CONTEXT_ELIS_TRACK) {
            throw new coding_exception('Invalid $record->contextlevel in context_elis_track constructor.');
        }
    }

    /**
     * Returns human readable context level name.
     *
     * @static
     * @return string the human readable context level name.
     */
    public static function get_level_name() {
        return get_string('track', 'elis_program');
    }

    /**
     * Returns human readable context identifier.
     *
     * @param boolean $withprefix whether to prefix the name of the context
     * @param boolean $short whether to use the short name of the thing
     * @return string the human readable context name.
     */
    public function get_context_name($withprefix = true, $short = false) {
        global $DB;

        $name = '';
        $track = $DB->get_record(track::TABLE, array('id'=>$this->_instanceid));
        if (!empty($track)) {
            if ($withprefix) {
                $name = get_string('track', 'elis_program').': ';
            }
            if ($short) {
                $name .= format_string($track->idnumber, true, array('context' => $this));
            } else {
                $name .= format_string($track->name, true, array('context' => $this));
            }
        }
        return $name;
    }

    /**
     * Returns the most relevant URL for this context.
     *
     * @return moodle_url
     */
    public function get_url() {
        $params = array(
            's'      => 'trk',
            'action' => 'view',
            'id'     => $this->_instanceid
        );
        return new moodle_url('/elis/program/index.php', $params);
    }

    /**
     * Returns array of relevant context capability records.
     *
     * @return array
     */
    public function get_capabilities() {
        global $DB;

        $sort = 'ORDER BY contextlevel,component,name';   // To group them sensibly for display

        $params = array();
        $sql = "SELECT *
                  FROM {capabilities}
                 WHERE contextlevel IN (".CONTEXT_ELIS_TRACK.",".CONTEXT_ELIS_CLASS.")";

        return $DB->get_records_sql($sql.' '.$sort, $params);
    }

    /**
     * Returns ELIS track context instance.
     *
     * @static
     * @param int $instanceid
     * @param int $strictness
     * @return context_elis_track context instance
     */
    public static function instance($instanceid, $strictness = MUST_EXIST) {
        global $DB;

        if ($context = context::cache_get(CONTEXT_ELIS_TRACK, $instanceid)) {
            return $context;
        }

        $record = $DB->get_record('context', array('contextlevel'=>CONTEXT_ELIS_TRACK, 'instanceid'=>$instanceid));
        if (empty($record)) {
            $track = $DB->get_record(track::TABLE, array('id'=>$instanceid), 'id,idnumber,curid', $strictness);
            if (!empty($track)) {
                $parentcontext = context_elis_program::instance($track->curid);
                $record = context::insert_context_record(CONTEXT_ELIS_TRACK, $track->id, $parentcontext->path);
            }
        }

        if (!empty($record)) {
            $context = new context_elis_track($record);
            context::cache_add($context);
            return $context;
        }

        return false;
    }

    /**
     * Returns immediate child contexts of track
     * decendents beyond immediate children are not returned.
     *
     * @return array
     */
    public function get_child_contexts() {
        global $DB;

        $result = array();

        $sql = "SELECT ctx.*
                  FROM {context} ctx
                 WHERE ctx.path LIKE ? AND (ctx.depth = ? OR ctx.contextlevel = ?)";
        $params = array($this->_path.'/%', $this->depth+1, CONTEXT_ELIS_TRACK);
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $result[$record->id] = context_elis::create_instance_from_record($record);
        }
        unset($records);

        return $result;
    }

    /**
     * Create missing context instances at ELIS track context level
     * @static
     */
    protected static function create_level_instances() {
        global $DB;

        $sql = "INSERT INTO {context} (contextlevel, instanceid)
                SELECT ".CONTEXT_ELIS_TRACK.", ep.id
                  FROM {".track::TABLE."} ep
                 WHERE NOT EXISTS (SELECT 'x'
                                     FROM {context} cx
                                    WHERE ep.id = cx.instanceid AND cx.contextlevel=".CONTEXT_ELIS_TRACK.")";
        $DB->execute($sql);
    }

    /**
     * Returns sql necessary for purging of stale context instances.
     *
     * @static
     * @return string cleanup SQL
     */
    protected static function get_cleanup_sql() {
        $sql = "
                  SELECT c.*
                    FROM {context} c
         LEFT OUTER JOIN {".track::TABLE."} ep ON c.instanceid = cc.id
                   WHERE ep.id IS NULL AND c.contextlevel = ".CONTEXT_ELIS_TRACK."
               ";

        return $sql;
    }

    /**
     * Rebuild context paths and depths at ELIS track context level.
     *
     * @static
     * @param $force
     */
    protected static function build_paths($force) {
        global $DB;

        if ($force or $DB->record_exists_select('context', "contextlevel = ".CONTEXT_ELIS_TRACK." AND (depth = 0 OR path IS NULL)")) {
            $ctxemptyclause = ($force) ? '' : "AND (ctx.path IS NULL OR ctx.depth = 0)";

            $sql = "INSERT INTO {context_temp} (id, path, depth)
                    SELECT ctx.id, ".$DB->sql_concat('pctx.path', "'/'", 'ctx.id').", pctx.depth+1
                      FROM {context} ctx
                      JOIN {crlm_track} trk ON (trk.id = ctx.instanceid AND ctx.contextlevel = ".CONTEXT_ELIS_TRACK.")
                      JOIN {context} pctx ON (pctx.instanceid = trk.curid AND pctx.contextlevel = ".CONTEXT_ELIS_PROGRAM.")
                     WHERE pctx.path IS NOT NULL AND pctx.depth > 0
                           $ctxemptyclause";
            $trans = $DB->start_delegated_transaction();
            $DB->delete_records('context_temp');
            $DB->execute($sql);
            context::merge_context_temp_table();
            $DB->delete_records('context_temp');
            $trans->allow_commit();
        }
    }
}

/**
 * ELIS Course Context
 */
class context_elis_course extends context_elis {
    /**
     * Please use context_elis_course::instance($courseid) if you need the instance of this context.
     * Alternatively if you know only the context id use context::instance_by_id($contextid)
     *
     * @param stdClass $record
     */
    protected function __construct(stdClass $record) {
        parent::__construct($record);
        if ($record->contextlevel != CONTEXT_ELIS_COURSE) {
            throw new coding_exception('Invalid $record->contextlevel in context_elis_course constructor.');
        }
    }

    /**
     * Returns human readable context level name.
     *
     * @static
     * @return string the human readable context level name.
     */
    public static function get_level_name() {
        return get_string('course', 'elis_program');
    }

    /**
     * Returns human readable context identifier.
     *
     * @param boolean $withprefix whether to prefix the name of the context
     * @param boolean $short whether to use the short name of the thing.
     * @return string the human readable context name.
     */
    public function get_context_name($withprefix = true, $short = false) {
        global $DB;

        $name = '';
        $course = $DB->get_record(course::TABLE, array('id'=>$this->_instanceid));
        if (!empty($course)) {
            if ($withprefix) {
                $name = get_string('course', 'elis_program').': ';
            }
            if ($short) {
                $name .= format_string($course->idnumber, true, array('context' => $this));
            } else {
                $name .= format_string($course->name, true, array('context' => $this));
            }
        }
        return $name;
    }

    /**
     * Returns the most relevant URL for this context.
     *
     * @return moodle_url
     */
    public function get_url() {
        $params = array(
            's'      => 'crs',
            'action' => 'view',
            'id'     => $this->_instanceid
        );
        return new moodle_url('/elis/program/index.php', $params);
    }

    /**
     * Returns array of relevant context capability records.
     *
     * @return array
     */
    public function get_capabilities() {
        global $DB;

        $sort = 'ORDER BY contextlevel,component,name';   // To group them sensibly for display

        $params = array();
        $sql = "SELECT *
                  FROM {capabilities}
                 WHERE contextlevel IN (".CONTEXT_ELIS_COURSE.",".CONTEXT_ELIS_CLASS.")";

        return $DB->get_records_sql($sql.' '.$sort, $params);
    }

    /**
     * Returns ELIS course context instance.
     *
     * @static
     * @param int $instanceid
     * @param int $strictness
     * @return context_elis_course context instance
     */
    public static function instance($instanceid, $strictness = MUST_EXIST) {
        global $DB;

        if ($context = context::cache_get(CONTEXT_ELIS_COURSE, $instanceid)) {
            return $context;
        }

        $record = $DB->get_record('context', array('contextlevel'=>CONTEXT_ELIS_COURSE, 'instanceid'=>$instanceid));
        if (empty($record)) {
            $course = $DB->get_record(course::TABLE, array('id'=>$instanceid), 'id,idnumber', $strictness);
            if (!empty($course)) {
                $parentpath = '/'.SYSCONTEXTID;
                $record = context::insert_context_record(CONTEXT_ELIS_COURSE, $course->id, $parentpath);
            }
        }

        if (!empty($record)) {
            $context = new context_elis_course($record);
            context::cache_add($context);
            return $context;
        }

        return false;
    }

    /**
     * Returns immediate child contexts of course (most likely classes),
     * decendents beyond immediate children are not returned.
     *
     * @return array
     */
    public function get_child_contexts() {
        global $DB;

        $result = array();

        $sql = "SELECT ctx.*
                  FROM {context} ctx
                 WHERE ctx.path LIKE ? AND (ctx.depth = ? OR ctx.contextlevel = ?)";
        $params = array($this->_path.'/%', $this->depth+1, CONTEXT_ELIS_COURSE);
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $result[$record->id] = context_elis::create_instance_from_record($record);
        }
        unset($records);

        return $result;
    }

    /**
     * Create missing context instances at ELIS course context level
     * @static
     */
    protected static function create_level_instances() {
        global $DB;

        $sql = "INSERT INTO {context} (contextlevel, instanceid)
                SELECT ".CONTEXT_ELIS_COURSE.", ep.id
                  FROM {".course::TABLE."} ep
                 WHERE NOT EXISTS (SELECT 'x'
                                     FROM {context} cx
                                    WHERE ep.id = cx.instanceid AND cx.contextlevel=".CONTEXT_ELIS_COURSE.")";
        $DB->execute($sql);
    }

    /**
     * Returns sql necessary for purging of stale context instances.
     *
     * @static
     * @return string cleanup SQL
     */
    protected static function get_cleanup_sql() {
        $sql = "
                  SELECT c.*
                    FROM {context} c
         LEFT OUTER JOIN {".course::TABLE."} ep ON c.instanceid = cc.id
                   WHERE ep.id IS NULL AND c.contextlevel = ".CONTEXT_ELIS_COURSE."
               ";

        return $sql;
    }

    /**
     * Rebuild context paths and depths at ELIS course context level.
     *
     * @static
     * @param $force
     */
    protected static function build_paths($force) {
        global $DB;

        if ($force or $DB->record_exists_select('context', "contextlevel = ".CONTEXT_ELIS_COURSE." AND (depth = 0 OR path IS NULL)")) {

            $emptyclause = ($force) ? '' : "AND ({context}.path IS NULL OR {context}.depth = 0)";
            $base = '/'.SYSCONTEXTID;

            // Normal top level categories
            $sql = "UPDATE {context}
                       SET depth=2,
                           path=".$DB->sql_concat("'$base/'", 'id')."
                     WHERE contextlevel=".CONTEXT_ELIS_COURSE."
                           AND EXISTS (SELECT 'x'
                                         FROM {crlm_course} ep
                                        WHERE ep.id = {context}.instanceid)
                           $emptyclause";
            $DB->execute($sql);
        }
    }
}

/**
 * ELIS Class Context
 */
class context_elis_class extends context_elis {
    /**
     * Please use context_elis_class::instance($classid) if you need the instance of this context.
     * Alternatively if you know only the context id use context::instance_by_id($contextid)
     *
     * @param stdClass $record
     */
    protected function __construct(stdClass $record) {
        parent::__construct($record);
        if ($record->contextlevel != CONTEXT_ELIS_CLASS) {
            throw new coding_exception('Invalid $record->contextlevel in context_elis_class constructor.');
        }
    }

    /**
     * Returns human readable context level name.
     *
     * @static
     * @return string the human readable context level name.
     */
    public static function get_level_name() {
        return get_string('class', 'elis_program');
    }

    /**
     * Returns human readable context identifier.
     *
     * @param boolean $withprefix whether to prefix the name of the context
     * @param boolean $short does not apply to classes
     * @return string the human readable context name.
     */
    public function get_context_name($withprefix = true, $short = false) {
        global $DB;

        $name = '';
        $class = $DB->get_record(pmclass::TABLE, array('id'=>$this->_instanceid));
        if (!empty($class)) {
            if ($withprefix) {
                $name = get_string('class', 'elis_program').': ';
            }
            $name .= format_string($class->idnumber, true, array('context' => $this));
        }
        return $name;
    }

    /**
     * Returns the most relevant URL for this context.
     *
     * @return moodle_url
     */
    public function get_url() {
        $params = array(
            's'      => 'cls',
            'action' => 'view',
            'id'     => $this->_instanceid
        );
        return new moodle_url('/elis/program/index.php', $params);
    }

    /**
     * Returns array of relevant context capability records.
     *
     * @return array
     */
    public function get_capabilities() {
        global $DB;

        $sort = 'ORDER BY contextlevel,component,name';   // To group them sensibly for display

        $params = array();
        $sql = "SELECT *
                  FROM {capabilities}
                 WHERE contextlevel IN (".CONTEXT_ELIS_CLASS.")";

        return $DB->get_records_sql($sql.' '.$sort, $params);
    }

    /**
     * Returns ELIS class context instance.
     *
     * @static
     * @param int $instanceid
     * @param int $strictness
     * @return context_elis_class context instance
     */
    public static function instance($instanceid, $strictness = MUST_EXIST) {
        global $DB;

        if ($context = context::cache_get(CONTEXT_ELIS_CLASS, $instanceid)) {
            return $context;
        }

        $record = $DB->get_record('context', array('contextlevel'=>CONTEXT_ELIS_CLASS, 'instanceid'=>$instanceid));
        if (empty($record)) {
            $class = $DB->get_record(pmclass::TABLE, array('id'=>$instanceid), 'id,idnumber,courseid', $strictness);
            if (!empty($class)) {
                $parentcontext = context_elis_course::instance($class->courseid);
                $record = context::insert_context_record(CONTEXT_ELIS_CLASS, $class->id, $parentcontext->path);
            }
        }

        if (!empty($record)) {
            $context = new context_elis_class($record);
            context::cache_add($context);
            return $context;
        }

        return false;
    }

    /**
     * Returns immediate child contexts of class
     * decendents beyond immediate children are not returned.
     *
     * @return array
     */
    public function get_child_contexts() {
        global $DB;

        $result = array();

        $sql = "SELECT ctx.*
                  FROM {context} ctx
                 WHERE ctx.path LIKE ? AND (ctx.depth = ? OR ctx.contextlevel = ?)";
        $params = array($this->_path.'/%', $this->depth+1, CONTEXT_ELIS_CLASS);
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $result[$record->id] = context_elis::create_instance_from_record($record);
        }
        unset($records);

        return $result;
    }

    /**
     * Create missing context instances at ELIS class context level
     * @static
     */
    protected static function create_level_instances() {
        global $DB;

        $sql = "INSERT INTO {context} (contextlevel, instanceid)
                SELECT ".CONTEXT_ELIS_CLASS.", ep.id
                  FROM {".pmclass::TABLE."} ep
                 WHERE NOT EXISTS (SELECT 'x'
                                     FROM {context} cx
                                    WHERE ep.id = cx.instanceid AND cx.contextlevel=".CONTEXT_ELIS_CLASS.")";
        $DB->execute($sql);
    }

    /**
     * Returns sql necessary for purging of stale context instances.
     *
     * @static
     * @return string cleanup SQL
     */
    protected static function get_cleanup_sql() {
        $sql = "
                  SELECT c.*
                    FROM {context} c
         LEFT OUTER JOIN {".pmclass::TABLE."} ep ON c.instanceid = cc.id
                   WHERE ep.id IS NULL AND c.contextlevel = ".CONTEXT_ELIS_CLASS."
               ";

        return $sql;
    }

    /**
     * Rebuild context paths and depths at ELIS class context level.
     *
     * @static
     * @param $force
     */
    protected static function build_paths($force) {
        global $DB;

        if ($force or $DB->record_exists_select('context', "contextlevel = ".CONTEXT_ELIS_CLASS." AND (depth = 0 OR path IS NULL)")) {
            $ctxemptyclause = ($force) ? '' : "AND (ctx.path IS NULL OR ctx.depth = 0)";

            $sql = "INSERT INTO {context_temp} (id, path, depth)
                    SELECT ctx.id, ".$DB->sql_concat('pctx.path', "'/'", 'ctx.id').", pctx.depth+1
                      FROM {context} ctx
                      JOIN {crlm_class} cls ON (cls.id = ctx.instanceid AND ctx.contextlevel = ".CONTEXT_ELIS_CLASS.")
                      JOIN {context} pctx ON (pctx.instanceid = cls.courseid AND pctx.contextlevel = ".CONTEXT_ELIS_COURSE.")
                     WHERE pctx.path IS NOT NULL AND pctx.depth > 0
                           $ctxemptyclause";
            $trans = $DB->start_delegated_transaction();
            $DB->delete_records('context_temp');
            $DB->execute($sql);
            context::merge_context_temp_table();
            $DB->delete_records('context_temp');
            $trans->allow_commit();
        }
    }
}

/**
 * ELIS user context
 */
class context_elis_user extends context_elis {
    /**
     * Please use context_user::instance($userid) if you need the instance of context.
     * Alternatively if you know only the context id use context::instance_by_id($contextid)
     *
     * @param stdClass $record
     */
    protected function __construct(stdClass $record) {
        parent::__construct($record);
        if ($record->contextlevel != CONTEXT_ELIS_USER) {
            throw new coding_exception('Invalid $record->contextlevel in context_elis_user constructor.');
        }
    }

    /**
     * Returns human readable context level name.
     *
     * @static
     * @return string the human readable context level name.
     */
    public static function get_level_name() {
        return get_string('user', 'elis_program');
    }

    /**
     * Returns human readable context identifier.
     *
     * @param boolean $withprefix whether to prefix the name of the context with User
     * @param boolean $short does not apply to user context
     * @return string the human readable context name.
     */
    public function get_context_name($withprefix = true, $short = false) {
        global $DB;

        $name = '';
        if ($user = $DB->get_record(user::TABLE, array('id'=>$this->_instanceid))) {
            if ($withprefix){
                $name = get_string('user', 'elis_program').': ';
            }
            $name .= fullname($user);
        }
        return $name;
    }

    /**
     * Returns the most relevant URL for this context.
     *
     * @return moodle_url
     */
    public function get_url() {
        $params = array(
            's'      => 'usr',
            'action' => 'view',
            'id'     => $this->_instanceid
        );
        return new moodle_url('/elis/program/index.php', $params);
    }

    /**
     * Returns array of relevant context capability records.
     *
     * @return array
     */
    public function get_capabilities() {
        global $DB;

        $sort = 'ORDER BY contextlevel,component,name';   // To group them sensibly for display

        $sql = "SELECT *
                  FROM {capabilities}
                 WHERE contextlevel = ".CONTEXT_ELIS_USER;

        return $records = $DB->get_records_sql($sql.' '.$sort, $params);
    }

    /**
     * Returns ELIS User context instance.
     *
     * @static
     * @param int $instanceid
     * @param int $strictness
     * @return context_elis_user context instance
     */
    public static function instance($instanceid, $strictness = MUST_EXIST) {
        global $DB;

        if ($context = context::cache_get(CONTEXT_ELIS_USER, $instanceid)) {
            return $context;
        }

        if (!$record = $DB->get_record('context', array('contextlevel'=>CONTEXT_ELIS_USER, 'instanceid'=>$instanceid))) {
            if ($user = $DB->get_record(user::TABLE, array('id'=>$instanceid), 'id', $strictness)) {
                $record = context::insert_context_record(CONTEXT_ELIS_USER, $user->id, '/'.SYSCONTEXTID, 0);
            }
        }

        if ($record) {
            $context = new context_elis_user($record);
            context::cache_add($context);
            return $context;
        }

        return false;
    }

    /**
     * Create missing context instances at ELIS user context level
     * @static
     */
    protected static function create_level_instances() {
        global $DB;

        $sql = "INSERT INTO {context} (contextlevel, instanceid)
                SELECT ".CONTEXT_ELIS_USER.", u.id
                  FROM {".user::TABLE."} u
                 WHERE NOT EXISTS (SELECT 'x'
                                     FROM {context} cx
                                    WHERE u.id = cx.instanceid AND cx.contextlevel=".CONTEXT_ELIS_USER.")";
        $DB->execute($sql);
    }

    /**
     * Returns sql necessary for purging of stale context instances.
     *
     * @static
     * @return string cleanup SQL
     */
    protected static function get_cleanup_sql() {
        $sql = "
                  SELECT c.*
                    FROM {context} c
         LEFT OUTER JOIN {".user::TABLE."} u ON c.instanceid = u.id
                   WHERE u.id IS NULL AND c.contextlevel = ".CONTEXT_ELIS_USER."
               ";

        return $sql;
    }

    /**
     * Rebuild context paths and depths at user context level.
     *
     * @static
     * @param $force
     */
    protected static function build_paths($force) {
        global $DB;

        // first update normal users
        $sql = "UPDATE {context}
                   SET depth = 2,
                       path = ".$DB->sql_concat("'/".SYSCONTEXTID."/'", 'id')."
                 WHERE contextlevel=".CONTEXT_ELIS_USER;
        $DB->execute($sql);
    }
}

/**
 * ELIS User Set context
 */
class context_elis_userset extends context_elis {
    /**
     * Please use context_coursecat::instance($usersetid) if you need the instance of context.
     * Alternatively if you know only the context id use context::instance_by_id($contextid)
     *
     * @param stdClass $record
     */
    protected function __construct(stdClass $record) {
        parent::__construct($record);
        if ($record->contextlevel != CONTEXT_ELIS_USERSET) {
            throw new coding_exception('Invalid $record->contextlevel in context_userset constructor.');
        }
    }

    /**
     * Returns human readable context level name.
     *
     * @static
     * @return string the human readable context level name.
     */
    public static function get_level_name() {
        return get_string('cluster', 'elis_program');
    }

    /**
     * Returns human readable context identifier.
     *
     * @param boolean $withprefix whether to prefix the name of the context with User Set
     * @param boolean $short does not apply to userset's
     * @return string the human readable context name.
     */
    public function get_context_name($withprefix = true, $short = false) {
        global $DB;

        $name = '';
        if ($userset = $DB->get_record(userset::TABLE, array('id'=>$this->_instanceid))) {
            if ($withprefix){
                $name = get_string('cluster', 'elis_program').': ';
            }
            $name .= format_string($userset->name, true, array('context' => $this));
        }
        return $name;
    }

    /**
     * Returns the most relevant URL for this context.
     *
     * @return moodle_url
     */
    public function get_url() {
        $params = array(
            's'      => 'clst',
            'action' => 'view',
            'id'     => $this->_instanceid
        );
        return new moodle_url('/elis/program/index.php', $params);
    }

    /**
     * Returns array of relevant context capability records.
     *
     * @return array
     */
    public function get_capabilities() {
        global $DB;

        $sort = 'ORDER BY contextlevel,component,name';   // To group them sensibly for display

        $params = array();
        $sql = "SELECT *
                  FROM {capabilities}
                 WHERE contextlevel = ".CONTEXT_ELIS_USERSET;

        return $DB->get_records_sql($sql.' '.$sort, $params);
    }

    /**
     * Returns ELIS User Set context instance.
     *
     * @static
     * @param int $instanceid
     * @param int $strictness
     * @return context_elis_userset context instance
     */
    public static function instance($instanceid, $strictness = MUST_EXIST) {
        global $DB;

        if ($context = context::cache_get(CONTEXT_ELIS_USERSET, $instanceid)) {
            return $context;
        }

        if (!$record = $DB->get_record('context', array('contextlevel'=>CONTEXT_ELIS_USERSET, 'instanceid'=>$instanceid))) {
            if ($userset = $DB->get_record(userset::TABLE, array('id'=>$instanceid), 'id,parent', $strictness)) {
                if ($userset->parent) {
                    $parentcontext = context_elis_userset::instance($userset->parent);
                    $record = context::insert_context_record(CONTEXT_ELIS_USERSET, $userset->id, $parentcontext->path);
                } else {
                    $record = context::insert_context_record(CONTEXT_ELIS_USERSET, $userset->id, '/'.SYSCONTEXTID, 0);
                }
            }
        }

        if ($record) {
            $context = new context_elis_userset($record);
            context::cache_add($context);
            return $context;
        }

        return false;
    }

    /**
     * Returns immediate child contexts of userset and sub-usersets
     *
     * @return array
     */
    public function get_child_contexts() {
        global $DB;

        $result = array();

        $sql = "SELECT ctx.*
                  FROM {context} ctx
                 WHERE ctx.path LIKE ? AND (ctx.depth = ? OR ctx.contextlevel = ?)";
        $params = array($this->_path.'/%', $this->depth+1, CONTEXT_ELIS_USERSET);
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $result[$record->id] = context_elis::create_instance_from_record($record);
        }
        unset($records);

        return $result;
    }

    /**
     * Create missing context instances at course ELIS User Set context level
     * @static
     */
    protected static function create_level_instances() {
        global $DB;

        $sql = "INSERT INTO {context} (contextlevel, instanceid)
                SELECT ".CONTEXT_ELIS_USERSET.", cc.id
                  FROM {".userset::TABLE."} eu
                 WHERE NOT EXISTS (SELECT 'x'
                                     FROM {context} cx
                                    WHERE eu.id = cx.instanceid AND cx.contextlevel=".CONTEXT_ELIS_USERSET.")";
        $DB->execute($sql);
    }

    /**
     * Returns sql necessary for purging of stale context instances.
     *
     * @static
     * @return string cleanup SQL
     */
    protected static function get_cleanup_sql() {
        $sql = "
                  SELECT c.*
                    FROM {context} c
         LEFT OUTER JOIN {".userset::TABLE."} eu ON c.instanceid = eu.id
                   WHERE eu.id IS NULL AND c.contextlevel = ".CONTEXT_ELIS_USERSET."
               ";

        return $sql;
    }

    /**
     * Rebuild context paths and depths at ELIS User Set context level.
     *
     * @static
     * @param $force
     */
    protected static function build_paths($force) {
        global $DB;

        if ($force or $DB->record_exists_select('context', "contextlevel = ".CONTEXT_ELIS_USERSET." AND (depth = 0 OR path IS NULL)")) {
            if ($force) {
                $ctxemptyclause = $emptyclause = '';
            } else {
                $ctxemptyclause = "AND (ctx.path IS NULL OR ctx.depth = 0)";
                $emptyclause    = "AND ({context}.path IS NULL OR {context}.depth = 0)";
            }

            $base = '/'.SYSCONTEXTID;

            // Normal top level user sets
            $sql = "UPDATE {context}
                       SET depth=2,
                           path=".$DB->sql_concat("'$base/'", 'id')."
                     WHERE contextlevel=".CONTEXT_ELIS_USERSET."
                           AND EXISTS (SELECT 'x'
                                         FROM {".userset::TABLE."} eu
                                        WHERE eu.id = {context}.instanceid AND eu.depth=1)
                           $emptyclause";
            $DB->execute($sql);

            // Deeper categories - one query per depthlevel
            $maxdepth = $DB->get_field_sql("SELECT MAX(depth) FROM {".userset::TABLE."}");
            for ($n=2; $n<=$maxdepth; $n++) {
                $sql = "INSERT INTO {context_temp} (id, path, depth)
                        SELECT ctx.id, ".$DB->sql_concat('pctx.path', "'/'", 'ctx.id').", pctx.depth+1
                          FROM {context} ctx
                          JOIN {".userset::TABLE."} eu ON (eu.id = ctx.instanceid AND ctx.contextlevel = ".CONTEXT_ELIS_USERSET." AND eu.depth = $n)
                          JOIN {context} pctx ON (pctx.instanceid = eu.parent AND pctx.contextlevel = ".CONTEXT_ELIS_USERSET.")
                         WHERE pctx.path IS NOT NULL AND pctx.depth > 0
                               $ctxemptyclause";
                $trans = $DB->start_delegated_transaction();
                $DB->delete_records('context_temp');
                $DB->execute($sql);
                context::merge_context_temp_table();
                $DB->delete_records('context_temp');
                $trans->allow_commit();

            }
        }
    }
}

