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

function xmldb_pmplugins_userset_themes_upgrade($oldversion = 0) {
    global $CFG, $THEME, $DB;
    $dbman = $DB->get_manager();

    $result = true;

    if ($oldversion < 2011071300) {
        // rename fields
        $fieldnames = array('theme', 'themepriority');
        foreach ($fieldnames as $fieldname) {
            $field = field::find(new field_filter('shortname', 'cluster_'.$fieldname));

            if ($field->valid()) {
                $field = $field->current();
                $field->shortname = '_elis_userset_'.$fieldname;
                $field->save();
            }
        }

        upgrade_plugin_savepoint($result, 2011071300, 'pmplugins', 'userset_themes');
    }

    if ($result && $oldversion < 2011101800) {
        // Userset -> 'User Set'
        $fieldnames = array('theme', 'themepriority');
        foreach ($fieldnames as $fieldname) {
            $fname = '_elis_userset_'. $fieldname;
            $field = field::find(new field_filter('shortname', $fname));

            if ($field->valid()) {
                $field = $field->current();
                // Add help file
                if ($owner = new field_owner((!isset($field->owners) || !isset($field->owners['manual'])) ? false : $field->owners['manual'])) {
                    $owner->fieldid = $field->id;
                    $owner->plugin = 'manual';
                    //$owner->exclude = 0; // TBD
                    $owner->param_help_file = "pmplugins_userset_themes/{$fname}";
                    $owner->save();
                }

                $category = $field->category;
                if (stripos($category->name, 'Userset') !== false) {
                    $category->name = str_ireplace('Userset', 'User Set', $category->name);
                    $category->save();
                }
            }
        }

        upgrade_plugin_savepoint($result, 2011101800, 'pmplugins', 'userset_themes');
    }

    if ($oldversion < 2013020400) {
        // rename field if it is still 'Cluster Theme'
        $field = field::find(new field_filter('shortname', '_elis_userset_theme'));

        if ($field->valid()) {
            $field = $field->current();
            $category = $field->category;
            if ($category->name == 'Cluster Theme') {
                // the field name hasn't been changed from the old default
                $category->name = get_string('userset_theme_category', 'pmplugins_userset_themes');
                $category->save();
            }
        }

        upgrade_plugin_savepoint($result, 2013020400, 'pmplugins', 'userset_themes');
    }

    return $result;
}
