<?php
require_once ('../config.php');

require_once CURMAN_DIRLOCATION.'/lib/lib.php';

if (isset($_SERVER['REMOTE_ADDR'])) {
    $context = get_context_instance(CONTEXT_SYSTEM);
    require_capability('moodle/site:doanything', $context);
}

cm_migrate_moodle_users(false);
?>
