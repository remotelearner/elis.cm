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
 * @package    block_curr_admin
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../../elis/core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Libs.
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(dirname(__FILE__).'/../../moodleblock.class.php');
require_once(dirname(__FILE__).'/../block_curr_admin.php');
require_once(elispm::lib('menuitem.class.php'));
require_once(elispm::file('pmclasspage.class.php'));
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * Test block functions.
 * @group block_curr_admin
 */
class block_curr_admin_testcase extends elis_database_test {

    /**
     * Test block_curr_admin_load_menu_children_course function.
     */
    public function test_block_curr_admin_load_menu_children_course() {
        global $DB, $USER;
        accesslib_clear_all_caches(true);

        // Create test user - ensure the returned user is NOT a site admin. if they are, our capability restrictions won't work.
        $testuser = new user;
        $testuser->username = 'testELIS4093';
        $testuser->idnumber = 'testELIS4093';
        $testuser->firstname = 'testELIS4093';
        $testuser->lastname = 'testELIS4093';
        $testuser->email = 'testELIS4093@example.com';
        $testuser->country = 'CA';
        $testuser->save();
        $testmuser = $testuser->get_moodleuser();

        // Create role with cap: 'elis/program:class_view'.
        $testrole = new stdClass;
        $testrole->name = 'ELIS Class View';
        $testrole->shortname = '_test_ELIS_4093';
        $testrole->description = 'ELIS Class View';
        $testrole->archetype = '';
        $testrole->id = create_role($testrole->name, $testrole->shortname, $testrole->description, $testrole->archetype);

        // Ensure our new role is assignable to ELIS class contexts.
        set_role_contextlevels($testrole->id, array(CONTEXT_ELIS_CLASS));

        // Ensure the role has our required capability assigned.
        $sitecontext = context_system::instance();
        assign_capability('elis/program:class_view', CAP_ALLOW, $testrole->id, $sitecontext->id, true);
        $sitecontext->mark_dirty();

        // Create ELIS Course Description.
        $testcrs = new course(array(
            'name'     => 'CD-ELIS-4093',
            'idnumber' => 'CDELIS4093',
            'syllabus' => ''
        ));
        $testcrs->save();
        $testcrs->load();

        // Create three(3) Class Instances for Course Descrption.
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

        // Assign testuser new role in one Class Instance.
        $context = context_elis_class::instance($testcls2->id);
        role_assign($testrole->id, $testmuser->id, $context->id);

        // Switch to testuser.
        $USER = $testmuser;
        $this->setUser($testmuser);
        $items = block_curr_admin_load_menu_children_course($testcrs->id, 0, 0, 5, '');

        $this->assertEquals(1, count($items));
        $this->assertTrue($items[0]->name == 'pmclass_2');
    }

    /**
     * Test block initialization.
     */
    public function test_blockinitialization() {
        $block = new block_curr_admin();
        $this->assertNotEmpty($block->title);
        $this->setAdminUser();
        $this->assertNotEmpty($block->get_content());
    }
}