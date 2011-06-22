<?php

define('CLUSTER_CLASSIFICATION_FIELD', '_elis_cluster_classification');

function cluster_classification_install() {
    global $CFG;
    require_once $CFG->dirroot.'/curriculum/config.php';
    require_once CURMAN_DIRLOCATION . '/lib/customfield.class.php';
    require_once CURMAN_DIRLOCATION . '/plugins/cluster_classification/clusterclassification.class.php';

    $cluster_ctx_lvl = context_level_base::get_custom_context_level('cluster', 'block_curr_admin');

    $field = new field();
    $field->shortname = CLUSTER_CLASSIFICATION_FIELD;
    $field->name = get_string('classification_field_name', 'crlm_cluster_classification');
    $field->datatype = 'char';

    $category = new field_category();
    $category->name = get_string('classification_category_name', 'crlm_cluster_classification');

    $field = field::ensure_field_exists_for_context_level($field, 'cluster', $category);

    // make sure we're set as owner
    if (!isset($field->owners['cluster_classification'])) {
        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'cluster_classification';
        $owner->add();
    }

    // make sure 'manual' is an owner
    if (!isset($field->owners['manual'])) {
        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'manual';
        $owner->param_view_capability = '';
        $owner->param_edit_capability = 'moodle/user:update';
        $owner->param_control = 'menu';
        $owner->param_options_source = 'cluster_classifications';
        $owner->add();
    }

    // make sure we have a default value set
    if (!field_data::get_for_context_and_field(NULL, $field)) {
        field_data::set_for_context_and_field(NULL, $field, 'regular');
    }

    $default = new clusterclassification();
    $default->shortname = 'regular';
    $default->name = get_string('cluster', 'block_curr_admin');
    $default->param_autoenrol_curricula = 1;
    $default->param_autoenrol_tracks = 1;
    $default->add();

    return true;
}

?>
