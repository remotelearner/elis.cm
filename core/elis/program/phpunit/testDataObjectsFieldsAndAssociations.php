<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis
 * @subpackage program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');

/** Quick test of all /elis/program/lib/data/*.class.php files
 *  to check $_dbfield_ properties and associations.
 */
class DataObjectBuiltInTest extends PHPUnit_Framework_TestCase {
    protected $backupGlobals = FALSE;

    /**
     * Test that ALL data classes have correct DB fields & associations
     */
    public function testAllDataFiles() {
        global $CFG;
        $data_dir = "{$CFG->dirroot}/elis/program/lib/data";
        $data_files = scandir($data_dir);
        $dbfield_errors = 0;
        $association_errors = 0;
        foreach ($data_files as $data_file) {
            $filename = "{$data_dir}/{$data_file}";
            if (!is_dir($filename) &&
                ($ext = strpos($data_file, '.class.php')) !== false &&
                strlen($data_file) == ($ext + 10)) {
                require_once($filename);
                $classname = substr($data_file, 0, $ext);
                error_log("testDataFiles(): {$filename} => {$classname} ... ");
                $testobj = new $classname();
                if (!is_a($testobj, 'elis_data_object')) {
                    error_log("testDataFiles(): WARNING {$classname} is NOT an elis_data_object subclass! File: {$filename}");
                    continue;
                }
                if (!$testobj->_test_dbfields()) {
                    $dbfield_errors++;
                }
                if (!$testobj->_test_associations()) {
                    $association_errors++;
                }
            }
        }
        $error_str = '';
        if ($dbfield_errors) {
            $error_str .= "{$dbfield_errors} files(s) with \$_dbfield_ property errors. ";
        }
        if ($association_errors) {
            $error_str .= "{$association_errors} files(s) with association errors. ";
        }
        if (!empty($error_str)) {
            $error_str .= 'Check error_log for details!';
        }

        $this->assertEquals(0, $dbfield_errors + $association_errors, $error_str);
    }

}
