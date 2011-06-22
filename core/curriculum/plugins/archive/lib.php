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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

function archive_install() {
    global $CFG;
    require_once $CFG->dirroot.'/curriculum/config.php';
    require_once CURMAN_DIRLOCATION . '/lib/customfield.class.php';

    // setup archive field
    $field = new field();
    $field->shortname = '_elis_curriculum_archive';
    $field->name = get_string('archive_field_name', 'crlm_archive');
    $field->datatype = 'bool';

    $category = new field_category();
    $category->name = get_string('archive_category_name', 'crlm_archive');

    $field = field::ensure_field_exists_for_context_level($field, 'curriculum', $category);

    // make sure 'manual' is an owner
    if (!isset($field->owners['manual'])) {
        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'manual';
        $owner->param_view_capability = '';
        $owner->param_edit_capability = '';
        $owner->param_control = 'menu';
        $owner->param_options_source = '';
        $owner->add();
    }

    $owner_options = array('required' => 0,
                           'edit_capability' => '',
                           'view_capability' => '',
                           'control' => 'checkbox',
                           'columns' => 30,
                           'rows' => 10,
                           'maxlength' => 2048,
                           'help_file' => 'crlm_archive/archive_curriculum');
    field_owner::ensure_field_owner_exists($field, 'manual', $owner_options);

    return true;
}

?>
