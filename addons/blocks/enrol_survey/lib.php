<?php
    
    function is_survey_taken($userid, $instanceid) {
        $temp = get_record('block_enrol_survey_taken', 'userid', $userid, 'blockinstanceid', $instanceid);

        return !empty($temp);
    }

    function get_profilefields() {
        global $CFG;

                //get elis user fields as it appears in the db
                //unset any fields that should never be allowed to be changed eg. the id field
        $sql = 'SELECT u.* FROM ' . $CFG->prefix . 'crlm_user AS u HAVING min(id)';

        $profile_fields = get_object_vars(current(get_records_sql($sql)));
        unset($profile_fields['id']);
        unset($profile_fields['idnumber']);
        $profile_fields = array_keys($profile_fields);

        return array_combine($profile_fields, $profile_fields);
    }

    function get_customfields() {
        $retval = array();
                //pull custom field values from moodle
        $custom_fields = get_records('user_info_field');
        if(!empty($custom_fields)) {
            foreach($custom_fields as $cf) {
                $profile_fields[] = $cf->shortname;
            }

            $retval = array_combine($profile_fields, $profile_fields);
        }

        return $retval;
    }

    function get_fields() {
        return array_merge(get_profilefields(), get_customfields());
    }

    function get_questions() {
        global $block;

        $other = array('force_user', 'title');
        $retval = array();

        if(!empty($block->config)) {
            foreach($block->config as $key=>$value) {
                if(!in_array($key, $other)) {
                    $retval[$key] = $value;
                }
            }
        }

        return $retval;
    }

    function get_forceuser() {
        global $block;

        return empty($block->config->force_user)?false: true;
    }
?>
