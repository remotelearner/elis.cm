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

/**
 * Sets up the fields necessary for enabling cluster groups
 *
 * @return  boolean  Returns true to indicate success
 */
function xmldb_pmplugins_userset_groups_install() {
    //set up the cluster group category
    $group_category = new field_category();
    $group_category->name = get_string('userset_group_category', 'pmplugins_userset_groups');

    //set up the field that allows users to turn the groupings on
    $group_field = new field();
    $group_field->shortname = 'userset_group';
    $group_field->name = get_string('userset_group', 'pmplugins_userset_groups');
    $group_field->datatype = 'bool';

    //set up the field and category
    $group_field = field::ensure_field_exists_for_context_level($group_field, CONTEXT_ELIS_USERSET, $group_category);

    //set up the field owner
    $owner_options = array('required' => 0,
                           'edit_capability' => '',
                           'view_capability' => '',
                           'control' => 'checkbox',
                           'columns' => 30,
                           'rows' => 10,
                           'maxlength' => 2048,
                           'help_file' => 'pmplugins_userset_groups/userset_group',
                          );
    field_owner::ensure_field_owner_exists($group_field, 'manual', $owner_options);

    $field = new field();
    $field->shortname = 'userset_groupings';
    $field->name = get_string('autoenrol_groupings', 'pmplugins_userset_classification');
    $field->datatype = 'bool';
    $field = field::ensure_field_exists_for_context_level($field, CONTEXT_ELIS_USERSET, $group_category);

    $owner_options = array('required' => 0,
                           'edit_capability' => '',
                           'view_capability' => '',
                           'control' => 'checkbox',
                           'columns' => 30,
                           'rows' => 10,
                           'maxlength' => 2048,
                           'help_file' => 'pmplugins_userset_groups/autoenrol_groupings');
    field_owner::ensure_field_owner_exists($field, 'manual', $owner_options);

    return true;
}
