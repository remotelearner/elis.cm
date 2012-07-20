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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elispm::file('plugins/archive/lib.php');

/**
 * Install function for this plugin
 *
 * @return  boolean  true  Returns true to satisfy install procedure
 */
function xmldb_pmplugins_archive_install() {
    global $CFG;

    require_once elispm::lib('setup.php');
    require_once elis::lib('data/customfield.class.php');

    // Archive field
    $field = new field();
    $field->shortname = ARCHIVE_FIELD;
    $field->name = get_string('archive_field_name', 'pmplugins_archive');
    $field->datatype = 'bool';

    $category = new field_category();
    $category->name = get_string('archive_category_name', 'pmplugins_archive');

    $field = field::ensure_field_exists_for_context_level($field, CONTEXT_ELIS_PROGRAM, $category);

    // make sure 'manual' is an owner
    if (!isset($field->owners['manual'])) {
        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'manual';
        $owner->param_required = 0;
        $owner->param_view_capability = '';
        $owner->param_edit_capability = '';
        $owner->param_control = 'checkbox';
        $owner->param_options_source = '';
        $owner->param_help_file = 'pmplugins_archive/archive_program';
        $owner->save();
    }

    $owner_options = array('required'        => 0,
                           'edit_capability' => '',
                           'view_capability' => '',
                           'control'         => 'checkbox',
                           'columns'         => 30,
                           'rows'            => 10,
                           'maxlength'       => 2048,
                           'help_file'       => 'pmplugins_archive/archive_program');
    field_owner::ensure_field_owner_exists($field, 'manual', $owner_options);

    return true;
}

