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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/// This parameter defines the application we are using:

define('CURMAN_APPPLATFORM', 'moodle');
// define('BLOCK_POS_CENTRE', 'c'); // now defined in /elis/core/lib/page.class.php
define('CURMAN_DATEFORMAT', "M j, Y");

if (CURMAN_APPPLATFORM == 'moodle') {
    /// Moodle-specific setup:
    if (!isset($CFG->dirroot)) {
        require_once dirname(dirname(__FILE__)) . '/config.php';
    }

/// Define where this file is:
    define('CURMAN_DIRLOCATION', $CFG->dirroot . '/curriculum');

    if (!empty($CFG->dbname)) {
        define('CURMAN_UDB_DBNAME', $CFG->dbname);
    }

    define('CURMAN_UDB_DBHOST', $CFG->dbhost);

    if (!empty($CFG->dbuser)) {
        define('CURMAN_UDB_DBUSER', $CFG->dbuser);
    }
    if (!empty($CFG->dbpass)) {
        define('CURMAN_UDB_DBPASS', $CFG->dbpass);
    }
} else {
    /// Other application-specific setup:

    /// Define where this file is (non-Moodle installations)
    define('CURMAN_DIRLOCATION', '/root/where/this/is/located');
}

require_once CURMAN_DIRLOCATION . '/lib/lib.php';
require_once CURMAN_DIRLOCATION . '/lib/data.class.php';

global $CURMAN; // To be safe... Never sure what context this may be included in.
$CURMAN = new stdClass();

/// Setup database connections:
//$CURMAN->db = database_factory('wr');
//$CURMAN->db->prefix = '';
//$CURMAN->db = NULL;
$CURMAN->db = database_factory(CURMAN_APPPLATFORM);

define('CMCONFIGTABLE', 'crlm_config');
$CURMAN->config = new stdClass();
/// Override any config settings.
/// e.g. $CURMAN->config->textpassword = 1;

require_once dirname(__FILE__) . '/version.php';

/// Load all configuration variable/structures.
$CURMAN->config = cm_load_config();

cm_add_config_defaults();

?>
