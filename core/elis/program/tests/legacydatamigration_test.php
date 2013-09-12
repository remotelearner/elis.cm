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

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Data objects.
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elis::lib('data/customfield.class.php'));

// Libs.
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));
require_once($CFG->dirroot.'/admin/roles/lib.php');

/**
 * Test migrating legacy data.
 * @group elis_program
 */
class legacydatamigration_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            field::TABLE => elis::component_file('program', 'tests/fixtures/user_field.csv'),
            field_owner::TABLE => elis::component_file('program', 'tests/fixtures/user_field_owner.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            curriculum::TABLE => elis::component_file('program', 'tests/fixtures/curriculum.csv'),
            'crlm_environment' => elis::component_file('program', 'tests/fixtures/environment.csv'),
            'crlm_tag' => elis::component_file('program', 'tests/fixtures/tag.csv'),
            'crlm_tag_instance' => elis::component_file('program', 'tests/fixtures/tag_instance.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test that the pm_migrate_tags() function works correctly
     */
    public function test_pmmigratetags() {
        global $DB;

        $this->load_csv_data();

        // ELIS-7599: create bogus tag instance data (ala 1.9).
        $tag = (object)array(
           'name' => 'bogus_tag',
           'description' => 'Bogus Tag Description',
           'timecreated' => 1327958800,
           'timemodified' => 1327958800
        );
        $tagid = $DB->insert_record('crlm_tag', $tag);

        $taginstance = (object)array(
           'instancetype' => 'cur',
           'instanceid' => 999999,
           'tagid' => $tagid,
           'data' => '',
           'timecreated' => 1327958800,
           'timemodified' => 132795880
        );
        $DB->insert_record('crlm_tag_instance', $taginstance);

        // Migrate the legacy tag data to new ELIS fields.
        pm_migrate_tags();

        $this->assertTrue(!$DB->get_records('crlm_tag_instance', array('tagid' => $tagid)));

        // Initialize the program object.
        $program = new curriculum(1);
        $program->reset_custom_field_list();
        $program->load();
        $program = $program->to_object();

        // Get the field data from the object.
        $this->assertObjectHasAttribute('field__19upgrade_curriculum_tags', $program);
        $this->assertEquals(1, count($program->field__19upgrade_curriculum_tags));
        $this->assertEquals('Testing data', $program->field__19upgrade_curriculum_tag_data_Test_tag);

        // Let's do some extra DB-level validation (though it's probably not necessary).
        $field = $DB->get_record(field::TABLE, array('shortname' => '_19upgrade_curriculum_tags'));
        $this->assertGreaterThan(0, $field->id);

        $context = context_elis_program::instance($program->id);
        $this->assertTrue($DB->record_exists(field_data_char::TABLE, array('contextid' => $context->id, 'fieldid' => $field->id)));
    }

    /**
     * Test that the pm_migrate_environments() function works correctly
     */
    public function test_pmmigrateenvironments() {
        global $DB;

        $this->load_csv_data();

        // Migrate the legacy environment data to new ELIS filelds.
        pm_migrate_environments();

        // Initialize the course object.
        $course = new course(100);
        $course->reset_custom_field_list();
        $course->load();
        $course = $course->to_object();

        // Get the field data from the object.
        $this->assertObjectHasAttribute('field__19upgrade_course_environment', $course);
        $this->assertEquals('Testing', $course->field__19upgrade_course_environment);

        // Let's do some extra DB-level validation (though it's probably not necessary).
        $field = $DB->get_record(field::TABLE, array('shortname' => '_19upgrade_course_environment'));
        $this->assertGreaterThan(0, $field->id);

        $context = context_elis_course::instance($course->id);
        $this->assertTrue($DB->record_exists(field_data_char::TABLE, array('contextid' => $context->id, 'fieldid' => $field->id)));
    }
}