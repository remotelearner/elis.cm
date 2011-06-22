<?php

define('CLUSTER_DISPLAY_PRIORITY_FIELD', '_elis_cluster_display_priority');

/**
 * Install function for this plugin
 *
 * @return  boolean  true  Returns true to satisfy install procedure
 */
function cluster_display_priority_install() {

    //context level at which we are creating the new field(s)
    $cluster_ctx_lvl = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

    $field = new field();
    $field->shortname = CLUSTER_DISPLAY_PRIORITY_FIELD;
    $field->name = get_string('display_priority_field_name', 'crlm_cluster_display_priority');
    $field->datatype = 'int';

    $category = new field_category();
    $category->name = get_string('display_settings_category_name', 'crlm_cluster_display_priority');

    $field = field::ensure_field_exists_for_context_level($field, 'cluster', $category);

    // make sure 'manual' is an owner
    if (!isset($field->owners['manual'])) {
        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'manual';
        $owner->param_view_capability = '';
        $owner->param_edit_capability = '';
        $owner->param_control = 'text';
        $owner->param_options_source = 'cluster_display_priority';
        $owner->add();
    }

    return true;
}

/**
 * Appends additional data to query parameters based on existence of theme priority field
 *
 * @param  string  $cluster_id_field  The field to join on for the cluster id
 * @param  string  $select            The current select clause
 * @param  string  $join              The current join clause
 */
function cluster_display_priority_append_sort_data($cluster_id_field, &$select, &$join) {
    global $CURMAN;

    //make sure we can get the field we need for ordering
    if($theme_priority_field = new field(field::get_for_context_level_with_name('cluster', CLUSTER_DISPLAY_PRIORITY_FIELD)) and
       $contextlevel = context_level_base::get_custom_context_level('cluster', 'block_curr_admin')) {

        $field_data_table = $CURMAN->db->prefix_table($theme_priority_field->data_table());

        //use this for easier naming in terms of sorting
        $select .= ', field_data.data AS priority ';

        $join .= "LEFT JOIN ({$CURMAN->db->prefix_table('context')} context
                  JOIN {$field_data_table} field_data
                    ON field_data.contextid = context.id
                    AND field_data.fieldid = {$theme_priority_field->id})

                    ON context.contextlevel = {$contextlevel}
                    AND context.instanceid = {$cluster_id_field} ";
    }
}

?>