<?php

require_once($CFG->dirroot . '/curriculum/lib/jasperlib.php');

/**
 * Create a link that allows for displaying embedded Jasper reports
 * on an HTML page
 *
 * @param   $uri        The resource id of the necessary report
 * @param   $parameters Additional parameters to be passed to the report
 * @param   $print      If true, prints iframe on page
 * @return              The HTML of the iframe containing the report
 *
 */
function embeddedreports_generate_link($uri, $parameters=array(), $print = true) {
    global $USER;

    $parameters['elisembedded'] = 'true';

    if (!is_enabled_auth('mnet')) {
        error('mnet is disabled');
    }

    // check remote login permissions
    if (! has_capability('moodle/site:mnetlogintoremote', get_context_instance(CONTEXT_SYSTEM))
            or is_mnet_remote_user($USER)
            or $USER->username == 'guest'
            or empty($USER->id)) {
        print_error('notpermittedtojump', 'mnet');
    }

    $mnet_auth = get_auth_plugin('mnet');

    // check for SSO publish permission first
    if ($mnet_auth->has_service(jasper_mnet_hostid(), 'sso_sp') == false) {
        print_error('hostnotconfiguredforsso', 'mnet');
    }

    $mnet_link = jasper_mnet_link(jasper_report_link($uri, $parameters));

    $result = '<iframe id="reportframe" name="reportframe" src="' . $mnet_link . '"></iframe>';

    if($print) {
        echo $result;
    }

    return $result;
}

?>