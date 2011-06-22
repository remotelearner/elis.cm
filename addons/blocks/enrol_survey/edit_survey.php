<?php
    require_once '../../config.php';
    require_once 'forms.php';
    require_once 'lib.php';
    
    $instanceid         = required_param('id', PARAM_INT);

    $add_profilefield   = optional_param('add_profilefield', '', PARAM_CLEAN);
    $retake             = optional_param('retake', '', PARAM_CLEAN);
    $update             = optional_param('update', '', PARAM_CLEAN);
    $exit               = optional_param('exit', '', PARAM_CLEAN);

    $force_user         = optional_param('force_user', false, PARAM_BOOL);

    $custom_names = optional_param('custom_name', 0);
    $profile_fields = optional_param('profile_field', 0);

    $delete = optional_param('delete', 0);

    $instance = get_record('block_instance', 'id', $instanceid);
    $block = block_instance('enrol_survey', $instance);

    if(strcmp($block->instance->pagetype, 'course-view') === 0) {
        require_course_login($block->instance->pageid);
    }

    if($COURSE->id == SITEID) {
        $context = get_context_instance(CONTEXT_SYSTEM);
    } else {
        $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
    }

    $data = get_object_vars($block->config);
    $data['force_user'] = $force_user;
    unset($data['none']);
    
    if(!empty($profile_fields) && count($profile_fields) === count($custom_names)) {
        $tempdata = array_combine($profile_fields, $custom_names);
        $data = array_merge($data, $tempdata);
    }

    $data = (object)$data;

    if(!empty($update) && !empty($data)) {
        $block->instance_config_save($data);
    } else if(!empty($add_profilefield)) {
        $data->none = '';
        $block->instance_config_save($data);
    } else if(!empty($exit)) {
        $block->instance_config_save($data);
        redirect($CFG->wwwroot . '/course/view.php?id=' . $COURSE->id);
    } else if(!empty($retake)) {
        delete_records('block_enrol_survey_taken', 'blockinstanceid', $instanceid);
    } else if(!empty($delete)) {
        $keys = array_keys($delete);

        foreach($keys as $todel) {
            unset($data->$todel);
        }

        $block->instance_config_save($data);
    }

    require_capability('block/enrol_survey:edit', $context);
    
    $edit_form = new edit_survey_form($CFG->wwwroot . '/blocks/enrol_survey/edit_survey.php?id=' . $instanceid);

    $blockname = get_string('blockname', 'block_enrol_survey');
    print_header($blockname, $blockname, build_navigation($blockname));
    print_heading(get_string('surveysettings', 'block_enrol_survey'));
    $edit_form->display();
    print_footer();
?>