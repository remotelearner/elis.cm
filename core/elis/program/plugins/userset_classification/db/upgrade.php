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
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::plugin_file('pmplugins_userset_classification', 'lib.php'));

function xmldb_pmplugins_userset_classification_upgrade($oldversion = 0) {
    global $CFG, $THEME, $DB;
    $dbman = $DB->get_manager();

    $result = true;

    if ($oldversion < 2011071400) {
        // rename field
        $field = field::find(new field_filter('shortname', '_elis_cluster_classification'));

        if ($field->valid()) {
            $field = $field->current();

            $field->shortname = USERSET_CLASSIFICATION_FIELD;
            if ($field->name == 'Cluster classification') {
                // the field name hasn't been changed from the old default
                $field->name = get_string('classification_field_name', 'pmplugins_userset_classification');
            }
            $field->save();

            $category = $field->category;
            if ($category->name == 'Cluster classification') {
                // the field name hasn't been changed from the old default
                $category->name = get_string('classification_category_name', 'pmplugins_userset_classification');
                $category->save();
            }
        }

        upgrade_plugin_savepoint($result, 2011071400, 'pmplugins', 'userset_classification');
    }

    if ($result && $oldversion < 2011101200) {

        $field = field::find(new field_filter('shortname', USERSET_CLASSIFICATION_FIELD));

        if ($field->valid()) {
            $field = $field->current();
            if ($owner = new field_owner((!isset($field->owners) || !isset($field->owners['manual'])) ? false : $field->owners['manual'])) {
                $owner->fieldid = $field->id;
                $owner->plugin = 'manual';
                //$owner->exclude = 0; // TBD
                $owner->param_help_file = 'pmplugins_userset_classification/cluster_classification';
                $owner->save();
            }
        }

        upgrade_plugin_savepoint($result, 2011101200, 'pmplugins', 'userset_classification');
    }

    if ($result && $oldversion < 2011101800) {
        // Userset -> 'User Set'
        $field = field::find(new field_filter('shortname', USERSET_CLASSIFICATION_FIELD));

        if ($field->valid()) {
            $field = $field->current();
            if (stripos($field->name, 'Userset') !== false) {
                $field->name = str_ireplace('Userset', 'User Set', $field->name);
                $field->save();
            }

            $category = $field->category;
            if (stripos($category->name, 'Userset') !== false) {
                $category->name = str_ireplace('Userset', 'User Set', $category->name);
                $category->save();
            }
        }

        upgrade_plugin_savepoint($result, 2011101800, 'pmplugins', 'userset_classification');
    }

    if ($result && $oldversion < 2011110300) {
        // Make sure to rename the default classification name from "Cluster" to "User set"
        require_once(elispm::file('plugins/userset_classification/usersetclassification.class.php'));

        //make sure there are no custom fields with invalid categories
        pm_fix_orphaned_fields();

        $field = field::find(new field_filter('shortname', USERSET_CLASSIFICATION_FIELD));

        if ($field->valid()) {
            $field = $field->current();
            $category = $field->category;

	        $default = usersetclassification::find(new field_filter('shortname', 'regular'));

	        if ($default->valid()) {
	            $default = $default->current();
	            $default->name = get_string('cluster', 'elis_program');
	            $default->save();
	        }

	        // Upgrade field owner data for the default User Set field
	        $field = field::ensure_field_exists_for_context_level($field, CONTEXT_ELIS_USERSET, $category);

	        $owners = field_owner::find(new field_filter('fieldid', $field->id));

	        if ($owners->valid()) {
	            foreach ($owners as $owner) {
	                if ($owner->plugin == 'cluster_classification') {
	                    $owner->plugin = 'userset_classification';

	                    $owner->save();
	                } else if ($owner->plugin == 'manual') {
	                    $owner->param_options_source = 'userset_classifications';
	                    $owner->param_help_file = 'pmplugins_userset_classification/cluster_classification';
	                    $owner->save();
	                }
	            }
	        }

	        upgrade_plugin_savepoint($result, 2011110300, 'pmplugins', 'userset_classification');
        }
    }

    return $result;
}
