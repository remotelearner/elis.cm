<?php
require_once (CURMAN_DIRLOCATION.'/elis_ip/elis_ip.php');

global $CFG;

if(is_ip_installed()) {
    require_once ($CFG->dirroot . '/blocks/rlip/elis/dataimportpage.class.php');
} else {
    require_once (CURMAN_DIRLOCATION . '/elis_ip/dataimportpage.class.php');
}
?>
