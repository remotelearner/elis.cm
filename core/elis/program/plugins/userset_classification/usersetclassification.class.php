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
require_once elis::lib('data/data_object_with_custom_fields.class.php');
require_once elispm::lib('contexts.php');

class usersetclassification extends elis_data_object {
    const TABLE = 'crlm_cluster_classification';

    protected $_dbfield_shortname;
    protected $_dbfield_name;
    protected $_dbfield_params;

    static $associations = array();

    var $verbose_name = 'userset_classification';

    function __get($name) {
        if (strncmp($name,'param_',6) == 0) {
            $paramname = substr($name,6);
            $params = unserialize($this->params);
            return isset($params[$paramname]) ? $params[$paramname] : NULL;
        } else {
            return parent::__get($name);
        }
    }

    function __set($name, $value) {
        if (strncmp($name,'param_',6) == 0) {
            $paramname = substr($name,6);
            $params = empty($this->params) ? array(): unserialize($this->params);
            $params[$paramname] = $value;
            $this->params = serialize($params);
        } else {
            parent::__set($name, $value);
        }
    }

    function __isset($name) {
        if (strncmp($name,'param_',6) == 0) {
            $paramname = substr($name,6);
            $params = unserialize($this->params);
            return isset($params[$paramname]);
        } else {
            return parent::__isset($name);
        }
    }

    public function set_from_data($data) {
        $fields = array('autoenrol_curricula', 'autoenrol_tracks', 'child_classification', 'autoenrol_groups', 'autoenrol_groupings', 'elis_files_shared_folder');
        foreach ($fields as $field) {
            $fieldname = "param_{$field}";
            if (isset($data->$fieldname)) {
                $this->$fieldname = $data->$fieldname;
            }
        }

        $this->_load_data_from_record($data, true);
    }

    public function __toString() {
        return $this->name;
    }

    function to_array() {
        $arr = (array)$this;
        foreach (unserialize($this->params) as $key => $value) {
            $arr["param_$key"] = $value;
        }
        return $arr;
    }

    /**
     * Add params fields to the form object
     */
    public function to_object() {
        $obj = parent::to_object();

        $fields = array('autoenrol_curricula', 'autoenrol_tracks', 'child_classification', 'autoenrol_groups', 'autoenrol_groupings', 'elis_files_shared_folder');
        foreach ($fields as $field) {
            $field_name = "param_{$field}";
            if (isset($this->$field_name)) {
                $obj->$field_name = $this->$field_name;
            }
        }
        return $obj;
    }

    function get_verbose_name() {
        return $this->verbose_name;
    }

    static function get_for_cluster($cluster) {
        require_once elis::lib('data/customfield.class.php');
        require_once elispm::file('plugins/userset_classification/lib.php');

        if (is_object($cluster)) {
            $cluster = $cluster->id;
        }

        $context = context_elis_userset::instance($cluster);
        $value = field_data::get_for_context_and_field($context, USERSET_CLASSIFICATION_FIELD);
        if (isset($value) && $value->valid()) {
            $value = $value->current();
            $name = $value->data;
            $newusersetclassification = usersetclassification::find(new field_filter('shortname', $name));
            return $newusersetclassification->current();
        } else {
            return false;
        }

    }
}
