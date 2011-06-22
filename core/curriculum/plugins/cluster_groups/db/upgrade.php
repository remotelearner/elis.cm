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

function xmldb_crlm_cluster_groups_upgrade($oldversion = 0) {
    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2010080602) {
        require_once $CFG->dirroot . '/curriculum/lib/customfield.class.php';
        require_once $CFG->dirroot . '/curriculum/plugins/cluster_classification/lib.php';
        require_once $CFG->dirroot . '/curriculum/plugins/cluster_classification/clusterclassification.class.php';
        $field = new field(field::get_for_context_level_with_name('cluster', 'cluster_group'));

        if (isset($field->owners['manual'])) {
            $owner = new field_owner($field->owners['manual']);
            $owner->param_help_file = 'crlm_cluster_groups/cluster_groups';
            $owner->update();
        }
    }

    if ($result && $oldversion < 2010080603) {
        //retrieve the cluster context
        $context = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

        //get the cluster classification category
        $category = new field_category();
        $category->name = get_string('cluster_group_category', 'crlm_cluster_groups');

        $field = new field();
        $field->shortname = 'cluster_groupings';
        $field->name = get_string('autoenrol_groupings', 'crlm_cluster_classification');
        $field->datatype = 'bool';
        $field = field::ensure_field_exists_for_context_level($field, $context, $category);

        $owner_options = array('required' => 0,
                               'edit_capability' => '',
                               'view_capability' => '',
                               'control' => 'checkbox',
                               'columns' => 30,
                               'rows' => 10,
                               'maxlength' => 2048,
                               'help_file' => 'crlm_cluster_groups/cluster_groupings');
        field_owner::ensure_field_owner_exists($field, 'manual', $owner_options);
    }
    
    if ($result && $oldversion < 2010080604) {
        if ($field = new field(field::get_for_context_level_with_name('cluster', 'cluster_site_course_group'))) {
            $field->shortname = 'cluster_groupings';
            $field->update();
        }
    }

    return $result;
}


?>
