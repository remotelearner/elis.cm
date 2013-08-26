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
 * @package    pmplugins_userset_groups
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../../../core/test_config.php');
global $CFG;

// Libs.
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::lib('data/userset.class.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));

/**
 * Test userset groups plugin.
 * @group pmplugins_userset_groups
 * @group elis_program
 */
class usersetgroups_testcase extends elis_database_test {

    /**
     * Test installing.
     */
    public function test_install() {
        global $DB;

        require_once(dirname(__FILE__).'/../db/install.php');

        $this->assertTrue(xmldb_pmplugins_userset_groups_install());

        // Check the "group" field and owner.
        $field = $DB->get_record(field::TABLE, array('shortname' => 'userset_group'));
        $this->assertNotEmpty($field->id);

        $this->assertTrue($DB->record_exists(field_owner::TABLE, array('fieldid' => $field->id, 'plugin' => 'manual')));

        // Check the "groupings" field and owner.
        $field = $DB->get_record(field::TABLE, array('shortname' => 'userset_groupings'));
        $this->assertNotEmpty($field->id);

        $this->assertTrue($DB->record_exists(field_owner::TABLE, array('fieldid' => $field->id, 'plugin' => 'manual')));
    }
}