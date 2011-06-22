<?php
function is_ip_installed() {
    return record_exists('block', 'name', 'rlip');
}
?>