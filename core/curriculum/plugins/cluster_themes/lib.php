<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * Sets up the fields necessary for enabling cluster theming
 *
 * @return  boolean  Returns true to indicate success
 */
function cluster_themes_install() {

    //retrieve the cluster context
    $cluster_context = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

    //set up the cluster theme category
    $theme_category = new field_category();
    $theme_category->name = get_string('cluster_theme_category', 'crlm_cluster_themes');

    //set up the theme priority field
    $theme_priority_field = new field();
    $theme_priority_field->shortname = 'cluster_themepriority';
    $theme_priority_field->name = get_string('cluster_theme_priority', 'crlm_cluster_themes');
    $theme_priority_field->datatype = 'int';

    //set up the field and category
    $theme_priority_field = field::ensure_field_exists_for_context_level($theme_priority_field, $cluster_context, $theme_category);
    $owner_options = array('required' => 0,
                           'edit_capability' => '',
                           'view_capability' => '',
                           'control' => 'text',
                           'columns' => 30,
                           'rows' => 10,
                           'maxlength' => 2048,
                           'help_file' => 'crlm_cluster_themes/cluster_themepriority');
    field_owner::ensure_field_owner_exists($theme_priority_field, 'manual', $owner_options);

    //set up the field for selecting the applicable theme
    $theme_field = new field();
    $theme_field->shortname = 'cluster_theme';
    $theme_field->name = get_string('cluster_theme', 'crlm_cluster_themes');
    $theme_field->datatype = 'char';

    //set up the field and category
    $theme_field = field::ensure_field_exists_for_context_level($theme_field, $cluster_context, $theme_category);
    $owner_options = array('control' => 'menu',
                           'options_source' => 'themes',
                           'required' => 0,
                           'edit_capability' => '',
                           'view_capability' => '',
                           'columns' => 30,
                           'rows' => 10,
                           'maxlength' => 2048,
                           'help_file' => 'crlm_cluster_themes/cluster_theme');
    field_owner::ensure_field_owner_exists($theme_field, 'manual', $owner_options);

    return true;
}

?>