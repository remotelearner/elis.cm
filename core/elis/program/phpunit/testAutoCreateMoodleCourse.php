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
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/classmoodlecourse.class.php'));


/**
 * Overlay database that allows for the handling of temporary tables as well
 * as some course-specific optimizations
 */
class overlay_course_database extends overlay_database {

    /**
     * Do NOT use in code, to be used by database_manager only!
     * @param string $sql query
     * @return bool true
     * @throws dml_exception if error
     */
    public function change_database_structure($sql) {
        if (strpos($sql, 'CREATE TEMPORARY TABLE ') === 0) {
            //creating a temporary table, so make it an overlay table

            //find the table name
            $start_pos = strlen('CREATE TEMPORARY TABLE ');
            $length = strpos($sql, '(') - $start_pos;
            $tablename = trim(substr($sql, $start_pos, $length));
            //don't use prefix when storing
            $tablename = substr($tablename, strlen($this->overlayprefix));

            //set it up as an overlay table
            $this->overlaytables[$tablename] = 'moodle';
            $this->pattern = '/{('.implode('|', array_keys($this->overlaytables)).')}/';
        }

        // FIXME: or should we just do nothing?
        return $this->basedb->change_database_structure($sql);
    }

    /**
     * Returns detailed information about columns in table. This information is cached internally.
     * @param string $table name
     * @param bool $usecache
     * @return array of database_column_info objects indexed with column names
     */
    public function get_columns($table, $usecache=true) {
        //determine if this is an overlay table
        $is_overlay_table = array_key_exists($table, $this->overlaytables);

        if ($is_overlay_table) {
            //temporarily set the prefix to the overlay prefix
            $cacheprefix = $this->basedb->prefix;
            $this->basedb->prefix = $this->overlayprefix; // HACK!!!
        }

        $result = $this->basedb->get_columns($table, $usecache);

        if ($is_overlay_table) {
            //restore proper prefix
            $this->basedb->prefix = $cacheprefix;
        }

        return $result;
    }

    /**
     * Clean up the temporary tables.  You'd think that if this method was
     * called dispose, then the cleanup would happen automatically, but it
     * doesn't.
     */
    public function cleanup() {
        $manager = $this->get_manager();
        foreach ($this->overlaytables as $tablename => $component) {
            $xmldb_file = $this->xmldbfiles[$component];
            $structure = $xmldb_file->getStructure();
            $table = $structure->getTable($tablename);
            // FIXME: when http://bugs.mysql.com/bug.php?id=10327 gets fixed,
            // we can switch this back to drop_temp_table
            if ($table === null) {
                //most likely a temporary table
                try {
                    //attempt to drop the temporary table
                    $table = new xmldb_table($tablename);
                    $manager->drop_temp_table($table);
                } catch (Exception $e) {
                    //temporary table was already dropped
                }
            } else {
                //structure was defined in xml, so drop normal table
                $manager->drop_table($table);
            }
        }
    }

    /**
     * Empty out all the overlay tables.
     */
    public function reset_overlay_tables() {
        //do nothing
    }
}


class autocreatemoodlecourseTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

	protected static function get_overlay_tables() {
	    return array(
            'backup_controllers' => 'moodle',
            'course' => 'moodle',
            'config' => 'moodle',
            'config_plugins' => 'moodle',
            'context' => 'moodle',
            'course_categories' => 'moodle',
            'course_sections' => 'moodle',
            'cache_flags' => 'moodle',
            'elis_field_categories' => 'elis_core',
            'elis_field_category_contexts' => 'elis_core',
            'elis_field' => 'elis_core',
            'elis_field_contextlevels' => 'elis_core',
            'elis_field_owner' => 'elis_core',
            'elis_field_data_text' => 'elis_core',
            'enrol' => 'moodle',
            'groups' => 'moodle',
            'user' => 'moodle',
            classmoodlecourse::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            course::TABLE => 'elis_program',
            coursetemplate::TABLE => 'elis_program'
        );
	}

    /**
     * Set up the course and context records needed for many of the
     * unit tests
     */
    private function init_contexts_and_site_course() {
        global $DB, $USER;

        $prefix = self::$origdb->get_prefix();
        $DB->execute("INSERT INTO {context}
                      SELECT * FROM
                      {$prefix}context
                      WHERE contextlevel = ?", array(CONTEXT_SYSTEM));
        $DB->execute("INSERT INTO {context}
                      SELECT * FROM
                      {$prefix}context
                      WHERE contextlevel = ? and instanceid = ?", array(CONTEXT_COURSE, SITEID));
        //set up the site course record
        if ($record = self::$origdb->get_record('course', array('id' => SITEID))) {
            unset($record->id);
            $DB->insert_record('course', $record);
        }

        build_context_path();
    }

    /**
     * Helper function that creates a parent and a child category
     *
     * @param boolean $second_child create a second child category if true
     */
    private static function set_up_category_structure($second_child = false) {
        global $DB;

        //basic parent and child categories
        $parent_category = new stdClass;
        $parent_category->name = 'parentcategory';
        $parent_category->id = $DB->insert_record('course_categories', $parent_category);
        get_context_instance(CONTEXT_COURSECAT, $parent_category->id);

        build_context_path(true);
    }

    /**
     * Helper function that creates an admin user and initializes the user
     * global
     */
    private static function create_admin_user() {
        global $USER, $DB, $CFG;

        //set up the guest user to prevent enrolment plugins from thinking the
        //created user is the guest user
        if ($admin = get_admin()) {
            self::$overlaydb->import_record('user', $admin);
        }

        //register as site admin
        set_config('siteadmins', $admin->id);

        //set up user global
        $USER = self::$overlaydb->get_record('user', array('id' => $admin->id));
    }

    /**
     * Creates a default guest user record in the database
     */
    private static function create_guest_user() {
        global $CFG, $DB;

        //set up the guest user to prevent enrolment plugins from thinking the
        //created user is the guest user
        if ($record = self::$origdb->get_record('user', array('username' => 'guest',
                                                'mnethostid' => $CFG->mnet_localhost_id))) {
            self::$overlaydb->import_record('user', $record);
        }
    }

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass() {
        // called before each test function
        global $DB;
        self::$origdb = $DB;

        //use our custom overlay database type that supports temporary tables
        self::$overlaydb = new overlay_course_database($DB, static::get_overlay_tables(), static::get_ignored_tables());
        self::create_admin_user();

        $DB = self::$overlaydb;

        //create data we need for many test cases
        self::create_guest_user();
        self::init_contexts_and_site_course();
        self::set_up_category_structure(true);

        set_config('defaultenrol', 1, 'enrol_guest');
        set_config('status', ENROL_INSTANCE_DISABLED, 'enrol_guest');
        set_config('enrol_plugins_enabled', 'manual,guest');
        self::load_csv_data();
    }

    protected function load_csv_data() {

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('course', elis::component_file('program', 'phpunit/autocreatemoodlecourse_course.csv'));
        //second param is false so we don't lose the site record
        load_phpunit_data_set($dataset, false, self::$overlaydb);

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/autocreatemoodlecourse_class.csv'));
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/autocreatemoodlecourse_coursedescription.csv'));
        $dataset->addTable(coursetemplate::TABLE, elis::component_file('program', 'phpunit/autocreatemoodlecourse_coursetemplate.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test validation that class duplicate with autocreate creates and links to a moodle course
     *
     */
    public function testAutoCreateMoodleCourseCreatesAndLinksMoodleCourse() {
        global $DB;

        $class = new pmclass(1);

        $classmoodle = new classmoodlecourse(array('moodlecourseid' => 2, 'classid' => 1));
        $classmoodle->save();

        $userset = new stdClass();
        $userset->name = 'test';
        $options = array();
        $options['targetcluster'] = $userset;
        $options['classes'] = 1;
        $options['moodlecourses'] = 'copyalways';
        $options['classmap'] = array();

        $return = $class->duplicate($options);
        //make sure that a we get a class returned
        $this->assertTrue(is_array($return['classes']));

        //get the new returned id
        $id = $return['classes'][1];

        $record_exists = $DB->record_exists('crlm_class_moodle', array('classid'=>$id));

        //we want to validate that a link to the new moodle course was created
        $this->assertTrue($record_exists);

        //get the new course id
        $record = $DB->get_record('crlm_class_moodle', array('classid'=>$id));
        $course_exists = $DB->record_exists('course',array('id'=>$record->moodlecourseid));

        //we want to validate that new moodle course was created
        $this->assertTrue($record_exists);

        //cleanup class_moodle records
        $DB->delete_records('crlm_class_moodle');
    }

    /**
     * Test validation that moodle_attach_class will attach a Moodle course if autocreate is true
     *
     */
    public function testAutoCreateMoodleCourseAttachesMoodleCourse() {
        global $DB;

        $clsid = 1;
        $mdlid = 2;
        $autocreate = true;

        $result = moodle_attach_class($clsid, $mdlid, '', false, false, $autocreate);

        //make sure that moodle_attach_class returns true
        $this->assertTrue($result);

        $record_exists = $DB->record_exists('crlm_class_moodle', array('classid'=>$clsid));

        //we want to validate that a link to the new moodle course was created
        $this->assertTrue($record_exists);

        //get the new course id
        $record = $DB->get_record('crlm_class_moodle', array('classid'=>$clsid));
        $course_exists = $DB->record_exists('course',array('id'=>$record->moodlecourseid));

        //we want to validate that new moodle course was created
        $this->assertTrue($record_exists);
    }
}