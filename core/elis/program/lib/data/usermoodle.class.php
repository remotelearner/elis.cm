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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elis::lib('data/data_object.class.php');

/**
 * Class that represents an association between a Moodle user and a PM user
 */
class usermoodle extends elis_data_object {
    const TABLE = 'crlm_user_moodle';

    static $validation_rules = array(
        array('validation_helper', 'is_unique_cuserid_muserid'),
        array('validation_helper', 'is_unique_idnumber')
    );

    var $verbosename = 'usermoodle';

    //database fields
    protected $_dbfield_cuserid;
    protected $_dbfield_muserid;
    protected $_dbfield_idnumber;
}