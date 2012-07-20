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
require_once elispm::file('plugins/userset_classification/lib.php');

/**
 * Install function for this plugin
 *
 * @return  boolean  true  Returns true to satisfy install procedure
 */
function xmldb_pmplugins_userset_classification_install() {
    global $CFG;

    require_once elispm::lib('setup.php');
    require_once elis::lib('data/customfield.class.php');
    require_once elispm::file('plugins/userset_classification/usersetclassification.class.php');

    $field = new field();
    $field->shortname = USERSET_CLASSIFICATION_FIELD;
    $field->name = get_string('classification_field_name', 'pmplugins_userset_classification');
    $field->datatype = 'char';

    $category = new field_category();
    $category->name = get_string('classification_category_name', 'pmplugins_userset_classification');

    $field = field::ensure_field_exists_for_context_level($field, CONTEXT_ELIS_USERSET, $category);

    // make sure we're set as owner
    if (!isset($field->owners['userset_classification'])) {
        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'userset_classification';
        $owner->save();
    }

    // make sure 'manual' is an owner
    if (!isset($field->owners['manual'])) {
        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'manual';
        $owner->param_view_capability = '';
        $owner->param_edit_capability = 'moodle/user:update';
        $owner->param_control = 'menu';
        $owner->param_options_source = 'userset_classifications';
        $owner->param_help_file = 'pmplugins_userset_classification/cluster_classification';
        $owner->save();
    }

    // make sure we have a default value set
    if (!field_data::get_for_context_and_field(NULL, $field)) {
        field_data::set_for_context_and_field(NULL, $field, 'regular');
    }

    $default = new usersetclassification();
    $default->shortname = 'regular';
    $default->name = get_string('cluster', 'elis_program');
    $default->param_autoenrol_curricula = 1;
    $default->param_autoenrol_tracks = 1;
    $default->save();

    return true;
}
