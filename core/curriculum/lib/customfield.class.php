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

require_once CURMAN_DIRLOCATION . '/lib/datarecord.class.php';

define ('FIELDTABLE', 'crlm_field');
define ('FIELDOWNERTABLE', 'crlm_field_owner');
define ('FIELDCATEGORYTABLE', 'crlm_field_category');
define ('FIELDDATATABLE', 'crlm_field_data');
define ('FIELDCONTEXTTABLE', 'crlm_field_contextlevel');
define ('FIELDCATEGORYCONTEXTTABLE', 'crlm_field_category_context');

/**
 * Custom fields.
 */
class field extends datarecord {
    const checkbox = 'checkbox';
    const menu = 'menu';
    const text = 'text';
    const textarea = 'textarea';

    function field($data=false) {
        parent::datarecord();

        $this->set_table(FIELDTABLE);
        $this->add_property('id', 'int');
        $this->add_property('shortname', 'string');
        $this->add_property('name', 'string');
        $this->add_property('datatype', 'string');
        $this->add_property('description', 'string');
        $this->add_property('categoryid', 'int');
        $this->add_property('sortorder', 'int');
        $this->add_property('forceunique', 'int');
        $this->add_property('multivalued', 'int');
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
            $params = unserialize($this->params);
            return $params[$paramname];
        }
        if ($name == 'owners') {
            $this->owners = field_owner::get_for_field($this);
            return $this->owners;
        }

        $trace = debug_backtrace();
        trigger_error("Undefined property via __get(): $name in {$trace[0]['file']} on line {$trace[0]['line']}",
                      E_USER_NOTICE);
        return null;
    }

    function __set($name, $value) {
        if (strncmp($name,'param_',6) == 0) {
            $paramname = substr($name,6);
            $params = unserialize($this->params);
            $params[$paramname] = $value;
            $this->params = serialize($params);
        } else {
            $this->$name = $value;
        }
    }

    function __isset($name) {
        if (strncmp($name,'param_',6) == 0) {
            $paramname = substr($name,6);
            $params = unserialize($this->params);
            return isset($params[$paramname]);
        } elseif ($name == 'owners') {
            $this->owners = field_owner::get_for_field($this);
            return $this->owners;
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

    function to_array() {
        $arr = (array)$this;
        foreach (unserialize($this->params) as $key => $value) {
            $arr["param_$key"] = $value;
        }
        return $arr;
    }

    function delete() {
        $result = parent::delete();
        // FIXME: delete all field data associated with this field
        return $result;
    }

    /**
     * Gets the custom field types, along with their categories, for a given
     * context level.
     */
    static function get_for_context_level($contextlevel) {
        global $CURMAN;
        if (!$contextlevel) {
            return array();
        }
        if (!is_numeric($contextlevel)) {
            $contextlevel = context_level_base::get_custom_context_level($contextlevel, 'block_curr_admin');
        }
        if ($contextlevel == context_level_base::get_custom_context_level('user', 'block_curr_admin')) {
            $sql = "SELECT field.*, category.name AS categoryname, mfield.id AS mfieldid, owner.exclude AS syncwithmoodle
                      FROM {$CURMAN->db->prefix_table(FIELDTABLE)} field
                 LEFT JOIN {$CURMAN->db->prefix_table('user_info_field')} mfield ON field.shortname = mfield.shortname
                 LEFT JOIN {$CURMAN->db->prefix_table(FIELDCATEGORYTABLE)} category ON field.categoryid = category.id
                 LEFT JOIN {$CURMAN->db->prefix_table(FIELDOWNERTABLE)} owner ON field.id = owner.fieldid AND owner.plugin = 'moodle_profile'
                      JOIN {$CURMAN->db->prefix_table(FIELDCONTEXTTABLE)} ctx ON ctx.fieldid = field.id AND ctx.contextlevel = {$contextlevel}
                  ORDER BY category.sortorder, field.sortorder";
        } else {
            $sql = "SELECT field.*, category.name AS categoryname
                      FROM {$CURMAN->db->prefix_table(FIELDTABLE)} field
                 LEFT JOIN {$CURMAN->db->prefix_table(FIELDCATEGORYTABLE)} category ON field.categoryid = category.id
                      JOIN {$CURMAN->db->prefix_table(FIELDCONTEXTTABLE)} ctx ON ctx.fieldid = field.id AND ctx.contextlevel = {$contextlevel}
                  ORDER BY category.sortorder, field.sortorder";
        }
        return $CURMAN->db->get_records_sql($sql);
    }

    static function get_for_context_level_with_name($contextlevel, $name) {
        global $CURMAN;
        if (!$contextlevel) {
            return false;
        }
        if (!is_numeric($contextlevel)) {
            $contextlevel = context_level_base::get_custom_context_level($contextlevel, 'block_curr_admin');
        }
        $select = "id IN (SELECT fctx.fieldid
                            FROM {$CURMAN->db->prefix_table(FIELDCONTEXTTABLE)} fctx
                           WHERE fctx.contextlevel = {$contextlevel})
               AND shortname='$name'";
        return $CURMAN->db->get_record_select(FIELDTABLE, $select);
    }

    function data_type() {
        global $CURMAN;
        switch ($this->datatype) {
        case 'int':
        case 'bool':
            return 'int';
            break;
        case 'num':
            return 'num';
            break;
        case 'char':
            return 'char';
            break;
        default:
            return 'text';
        }
    }

    function data_table() {
        return FIELDDATATABLE.'_'.$this->data_type();
    }

    /**
     * Makes sure that a custom field (identified by $field->shortname) exists
     * for the given context level.  If not, it will create a field, putting it
     * in the given category (identified by $category->name), creating it if
     * necessary.
     *
     * @param object a field object, specifying the field configuration if a
     * new field is created
     * @param mixed the context level
     * @param object a field_category object, specifying the category
     * configuration if a new category is created
     * @return object a field object
     */
    static function ensure_field_exists_for_context_level($field, $ctx_lvl, $category) {
        if (!is_numeric($ctx_lvl)) {
            $ctx_lvl = context_level_base::get_custom_context_level($ctx_lvl, 'block_curr_admin');
        }

        // see if we need to create a new field
        $fields = field::get_for_context_level($ctx_lvl);
        if (!empty($fields)) {
            foreach ($fields as $f) {
                if ($f->shortname === $field->shortname) {
                    return new field($f);
                }
            }
        }

        // No existing field found.  See if we need to create a category for it
        $categories = field_category::get_for_context_level($ctx_lvl);
        $found = false;
        if (!empty($categories)) {
            foreach ($categories as $c) {
                if ($c->name === $category->name) {
                    $category = $found = $c;
                    break;
                }
            }
        }
        if (!$found) {
            // create the category
            $category->add();
            $categorycontext = new field_category_contextlevel();
            $categorycontext->categoryid = $category->id;
            $categorycontext->contextlevel = $ctx_lvl;
            $categorycontext->add();
        }

        // create the field
        $field->categoryid = $category->id;
        $field->add();
        $fieldcontext = new field_contextlevel();
        $fieldcontext->fieldid = $field->id;
        $fieldcontext->contextlevel = $ctx_lvl;
        $fieldcontext->add();

        return $field;
    }

}

/**
 * Field owners.
 */
class field_owner extends datarecord {
    function field_owner($data=false) {
        parent::datarecord();

        $this->set_table(FIELDOWNERTABLE);
        $this->add_property('id', 'int');
        $this->add_property('fieldid', 'int');
        $this->add_property('plugin', 'string');
        $this->add_property('exclude', 'int');
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
            $params = unserialize($this->params);
            return $params[$paramname];
        }

        $trace = debug_backtrace();
        trigger_error("Undefined property via __get(): $name in {$trace[0]['file']} on line {$trace[0]['line']}",
                      E_USER_NOTICE);
        return null;
    }

    function __set($name, $value) {
        if (strncmp($name,'param_',6) == 0) {
            $paramname = substr($name,6);
            $params = unserialize($this->params);
            $params[$paramname] = $value;
            $this->params = serialize($params);
        } else {
            $this->$name = $value;
        }
    }

    function __isset($name) {
        if (strncmp($name,'param_',6) == 0) {
            $paramname = substr($name,6);
            $params = unserialize($this->params);
            return isset($params[$paramname]);
        } else {
            return false;
        }
    }

    static function get_for_field($field) {
        global $CURMAN;
        return $CURMAN->db->get_records(FIELDOWNERTABLE, 'fieldid', $field->id, '', 'plugin, id, fieldid, exclude, params');
    }

    /**
     * Creates the owner record corresponding to the supplied field if it does not already exist
     *
     * @param   field   $field   The field to create the owner for
     * @param   string  $plugin  The plugin used for the owner field
     * @param   array   $params  Any additional parameters to pass to the owner record
     */
    static function ensure_field_owner_exists($field, $plugin, $params = array()) {
        $owners = $field->owners;
        if (!empty($owners[$plugin])) {
            return;
        }

        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = $plugin;
        $owner->params = serialize($params);
        $owner->add();

    }
}

/**
 * Field categories.
 */
class field_category extends datarecord {
    function field_category($data=false) {
        parent::datarecord();

        $this->set_table(FIELDCATEGORYTABLE);
        $this->add_property('id', 'int');
        $this->add_property('name', 'string');
        $this->add_property('sortorder', 'int');

        if (is_numeric($data) || is_string($data)) {
            $this->data_load_record($data);
        } else if (is_array($data)) {
            $this->data_load_array($data);
        } else if (is_object($data)) {
            $this->data_load_array(get_object_vars($data));
        }
    }

    static function get_all() {
        global $CURMAN;
        return $CURMAN->db->get_records(FIELDCATEGORYTABLE, '', '', 'sortorder');
    }

    /**
     * Gets the custom field categories for a given context level.
     */
    static function get_for_context_level($contextlevel) {
        global $CURMAN;
        if (!$contextlevel) {
            return array();
        }
        if (!is_numeric($contextlevel)) {
            $contextlevel = context_level_base::get_custom_context_level($contextlevel, 'block_curr_admin');
        }
        $sql = "SELECT category.*
                  FROM {$CURMAN->db->prefix_table(FIELDCATEGORYTABLE)} category
                  JOIN {$CURMAN->db->prefix_table(FIELDCATEGORYCONTEXTTABLE)} ctx ON ctx.categoryid = category.id AND ctx.contextlevel = {$contextlevel}
              ORDER BY category.sortorder";
        return $CURMAN->db->get_records_sql($sql);
    }

    function delete() {
        $result = parent::delete();
        // FIXME: delete all fields associated with this category
        return $result;
    }

}

/**
 * Field data.
 */
class field_data extends datarecord {
    function field_data($data=false, $type='text') {
        parent::datarecord();

        $this->set_table(FIELDDATATABLE.'_'.$type);
        $this->add_property('id', 'int');
        $this->add_property('contextid', 'int');
        $this->add_property('fieldid', 'int');
        $this->add_property('plugin', 'string');
        switch ($type) {
        case 'int':
            $this->add_property('data', 'int');
            break;
        case 'num':
            $this->add_property('data', 'float');
            break;
        case 'text':
            $this->add_property('data', 'html');
            break;
        default:
            $this->add_property('data', 'string');
            break;
        }

        if (is_numeric($data) || is_string($data)) {
            $this->data_load_record($data);
        } else if (is_array($data)) {
            $this->data_load_array($data);
        } else if (is_object($data)) {
            $this->data_load_array(get_object_vars($data));
        }
    }

    /**
     * Gets the custom field data, along with their categories, for a given
     * context.  If a field value is not set, the default value will be given,
     * and the data id will be null.
     *
     * @return array An array with items of the form fieldshortname => value,
     * where value is an array if the field is multivalued, or a single value
     * if not.
     */
    static function get_for_context($context) {
        global $CURMAN;

        // find out which fields we have, and what tables to look for the values
        // in
        $fields = field::get_for_context_level($context->contextlevel);
        $fields = $fields ? $fields : array();
        $data_tables = array();
        foreach ($fields as $field) {
            $field = $fields[$field->id] = new field($field);
            $data_tables[$field->data_table()] = true;
        }

        // load the values from the database, and sort them into the fields
        $values = array();
        $default_values = array();
        foreach ($data_tables as $table => $unused) {
            $records = $CURMAN->db->get_records_select($table, "contextid = {$context->id} OR contextid IS NULL", 'id');
            $records = $records ? $records : array();
            foreach ($records as $record) {
                if (!isset($fields[$record->fieldid]) || $fields[$record->fieldid]->data_table() != $table) {
                    // nonexistent field, or this data isn't supposed to come from this table
                    continue;
                }
                if ($record->contextid) {
                    if (!isset($values[$record->fieldid])) {
                        $values[$record->fieldid] = array();
                    }
                    $values[$record->fieldid][] = $record->data;
                } else {
                    if (!isset($default_values[$record->fieldid])) {
                        $default_values[$record->fieldid] = array();
                    }
                    $default_values[$record->fieldid][] = $record->data;
                }
            }
        }

        // create the final result
        $result = array();
        foreach ($fields as $field) {
            // If multivalued, copy the whole array; otherwise just copy the
            // first value.  If a value for the context is set, then use that
            // value; otherwise use the default value.
            if ($field->multivalued) {
                if (!empty($values[$field->id])) {
                    $result[$field->shortname] = $values[$field->id];
                } elseif (!empty($default_values[$field->id])) {
                    $result[$field->shortname] = $default_values[$field->id];
                }
            } else {
                if (!empty($values[$field->id])) {
                    $result[$field->shortname] = $values[$field->id][0];
                } elseif (!empty($default_values[$field->id])) {
                    $result[$field->shortname] = $default_values[$field->id][0];
                }
            }
        }
        return $result;
    }

    /**
     * Gets the custom field data for a specified context and field.  If a
     * field value is not set, the default value will be given.
     */
    static function get_for_context_and_field($context, $field) {
        global $CURMAN;
        if (is_string($field)) {
            $field = addslashes($field);
            $field = new field("shortname = '$field'
                            AND id IN (SELECT fctx.fieldid
                                         FROM {$CURMAN->db->prefix_table(FIELDCONTEXTTABLE)} fctx
                                        WHERE fctx.contextlevel = {$context->contextlevel})");
        }
        $result = NULL;
        if ($context) {
            $result = $CURMAN->db->get_records_select($field->data_table(), "contextid = {$context->id} AND fieldid = {$field->id}");
        }
        if (empty($result)) {
            $result = $CURMAN->db->get_records_select($field->data_table(), "contextid IS NULL AND fieldid = {$field->id}");
        }
        return $result;
    }

    /**
     * Sets the custom field data for a specified context and field.
     *
     * @param object $context
     * @param object $field
     * @param mixed $data a single value or an array depending on whether
     *        $field is multivalued or not
     * @param string $plugin
     * @return boolean whether or not the data was modified
     */
    static function set_for_context_and_field($context, $field, $data) {
        global $CURMAN;
        if ($context) {
            $contextid = $context->id;
        } else {
            $contextid = NULL;
        }
        $data_table = $field->data_table();
        // FIXME: check exclude, unique, etc
        if ($field->multivalued) {
            $records = field_data::get_for_context_and_field($context, $field);
            $records = $records ? $records : array();
            $todelete = array();
            $toadd = array();
            $existing = array();
            foreach ($records as $rec) {
                if (in_array($rec->data, $data)) {
                    $existing[] = $rec->data;
                } else {
                    $todelete[] = $rec;
                }
            }
            if (is_array($data)) {
                $toadd = array_diff($data, $existing);
            }
            foreach ($todelete as $rec) {
                $CURMAN->db->delete_records($data_table, 'id', $rec->id);
            }
            foreach ($toadd as $value) {
                $rec = new field_data(false, $field->data_type());
                $rec->contextid = $contextid;
                $rec->fieldid = $field->id;
                $rec->data = $value;
                $rec->data_insert_record();
            }
            return !empty($toadd) || !empty($todelete);
        } else {
            if (($rec = $CURMAN->db->get_record($data_table, 'contextid', $contextid, 'fieldid', $field->id))) {
                $fielddata = new field_data($rec, $field->data_type());
                if ($data === NULL) {
                    $fielddata->delete();
                    return true;
                }
                if (addslashes($fielddata->data) == $data) {
                    return false;
                }
                $fielddata->contextid = $contextid; // needed, or else NULL becomes 0
                $fielddata->data = $data;
                $fielddata->update();
                return true;
            } elseif ($data !== NULL) {
                $rec = new field_data(false, $field->data_type());
                $rec->contextid = $contextid;
                $rec->fieldid = $field->id;
                $rec->data = $data;
                $rec->add();
                return true;
            }
        }
    }


    /**
     * Convenience function for use by datarecord objects
     */
    function set_for_context_from_datarecord($level, $record) {
        global $CURMAN;

        $contextlevel = context_level_base::get_custom_context_level($level, 'block_curr_admin');
        if (!$contextlevel) {
            // context levels not set up -- we must be in initial installation,
            // so no fields set up
            return true;
        }
        $context = get_context_instance($contextlevel, $record->id);
        $fields = field::get_for_context_level($contextlevel);
        $fields = $fields ? $fields : array();
        foreach ($fields as $field) {
            $fieldname = "field_{$field->shortname}";
            if (isset($record->$fieldname)) {
                field_data::set_for_context_and_field($context, new field($field), $record->$fieldname);
            }
        }

        return true;
    }
}

/**
 * Which contexts a field applies to.
 */
class field_contextlevel extends datarecord {
    function field_contextlevel($data=false) {
        parent::datarecord();

        $this->set_table(FIELDCONTEXTTABLE);
        $this->add_property('id', 'int');
        $this->add_property('fieldid', 'int');
        $this->add_property('contextlevel', 'int');

        if (is_numeric($data) || is_string($data)) {
            $this->data_load_record($data);
        } else if (is_array($data)) {
            $this->data_load_array($data);
        } else if (is_object($data)) {
            $this->data_load_array(get_object_vars($data));
        }
    }
}

/**
 * Which contexts a field category applies to.
 */
class field_category_contextlevel extends datarecord {
    function field_category_contextlevel($data=false) {
        parent::datarecord();

        $this->set_table(FIELDCATEGORYCONTEXTTABLE);
        $this->add_property('id', 'int');
        $this->add_property('categoryid', 'int');
        $this->add_property('contextlevel', 'int');

        if (is_numeric($data) || is_string($data)) {
            $this->data_load_record($data);
        } else if (is_array($data)) {
            $this->data_load_array($data);
        } else if (is_object($data)) {
            $this->data_load_array(get_object_vars($data));
        }
    }
}


?>
