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

function xmldb_crlm_cluster_themes_upgrade($oldversion = 0) {
    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2010080602) {
        require_once $CFG->dirroot . '/curriculum/lib/customfield.class.php';
        require_once $CFG->dirroot . '/curriculum/plugins/cluster_classification/lib.php';
        require_once $CFG->dirroot . '/curriculum/plugins/cluster_classification/clusterclassification.class.php';

        //theme priority
        $theme_priority_field = new field(field::get_for_context_level_with_name('cluster', 'cluster_themepriority'));

        if (isset($theme_priority_field->owners['manual'])) {
            $theme_priority_owner = new field_owner($theme_priority_field->owners['manual']);
            $theme_priority_owner->param_help_file = 'crlm_cluster_themes/cluster_themepriority';
            $theme_priority_owner->update();
        }

        //theme selection
        $theme_field = new field(field::get_for_context_level_with_name('cluster', 'cluster_theme'));

        if (isset($theme_field->owners['manual'])) {
            $theme_owner = new field_owner($theme_field->owners['manual']);
            $theme_owner->param_help_file = 'crlm_cluster_themes/cluster_theme';
            $theme_owner->update();
        }
    }

    return $result;
}

?>
