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
 * @subpackage program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once($CFG->dirroot.'/elis/program/curriculumcoursepage.class.php');

class testPermissions extends elis_database_test {

    /**
     * Return the list of tables that should be overlayed.
     *
     * @return array Mapping of tables to component names
     */
    static protected function get_overlay_tables() {
        global $CFG;
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/usermoodle.class.php'));

        return array(user::TABLE => 'elis_program',
                     usermoodle::TABLE => 'elis_program',
                     'config_plugins' => 'moodle',
                     'context' => 'moodle',
                     'role' => 'moodle',
                     'role_assignments' => 'moodle',
                     'role_capabilities' => 'moodle',
                     'user' => 'moodle',
                     'config' => 'moodle',
                     'elis_field_data_char' => 'elis_core',
                     'elis_field_data_int' => 'elis_core',
                     'elis_field_data_num' => 'elis_core',
                     'elis_field_data_text' => 'elis_core'
               );
    }

    /**
     * Return the list of tables that should be ignored for writes.
     *
     * @return array Mapping of tables to component names
     */
    static protected function get_ignored_tables() {
        return array('cache_flags' => 'moodle');
    }

    // Test for correct assignment of course permissions
    public function testCoursePermissions() {
        global $DB, $CFG, $USER;

        accesslib_clear_all_caches(true);

        set_config('siteguest', '');
        set_config('siteadmins', '');

        $DB->execute("INSERT INTO {context} SELECT * FROM " . self::$origdb->get_prefix() .
                     "context WHERE contextlevel = ?", array(CONTEXT_SYSTEM));
        $syscontext = get_context_instance(CONTEXT_SYSTEM);

        $assigning_user = new user(array('idnumber' => 'testuserid',
                                         'username' => 'testuser',
                                         'firstname' => 'testuser',
                                         'lastname' => 'testuser',
                                         'email' => 'testuser@testuserdomain.com',
                                         'country' => 'CA'));
        $assigning_user->save();
        $roleid = create_role('userrole', 'userrole', 'userrole');

        $USER = $DB->get_record('user', array('username' => 'testuser'));

        assign_capability('elis/program:associate', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('elis/program:manage', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('elis/program:program_create', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('elis/program:program_enrol', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('elis/program:program_view', CAP_ALLOW, $roleid, $syscontext->id);

        role_assign($roleid, $USER->id, $syscontext->id);

        $crscurpg = new coursecurriculumpage();
        $this->assertTrue($crscurpg->has_program_create_capability());
        $this->assertTrue($crscurpg->has_associate_and_manage_capability());

        $curcrspg = new curriculumcoursepage();
        $this->assertTrue($curcrspg->has_program_view_capability());
    }

}

?>

