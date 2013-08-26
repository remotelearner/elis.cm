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
require_once(elis::lib('data/customfield.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::file('enrol/userset/moodle_profile/userset_profile.class.php'));

/**
 * Test custom userset themes.
 * @group elis_program
 */
class customusersettheme_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/usermoodle.csv'),
            userset::TABLE => elis::component_file('program', 'tests/fixtures/userset.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test custom userset theme assignment.
     */
    public function test_customusersetthemectx() {

        $this->load_csv_data();

        // ELIS user with the associated moodle user.
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        // Userset with a custom theme.
        $userset = new userset(1);
        $userset->field__elis_userset_theme = 'formal_white';
        $userset->field__elis_userset_themepriority = 1;
        $userset->save();

        // Assign the user to the user set.
        $usersetassign = new clusterassignment;
        $usersetassign->userid = $user->id;
        $usersetassign->clusterid = $userset->id;
        $usersetassign->save();

        // Pretend to be that user.
        $this->setUser($muser->id);

        // Initialize page.
        $page = new moodle_page;
        $page->initialise_theme_and_output();

        // Assert we have our theme.
        $this->assertEquals('formal_white', $page->theme->name);

        $this->setUser(null);
    }
}