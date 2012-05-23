<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage block_curr_admin:phpunit test
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('PHPUNIT_SCRIPT') || define('PHPUNIT_SCRIPT', true);

require_once 'PHPUnit/Framework.php';

// Include config.php with global $CFG
require_once(dirname(__FILE__) .'/../../../config.php');

global $CFG;

// Test specific includes:
require_once($CFG->dirroot .'/elis/program/lib/setup.php');
require_once($CFG->dirroot .'/elis/core/lib/testlib.php');
require_once($CFG->dirroot .'/elis/program/lib/menuitem.class.php');
require_once($CFG->dirroot .'/elis/program/pmclasspage.class.php');
require_once($CFG->dirroot .'/blocks/curr_admin/lib.php');
require_once($CFG->dirroot .'/lib/phpunittestlib/testlib.php');

global $testcrs, $testrole, $testuser, $saveCFG, $saveUSER;

class block_curr_admin_test extends elis_database_test {

    protected $backupGlobals = FALSE;

    protected static function get_overlay_tables() {
        return array(course::TABLE            => 'elis_program',
                     coursetemplate::TABLE    => 'elis_program',
                     curriculumcourse::TABLE  => 'elis_program',
                     classmoodlecourse::TABLE => 'elis_program',
                     pmclass::TABLE           => 'elis_program',
                     instructor::TABLE        => 'elis_program',
                     student::TABLE           => 'elis_program',
                     student_grade::TABLE     => 'elis_program',
                     trackassignment::TABLE   => 'elis_program',
                     waitlist::TABLE          => 'elis_program',

                     'course'              => 'moodle',
                     'config'              => 'moodle',
                     'config_plugins'      => 'moodle',
                     'context'             => 'moodle',
                     'role'                => 'moodle',
                     'role_names'          => 'moodle',
                     'role_sortorder'      => 'moodle',
                     'role_allow_assign'   => 'moodle',
                     'role_allow_override' => 'moodle',
                     'role_allow_switch'   => 'moodle',
                     'role_assignments'    => 'moodle',
                     'role_capabilities'   => 'moodle',
                     'role_context_levels' => 'moodle',
                     'user'                => 'moodle',

                     'elis_field'                   => 'elis_core',
                     'elis_field_categories'        => 'elis_core',
                     'elis_field_category_contexts' => 'elis_core',
                     'elis_field_contextlevels'     => 'elis_core',
                     'elis_field_data_char'         => 'elis_core',
                     'elis_field_data_int'          => 'elis_core',
                     'elis_field_data_num'          => 'elis_core',
                     'elis_field_data_text'         => 'elis_core',
                     'elis_field_owner'             => 'elis_core'
               );
    }

    protected static function get_ignored_tables() {
        return array('cache_flags' => 'moodle'
               );
    }

    public static function setUpBeforeClass() {
        // called only once before all other methods
        global $USER, $CFG, $saveCFG, $saveUSER, $testcrs, $testrole, $testuser;

        //echo "setUpBeforeClass() - begin\n";
        $saveCFG = $CFG; // save CFG
        $saveUSER = $USER;
        parent::setUpBeforeClass();
        $testcrs = null; 
        $testrole = null;
        $testuser = null;
        //echo "setUpBeforeClass() - end\n";
    }

    protected function setUp() {
        // called before each test function
        parent::setUp();
        pmclasspage::$contexts = array();
    }

    protected function assertPreConditions() {
        global $DB, $USER, $testuser;
        // called after setUp() before each test function
        //echo "Test pre-conditions: begin\n";

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

        // Create guest user
        $guestuser = get_test_user('guest');
        set_config('siteguest', $guestuser->id);

        // Create admin user
        $admiuser = get_test_user('admin');
        set_config('siteadmins', $adminuser->id);
        //echo "Test pre-conditions: end\n";
    }

    public function test_block_curr_admin_load_menu_children_course() { 
        global $testcrs, $testrole, $testuser, $USER;
        // create test user
        $testuser = get_test_user('testELIS4093');

        // create role with cap: 'elis/program:class_view'
        $testrole = new stdClass;
        $testrole->name = 'ELIS Class View';
        $testrole->shortname = '_test_ELIS_4093';
        $testrole->description = 'ELIS Class View';
        $testrole->archetype = '';
        $testrole->id = create_role($testrole->name, $testrole->shortname, $testrole->description, $testrole->archetype);
        $cntxtlvl = context_level_base::get_custom_context_level('class', 'elis_program');
        set_role_contextlevels($testrole->id, array($cntxtlvl));
        $sitecontext = get_context_instance(CONTEXT_SYSTEM);
        assign_capability('elis/program:class_view', CAP_ALLOW, $testrole->id,
                          $sitecontext->id, true);

        // create ELIS Course Description
        $testcrs = new course(array('name'     => 'CD-ELIS-4093',
                                    'idnumber' => 'CDELIS4093',
                                    'syllabus' => ''
                                    ));
        $testcrs->save();

        // create three(3) Class Instances for Course Descrption
        $testcls1 = new pmclass(array('courseid' => $testcrs->id,
                                      'idnumber' => 'CI_ELIS_4093.1'));
        $testcls1->save();
        $testcls2 = new pmclass(array('courseid' => $testcrs->id,
                                      'idnumber' => 'CI_ELIS_4093.2'));
        $testcls2->save();
        $testcls3 = new pmclass(array('courseid' => $testcrs->id,
                                      'idnumber' => 'CI_ELIS_4093.3'));
        $testcls3->save();

        // Assign testuser new role in one Class Instance
        $context = get_context_instance($cntxtlvl, $testcls2->id);
        role_assign($testrole->id, $testuser->id, $context->id);

        // switch to testuser
        $USER = $testuser;
        mark_context_dirty($context->path);
        $items = block_curr_admin_load_menu_children_course($testcrs->id, 0, 0, 5, '');
      /*
        ob_start();
        var_dump($items);
        $tmp = ob_get_contents();
        ob_end_clean();
        error_log("testblock_curradmin.php: items => {$tmp}");
      */
        $this->assertTrue(count($items) == 1);
        $this->assertTrue($items[0]->name == 'pmclass_2');
    }

    protected function assertPostConditions() {
        // called after each test function
    }

    protected function tearDown() {
        // called after assertPostConditions() for each test function
        parent::tearDown();
    }

    public static function tearDownAfterClass() {
        // called only once after all test functions, it's always the last function called.
        global $testcrs, $testrole, $testuser, $CFG, $saveCFG, $saveUSER, $USER;
        //echo "tearDownAfterClass() - begin\n";

        if ($testcrs) {
            $testcrs->delete();
        }

        if ($testuser) {
            delete_test_user($testuser->username);
        }

        if ($testrole && !delete_role($testrole->id)) {
            error_log('testblock_curradmin.php::tearDownAfterClass() - failed deleting test role!');
        }

        parent::tearDownAfterClass();
        $USER = $saveUSER;
        $CFG = $saveCFG;
        //echo "tearDownAfterClass() - end\n";
    }
}

