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
 * @package    elis
 * @subpackage program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Data objects.
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::file('coursecatalogpage.class.php'));

require_once($CFG->dirroot.'/lib/phpunittestlib/testlib.php');
require_once(elis::lib('testlib.php'));

/**
 * Test features of the course catalog page.
 */
class test_course_catalog_page extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Get list of overlay tables
     * @return array Array of overlay tables.
     */
    protected static function get_overlay_tables() {
        return array(
            course::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
        );
    }

    /**
     * Load CSV data for courses, classes, students, and users.
     */
    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcrs.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/student.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/user.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test addclasstable->get_item_display_classsize()
     */
    public function test_get_item_display_classsize() {
        $this->load_csv_data();

        $class = new pmclass(100);
        $class->load();
        $class->maxstudents = 10;

        $items = array();
        $addclasstable = new addclasstable($items);
        $classsize = $addclasstable->get_item_display_classsize('', $class);
        $expected = '1/10';

        $this->assertEquals($expected, $classsize);
    }

    /**
     * Test addclasstable->get_item_display_options()
     */
    public function test_get_item_display_options() {
        $this->load_csv_data();
        $class = new pmclass(100);
        $class->load();

        $items = array();
        $addclasstable = new addclasstable($items);
        $option = $addclasstable->get_item_display_options('', $class);
        $expected = '<a href="index.php?s=crscat&amp;section=curr&amp;clsid=100&amp;action=savenew">Choose</a>';

        $this->assertEquals($expected, $option);
    }
}