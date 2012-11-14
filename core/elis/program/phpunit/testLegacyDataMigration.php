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
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));
// require_once(elispm::lib('data/usermoodle.class.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once($CFG->dirroot.'/admin/roles/lib.php');

class curriculumCustomFieldsTest extends elis_database_test {
//     protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
		return array(
            'cache_flags' => 'moodle',
            'context' => 'moodle',
            'course' => 'moodle',
            'events_queue' => 'moodle',
            'events_queue_handlers' => 'moodle',
            'user' => 'moodle',
            'user_info_category' => 'moodle',
            'user_info_field' => 'moodle',
            'user_info_data' => 'moodle',
            field::TABLE => 'elis_core',
            field_category::TABLE => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_data_char::TABLE => 'elis_core',
            field_owner::TABLE => 'elis_core',
		    course::TABLE => 'elis_program',
		    pmclass::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
		    'crlm_environment' => 'elis_program',
		    'crlm_tag' => 'elis_program',
		    'crlm_tag_instance' => 'elis_program'
        );
    }

    protected function setUp() {
        parent::setUp();
        $this->setUpContextsTable();
        $this->load_csv_data();
    }

    /**
     * Set up the contexts table with the minimum that we need.
     */
    private function setUpContextsTable() {
        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_SYSTEM));
        self::$overlaydb->import_record('context', $syscontext);

        $site = self::$origdb->get_record('course', array('id' => SITEID));
        self::$overlaydb->import_record('course', $site);


        $sitecontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_COURSE,
                                                                  'instanceid' => SITEID));
        self::$overlaydb->import_record('context', $sitecontext);
    }

    protected function load_csv_data() {
        // Load initial data from a CSV file
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(field_category::TABLE, elis::component_file('program', 'phpunit/user_field_category.csv'));
        $dataset->addTable(field::TABLE, elis::component_file('program', 'phpunit/user_field.csv'));
        $dataset->addTable(field_owner::TABLE, elis::component_file('program', 'phpunit/user_field_owner.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        // Load ELIS PM data
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        $dataset->addTable(curriculum::TABLE, elis::component_file('program', 'phpunit/curriculum.csv'));
        $dataset->addTable('crlm_environment', elis::component_file('program', 'phpunit/environment.csv'));
        $dataset->addTable('crlm_tag', elis::component_file('program', 'phpunit/tag.csv'));
        $dataset->addTable('crlm_tag_instance', elis::component_file('program', 'phpunit/tag_instance.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test that the pm_migrate_tags() function works correctly
     */
    public function testPmMigrateTags() {
        global $DB;

        $db = $DB; // self::$overlaydb;
        // ELIS-7599: create bogus tag instance data (ala 1.9)
        $tagid = $db->insert_record('crlm_tag', (object)array(
                                           'name'         => 'bogus_tag',
                                           'description'  => 'Bogus Tag Description',
                                           'timecreated'  => 1327958800,
                                           'timemodified' => 1327958800));
        $db->insert_record('crlm_tag_instance', (object)array(
                                           'instancetype' => 'cur',
                                           'instanceid'   => 999999,
                                           'tagid'        => $tagid,
                                           'data'         => '',
                                           'timecreated'  => 1327958800,
                                           'timemodified' => 1327958800));

        // Migrate the legacy tag data to new ELIS fields
        pm_migrate_tags();

        $this->assertTrue(!$db->get_records('crlm_tag_instance', array('tagid' => $tagid)));

        // Initialize the program object
        $program = new curriculum(1);
        $program->load();
        $program = $program->to_object();

        // Get the field data from the object
        $this->assertObjectHasAttribute('field__19upgrade_curriculum_tags', $program);
        $this->assertEquals(1, count($program->field__19upgrade_curriculum_tags));
        $this->assertEquals('Testing data', $program->field__19upgrade_curriculum_tag_data_Test_tag);

        // Let's do some extra DB-level validation (though it's probably not necessary)
        $field = $DB->get_record(field::TABLE, array('shortname' => '_19upgrade_curriculum_tags'));
        $this->assertGreaterThan(0, $field->id);

        $context = context_elis_program::instance($program->id);
        $this->assertTrue($DB->record_exists(field_data_char::TABLE, array('contextid' => $context->id, 'fieldid' => $field->id)));
    }

    /**
     * Test that the pm_migrate_environments() function works correctly
     */
    public function testPmMigrateEnvironments() {
        global $DB;

        // Migrate the legacy environment data to new ELIS filelds
        pm_migrate_environments();

        // Initialize the course object
        $course = new course(100);
        $course->load();
        $course = $course->to_object();

        // Get the field data from the object
        $this->assertObjectHasAttribute('field__19upgrade_course_environment', $course);
        $this->assertEquals('Testing', $course->field__19upgrade_course_environment);

        // Let's do some extra DB-level validation (though it's probably not necessary)
        $field = $DB->get_record(field::TABLE, array('shortname' => '_19upgrade_course_environment'));
        $this->assertGreaterThan(0, $field->id);

        $context = context_elis_course::instance($course->id);
        $this->assertTrue($DB->record_exists(field_data_char::TABLE, array('contextid' => $context->id, 'fieldid' => $field->id)));
    }
}
