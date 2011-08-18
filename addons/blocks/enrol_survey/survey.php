<?php
    require_once '../../config.php';
    require_once 'forms.php';
    require_once 'lib.php';
    require_once $CFG->dirroot . '/curriculum/config.php';
    require_once CURMAN_DIRLOCATION . '/lib/user.class.php';

    $instanceid         = required_param('id', PARAM_INT);
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

    require_capability('block/enrol_survey:take', $context);

    /* TBD: avoid error from $u->update() below
    if (cm_get_crlmuserid($USER->id) === false) {
        print_error(get_string('noelisuser', 'block_enrol_survey'));
    }
    */

    $survey_form = new survey_form($CFG->wwwroot . '/blocks/enrol_survey/survey.php?id=' . $instanceid);

    if ($survey_form->is_cancelled()){
        redirect($CFG->wwwroot . '/course/view.php?id=' . $COURSE->id);
    } else if($formdata=$survey_form->get_data()) {
        $customfields = get_customfields();
        $profilefields = get_profilefields();

        $data = get_object_vars($formdata);
        $u = new user(cm_get_crlmuserid($USER->id));

        foreach($data as $key=>$fd) {
            if(!empty($fd)) {
                if(in_array($key, $profilefields)) {
                    if(!empty($u->properties[$key])) {
                        $u->$key($fd);
                    }
                } else if(in_array($key, $customfields)) {
                    $id = get_field('user_info_field', 'id', 'shortname', $key);

                    if(record_exists('user_info_data', 'userid', $USER->id, 'fieldid', $id)) {
                        set_field('user_info_data', 'data', $fd, 'userid', $USER->id, 'fieldid', $id);
                    } else {
                        $dataobj = new object();
                        $dataobj->userid = $USER->id;
                        $dataobj->fieldid = $id;
                        $dataobj->data = $fd;
                        insert_record('user_info_data', $dataobj);
                    }
                }
            } else {
                $incomplete = true;
            }
        }

        $u->update();
       
        $usernew = get_record('user', 'id', $USER->id);
        foreach ((array)$usernew as $variable => $value) {
            $USER->$variable = $value;
        }

        if(!is_survey_taken($USER->id, $instanceid) && empty($incomplete)) {
            $dataobject = new object();
            $dataobject->blockinstanceid = $instanceid;
            $dataobject->userid = $USER->id;
            insert_record('block_enrol_survey_taken', $dataobject);
        }

        if(!empty($formdata->save_exit)) {
            redirect($CFG->wwwroot . '/course/view.php?id=' . $COURSE->id);
        }
    }

    $toform = array();
    $u = new user(cm_get_crlmuserid($USER->id));
    $toform = get_object_vars($u);

    $customdata = get_records('user_info_data', 'userid', $USER->id);
    if(!empty($customdata)) {
        foreach($customdata as $cd) {
            $customfields = get_record('user_info_field', 'id', $cd->fieldid);
            $toform[$customfields->shortname] = $cd->data;
        }
    }

    $survey_form->set_data($toform);

    $blockname = get_string('blockname', 'block_enrol_survey');
    print_header($blockname, $blockname, build_navigation($blockname));
    print_heading($block->config->title);
    $survey_form->display();
    print_footer();
?>
