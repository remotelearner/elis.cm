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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once CURMAN_DIRLOCATION . '/lib/datarecord.class.php';

define ('CLUSTERCLASSTABLE', 'crlm_cluster_classification');

class clusterclassification extends datarecord {
    var $verbose_name = 'cluster classification';

    function clusterclassification($data = false) {
        parent::datarecord();

        $this->set_table(CLUSTERCLASSTABLE);
        $this->add_property('id', 'int');
        $this->add_property('shortname', 'string');
        $this->add_property('name', 'string');
        $this->add_property('params', 'string');

        if (is_numeric($data) || is_string($data)) {
            $this->data_load_record($data);
        } else if (is_array($data)) {
            $this->data_load_array($data);
        } else if (is_object($data)) {
            $this->data_load_array(get_object_vars($data));
        }

        if (empty($this->params)) {
            $this->params = serialize(array());
        }
    }

    /* get and set parameter values */
    function __get($name) {
        if (strncmp($name,'param_',6) == 0) {
            $paramname = substr($name,6);
            $params = unserialize(stripslashes($this->params));
            return isset($params[$paramname]) ? $params[$paramname] : NULL;
        }

        $trace = debug_backtrace();
        trigger_error("Undefined property via __get(): $name in {$trace[0]['file']} on line {$trace[0]['line']}",
                      E_USER_NOTICE);
        return null;
    }

    function __set($name, $value) {
        $this->$name = $value;
        if (strncmp($name,'param_',6) == 0) {
            $paramname = substr($name,6);
            $params = unserialize(stripslashes($this->params));
            $params[$paramname] = $value;
            $this->params = addslashes(serialize($params));
        }
    }

    function __isset($name) {
        if (strncmp($name,'param_',6) == 0) {
            $paramname = substr($name,6);
            $params = unserialize(stripslashes($this->params));
            return isset($params[$paramname]);
        } else {
            return false;
        }
    }

    function data_load_array($data) {
        if (!parent::data_load_array($data)) {
            return false;
        }

        foreach ($data as $key => $value) {
            if (strncmp($key,'param_',6) === 0) {
                $this->$key = $value;
            }
        }

        return true;
    }

    public function set_from_data($data) {
        $fields = array('autoenrol_curricula', 'autoenrol_tracks', 'child_classification');
        foreach ($fields as $field) {
            $fieldname = "param_{$field}";
            if (isset($data->$fieldname)) {
                $this->$fieldname = $data->$fieldname;
            }
        }

        return parent::set_from_data($data);
    }

    public function to_string() {
        return $this->name;
    }

    function to_array() {
        $arr = (array)$this;
        foreach (unserialize($this->params) as $key => $value) {
            $arr["param_$key"] = $value;
        }
        return $arr;
    }

    static function get_for_cluster($cluster) {
        require_once CURMAN_DIRLOCATION . '/lib/customfield.class.php';
        require_once CURMAN_DIRLOCATION . '/plugins/cluster_classification/lib.php';

        if (is_object($cluster)) {
            $cluster = $cluster->id;
        }

        $context = get_context_instance(context_level_base::get_custom_context_level('cluster', 'block_curr_admin'), $cluster);
        $value = field_data::get_for_context_and_field($context, CLUSTER_CLASSIFICATION_FIELD);
        if (!empty($value)) {
            $value = array_shift($value);
            $name = addslashes($value->data);
            return new clusterclassification("shortname = '$name'");
        }
    }
}

?>
