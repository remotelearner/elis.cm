<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

function xmldb_elis_program_install() {
    global $CFG;

    require_once($CFG->dirroot.'/blocks/curr_admin/lib.php');
    require_once($CFG->dirroot.'/elis/program/lib/lib.php');

    //make sure the site has exactly one curr admin block instance
    //that is viewable everywhere
    block_curr_admin_create_instance();

    // make sure that the manager role can be assigned to all PM context levels
    update_capabilities('elis_program'); // load context levels
    pm_ensure_role_assignable('manager');
    pm_ensure_role_assignable('curriculumadmin');

    // Migrate dataroot files
    pm_migrate_certificate_files();
}
