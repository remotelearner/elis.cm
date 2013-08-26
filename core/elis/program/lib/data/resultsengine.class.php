<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elis::lib('data/data_object.class.php');
require_once elis::lib('table.class.php');
require_once elispm::lib('lib.php');
require_once elispm::lib('deprecatedlib.php');
require_once elispm::lib('data/pmclass.class.php');

define ('ENGINETABLE', 'crlm_results');

class resultsengine extends elis_data_object {
    const TABLE = ENGINETABLE;
    const LANG_FILE = 'elis_program';

    static public $_unset = -1;

    private $form_url = null;  //moodle_url object

    protected $_dbfield_id;
    protected $_dbfield_contextid;
    protected $_dbfield_active;
    protected $_dbfield_eventtriggertype;
    protected $_dbfield_lockedgrade;
    protected $_dbfield_triggerstartdate;
    protected $_dbfield_days;
    protected $_dbfield_criteriatype;

    /**
     * Perform parent add
     */
    public function save() {
        parent::save();
    }

    /**
     * Perform parent delete
    */
    public function delete() {
        parent::delete();
    }

    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }
}

class resultsengineaction extends elis_data_object {
    const TABLE = 'crlm_results_action';
    const LANG_FILE = 'elis_program';

    static public $_unset = -1;

    private $form_url = null;  //moodle_url object

    protected $_dbfield_id;
    protected $_dbfield_resultsid;
    protected $_dbfield_actiontype;
    protected $_dbfield_minimum;
    protected $_dbfield_maximum;
    protected $_dbfield_trackid;
    protected $_dbfield_classid;
    protected $_dbfield_fieldid;
    protected $_dbfield_fielddata;

    /**
     * Perform parent add
     */
    public function save() {
        parent::save();
    }

    /**
     * Perform parent delete
    */
    public function delete() {
        parent::delete();
    }

    public function set_from_data($data, $override = true, $field_map = null) {
        $this->_load_data_from_record($data, $override, $field_map);
    }
}