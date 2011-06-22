<?php

require_once('../../config.php');
require_once('lib.php');

echo 'header<br/>';
embeddedreports_generate_link('international.jrxml', array('id' => 10));
echo '<br/>footer';
?>