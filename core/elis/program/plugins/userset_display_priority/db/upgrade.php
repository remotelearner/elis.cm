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
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::plugin_file('pmplugins_userset_display_priority', 'lib.php'));

function xmldb_pmplugins_userset_display_priority_upgrade($oldversion = 0) {
    global $CFG, $THEME, $DB;
    $dbman = $DB->get_manager();

    $result = true;

    if ($oldversion < 2011071200) {
        // rename field
        $field = field::find(new field_filter('shortname', '_elis_cluster_display_priority'));

        if ($field->valid()) {
            $field = $field->current();
            $field->shortname = USERSET_DISPLAY_PRIORITY_FIELD;
            $field->save();

            $category = $field->category;
            if ($category->name == 'Cluster Display Settings') {
                // the field name hasn't been changed from the old default
                $category->name = get_string('display_settings_category_name', 'pmplugins_userset_display_priority');
                $category->save();
            }
        }

        upgrade_plugin_savepoint($result, 2011071200, 'pmplugins', 'userset_display_priority');
    }

    if ($result && $oldversion < 2011101200) {
        $field = field::find(new field_filter('shortname', USERSET_DISPLAY_PRIORITY_FIELD));

        if ($field->valid()) {
            $field = $field->current();
            if ($owner = new field_owner((!isset($field->owners) || !isset($field->owners['manual'])) ? false : $field->owners['manual'])) {
                $owner->fieldid = $field->id;
                $owner->plugin = 'manual';
                //$owner->exclude = 0; // TBD
                $owner->param_help_file = 'pmplugins_userset_display_priority/display_priority';
                $owner->save();
            }
        }

        upgrade_plugin_savepoint($result, 2011101200, 'pmplugins', 'userset_display_priority');
    }

    if ($result && $oldversion < 2011101800) {
        // Userset -> 'User Set'
        $field = field::find(new field_filter('shortname', USERSET_DISPLAY_PRIORITY_FIELD));

        if ($field->valid()) {
            $field = $field->current();
            $category = $field->category;
            if (stripos($category->name, 'Userset') !== false) {
                $category->name = str_ireplace('Userset', 'User Set', $category->name);
                $category->save();
            }
        }

        upgrade_plugin_savepoint($result, 2011101800, 'pmplugins', 'userset_display_priority');
    }

    return $result;
}
