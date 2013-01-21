<?php
set_include_path(dirname(dirname(dirname(dirname(__FILE__)))).'/lib/pear' . PATH_SEPARATOR . get_include_path());
global $CFG;
require_once(dirname(__FILE__) . '/../../core/test_config.php');
require_once($CFG->dirroot . '/elis/program/lib/setup.php');

function local_phpunit_autoload($class) {
    if (strpos($class, 'PHPUnit_') === 0) {
        $file = str_replace('_', '/', $class) . '.php';
        $file = PHPUnit_Util_Filesystem::fileExistsInIncludePath($file);

        if ($file) {
            require_once $file;
        }
    }
}

spl_autoload_register('local_phpunit_autoload');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');