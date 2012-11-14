<?php
/**
 *
 *
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
 * @package
 * @subpackage
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../../elis/core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(dirname(__FILE__).'/../../moodleblock.class.php');
require_once(dirname(__FILE__).'/../block_curr_admin.php');
require_once(elispm::lib('setup.php'));
require_once(elispm::lib('menuitem.class.php'));
require_once(elispm::file('pmclasspage.class.php'));
require_once($CFG->dirroot.'/lib/phpunittestlib/testlib.php');


global $testcrs, $testrole, $testuser, $saveCFG, $saveUSER;


class test_block_curr_admin extends elis_database_test {
    protected $backupGlobals = false;

    protected static function get_overlay_tables() {
        return array(
            'cache_flags'                      => 'moodle',
            'course'                           => 'moodle',
            'config'                           => 'moodle',
            'config_plugins'                   => 'moodle',
            'context'                          => 'moodle',
            'context_temp'                     => 'moodle',
            'role'                             => 'moodle',
            'role_names'                       => 'moodle',
            'role_sortorder'                   => 'moodle',
            'role_allow_assign'                => 'moodle',
            'role_allow_override'              => 'moodle',
            'role_allow_switch'                => 'moodle',
            'role_assignments'                 => 'moodle',
            'role_capabilities'                => 'moodle',
            'role_context_levels'              => 'moodle',
            'user'                             => 'moodle',
            field::TABLE                       => 'elis_core',
            field_category::TABLE              => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            field_contextlevel::TABLE          => 'elis_core',
            field_data_char::TABLE             => 'elis_core',
            field_data_int::TABLE              => 'elis_core',
            field_data_num::TABLE              => 'elis_core',
            field_data_text::TABLE             => 'elis_core',
            field_owner::TABLE                 => 'elis_core',
            course::TABLE                      => 'elis_program',
            coursetemplate::TABLE              => 'elis_program',
            curriculumcourse::TABLE            => 'elis_program',
            classmoodlecourse::TABLE           => 'elis_program',
            pmclass::TABLE                     => 'elis_program',
            instructor::TABLE                  => 'elis_program',
            student::TABLE                     => 'elis_program',
            student_grade::TABLE               => 'elis_program',
            trackassignment::TABLE             => 'elis_program',
            waitlist::TABLE                    => 'elis_program'
        );
    }

    public static function setUpBeforeClass() {
        global $USER, $CFG, $saveCFG, $saveUSER, $testcrs, $testrole, $testuser;

        $saveCFG = $CFG; // save CFG
        $saveUSER = $USER;
        parent::setUpBeforeClass();
        $testcrs = null;
        $testrole = null;
        $testuser = null;
    }

    protected function setUp() {
        parent::setUp();

        $DB = self::$origdb; // setUpContextsTable needs $DB to be the real database for get_admin()
        $this->setUpContextsTable();
        $DB = self::$overlaydb;

        pmclasspage::$contexts = array();
    }

    /**
     * Set up the contexts table with the minimum that we need.
     */
    private function setUpContextsTable() {
        global $CFG;

        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_SYSTEM));
        self::$overlaydb->import_record('context', $syscontext);

        $site = self::$origdb->get_record('course', array('id' => SITEID));
        self::$overlaydb->import_record('course', $site);
        $sitecontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_COURSE,
                                                                  'instanceid' => SITEID));
        self::$overlaydb->import_record('context', $sitecontext);

        // Guest user
        if ($guest = self::$origdb->get_record('user', array('username' => 'guest', 'mnethostid' => $CFG->mnet_localhost_id))) {
            self::$overlaydb->import_record('user', $guest);
            $CFG->siteguest = $guest->id;
        }

        // Primary admin user
        $admin = get_admin();
        if ($admin) {
            self::$overlaydb->import_record('user', $admin);
            $CFG->siteadmins = $admin->id;
            $usercontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_USER,
                                                     'instanceid' => $admin->id));
            self::$overlaydb->import_record('context', $usercontext);

            // copy admin user's ELIS user (if available)
            $elisuser = user::find(new field_filter('idnumber', $admin->idnumber), array(), 0, 0, self::$origdb);
            if ($elisuser->valid()) {
                $elisuser = $elisuser->current();
                self::$overlaydb->import_record(user::TABLE, $elisuser->to_object());
            }
        }
    }

    public static function tearDownAfterClass() {
        // called only once after all test functions, it's always the last function called.
        global $testcrs, $testrole, $testuser, $CFG, $saveCFG, $saveUSER, $USER;

        // Calling these here causes errors to happen... =/
//         if ($testcrs) {
//             $testcrs->delete();
//         }

//         if ($testuser) {
//             delete_test_user($testuser->username);
//         }

//         if ($testrole && !delete_role($testrole->id)) {
//             error_log('testblock_curradmin.php::tearDownAfterClass() - failed deleting test role!');
//         }

        parent::tearDownAfterClass();
        $USER = $saveUSER;
        $CFG = $saveCFG;
    }

    public function testBlockInitialization() {
        $block = new block_curr_admin();
        $this->assertNotEmpty($block->title);

        $USER = get_admin();

        $this->assertNotEmpty($block->get_content());
    }

    public function test_block_curr_admin_load_menu_children_course() {
        global $testcrs, $testrole, $testuser, $USER;

        // create test user - ensure the returned user is NOT a site admin. if they are, our capability restrictions won't work
        $i=0;
        while(true) {
            $testuser = get_test_user('testELIS4093_'.$i);
            if (!is_siteadmin($testuser)) {
                break;
            }
            $i++;
        }

        // create role with cap: 'elis/program:class_view'
        $testrole = new stdClass;
        $testrole->name = 'ELIS Class View';
        $testrole->shortname = '_test_ELIS_4093';
        $testrole->description = 'ELIS Class View';
        $testrole->archetype = '';
        $testrole->id = create_role($testrole->name, $testrole->shortname, $testrole->description, $testrole->archetype);

        // Ensure our new role is assignable to ELIS class contexts
        set_role_contextlevels($testrole->id, array(CONTEXT_ELIS_CLASS));

        // Ensure the role has our required capability assigned
        $sitecontext = context_system::instance();
        assign_capability('elis/program:class_view', CAP_ALLOW, $testrole->id, $sitecontext->id, true);
        $sitecontext->mark_dirty();

        // create ELIS Course Description
        $testcrs = new course(array(
            'name'     => 'CD-ELIS-4093',
            'idnumber' => 'CDELIS4093',
            'syllabus' => ''
        ));
        $testcrs->save();
        $testcrs->load();

        // create three(3) Class Instances for Course Descrption
        $testcls1 = new pmclass(array(
            'courseid' => $testcrs->id,
            'idnumber' => 'CI_ELIS_4093.1'
        ));
        $testcls1->save();
        $testcls1->load();

        $testcls2 = new pmclass(array(
            'courseid' => $testcrs->id,
            'idnumber' => 'CI_ELIS_4093.2'
        ));
        $testcls2->save();
        $testcls2->load();

        $testcls3 = new pmclass(array(
            'courseid' => $testcrs->id,
            'idnumber' => 'CI_ELIS_4093.3'
        ));
        $testcls3->save();
        $testcls3->load();

        // Assign testuser new role in one Class Instance
        $context = context_elis_class::instance($testcls2->id);
        role_assign($testrole->id, $testuser->id, $context->id);

        // switch to testuser
        $USER = $testuser;
        $items = block_curr_admin_load_menu_children_course($testcrs->id, 0, 0, 5, '');

        $this->assertEquals(1,count($items));
        $this->assertTrue($items[0]->name == 'pmclass_2');
    }
}
