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

// Libs.
require_once($CFG->dirroot.'/elis/program/curriculumcoursepage.class.php');
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Test permissions.
 * @group elis_program
 */
class permissions_testcase extends elis_database_test {

    /**
     * Test for correct assignment of course permissions
     */
    public function test_coursepermissions() {
        global $DB, $CFG, $USER;

        accesslib_clear_all_caches(true);

        set_config('siteguest', '');
        set_config('siteadmins', '');

        $this->assertFalse($this->has_program_create_capability());
        $this->assertFalse($this->has_associate_and_manage_capability());
        $this->assertFalse($this->has_program_view_capability());

        $syscontext = get_context_instance(CONTEXT_SYSTEM);

        $assigninguser = new user(array(
            'idnumber' => 'testuserid',
            'username' => 'testuser',
            'firstname' => 'testuser',
            'lastname' => 'testuser',
            'email' => 'testuser@testuserdomain.com',
            'country' => 'CA'
        ));
        $assigninguser->save();
        $roleid = create_role('userrole', 'userrole', 'userrole');

        $usr = $DB->get_record('user', array('username' => 'testuser'));
        $this->setUser($usr);
        $USER = $usr;

        assign_capability('elis/program:associate', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('elis/program:manage', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('elis/program:program_create', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('elis/program:program_enrol', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('elis/program:program_view', CAP_ALLOW, $roleid, $syscontext->id);

        role_assign($roleid, $usr->id, $syscontext->id);

        $this->assertTrue($this->has_program_create_capability());
        $this->assertTrue($this->has_associate_and_manage_capability());
        $this->assertTrue($this->has_program_view_capability());

        $this->setUser(null);
    }

    public function has_associate_and_manage_capability() {
        if (has_capability('elis/program:associate', context_system::instance()) ||
                has_capability('elis/program:manage', context_system::instance())) {
            return true;
        }
        return false;
    }

    public function has_program_view_capability() {
        return has_capability('elis/program:program_view', context_system::instance());
    }

    public function has_program_create_capability() {
        return has_capability('elis/program:program_create', context_system::instance());
    }
}