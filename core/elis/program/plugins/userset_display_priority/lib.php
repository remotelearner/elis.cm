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

define('USERSET_DISPLAY_PRIORITY_FIELD', '_elis_userset_display_priority');

/**
 * Appends additional data to query parameters based on existence of theme priority field
 *
 * @param  string  $cluster_id_field  The field to join on for the cluster id
 * @param  string  $select            The current select clause
 * @param  string  $join              The current join clause
 */
function userset_display_priority_append_sort_data($userset_id_field, &$select, &$join) {
    global $DB;

    //make sure we can get the field we need for ordering
    if ($theme_priority_field = new field(field::get_for_context_level_with_name(CONTEXT_ELIS_USERSET, USERSET_DISPLAY_PRIORITY_FIELD))) {
        $field_data_table = $theme_priority_field->data_table();

        //use this for easier naming in terms of sorting
        $select .= ', field_data.data AS priority ';

        $join .= ' LEFT JOIN ({context} context
                   JOIN {'.$field_data_table.'} field_data
                     ON field_data.contextid = context.id
                     AND field_data.fieldid = '.$theme_priority_field->id.')

                     ON context.contextlevel = '.CONTEXT_ELIS_USERSET.'
                     AND context.instanceid = '.$userset_id_field.'
                 ';
    }
}