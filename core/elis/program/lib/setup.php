<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once dirname(__FILE__).'/../../core/lib/setup.php';
require_once dirname(__FILE__).'/../accesslib.php';

/**
 * Global Program Manager object.
 */
class elispm {
    /**
     * The Program Manager DB version
     */
    static $version;

    /**
     * The Program Manager human-readable release
     */
    static $release;

    /**
     * The base directory for the PM code.
     */
    static $basedir;

    /**
     * Return the full path name for a PM file.
     */
    static function file($file) {
        return elis::component_file('program', $file);
    }

    /**
     * The base directory for the PM libraries.
     */
    static $libdir;

    /**
     * Return the full path name for a PM library file.
     */
    static function lib($file) {
        return elispm::file("lib/{$file}");
    }

    /**
     * The default database object.
     */
    static $db;
}

global $CFG;
elispm::$basedir = elis::file('program');
elispm::$libdir = elis::component_file('program', 'lib');

global $DB;
elispm::$db = $DB;

{
    $plugin = new stdClass;
    include elispm::file('version.php');
    elispm::$version = $plugin->version;
    elispm::$release = $plugin->release;
}
