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
require_once(elis::lib('data/customfield.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::file('enrol/userset/moodle_profile/userset_profile.class.php'));

class curriculumCustomFieldsTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'user' => 'moodle',
            'context' => 'moodle',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
            field_category::TABLE => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            field::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_data_char::TABLE => 'elis_core',
            field_data_int::TABLE => 'elis_core',
            clusterassignment::TABLE => 'elis_program',
            curriculumstudent::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            userset_profile::TABLE  => 'elis_program',
            usertrack::TABLE => 'elis_program',
        );
    }

    protected function setUp() {
        parent::setUp();
        $this->setUpUsers();
        $this->setUpUsersets();
    }

    protected function setUpUsers() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/usermoodle.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    protected function setUpUsersets() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(field::TABLE, elis::component_file('program', 'phpunit/userset_custom_fields.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(field_contextlevel::TABLE, elis::component_file('program', 'phpunit/userset_custom_fields_contextlevels.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    public function testCustomUsersetThemeCTX() {
        //elis user with the associated moodle user
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        //userset with a custom theme
        $userset = new userset(1);
        $userset->field__elis_userset_theme = 'formal_white';
        $userset->field__elis_userset_themepriority = 1;
        $userset->save();

        //assign the user to the user set
        $userset_assign = new clusterassignment;
        $userset_assign->userid = $user->id;
        $userset_assign->clusterid = $userset->id;
        $userset_assign->save();

        //pretend to be that user
        $USER_bkup = $GLOBALS['USER'];
        $GLOBALS['USER'] = new stdClass;
        $GLOBALS['USER']->id = $muser->id;
        $GLOBALS['USER']->mnethostid = 1;

        //initialize page
        $page = new moodle_page;
        $page->initialise_theme_and_output();

        //assert we have our theme
        $this->assertEquals('formal_white', $page->theme->name);

        //revert to our real identity
        $GLOBALS['USER'] = $USER_bkup;
    }
}