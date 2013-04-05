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
 * @package    elis-program
 * @subpackage filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once($CFG->dirroot.'/elis/core/lib/filtering/autocomplete_base.php');

class generalized_filter_autocomplete_eliswithcustomfields extends generalized_filter_autocomplete_base {
    protected $contextid;

    protected $context_level_map = array(
        1001 => 'curriculum',
        1002 => 'track',
        1003 => 'course',
        1004 => 'class',
        1005 => 'user',
        1006 => 'cluster'
    );

    protected $custom_fields_data_tables = array(
        'char' => 'elis_field_data_char',
        'text' => 'elis_field_data_text'
    );
    protected $custom_fields = array();
    protected $instance_fields = array();
    protected $forced_custom_vals = array();

    /**
     * Loads the $options array into class properties
     * Possible options (these options are in addition to the standard autocomplete options
     *
     * contextlevel               The ELIS context level to search.possible values: 1001-1006
     *
     * instance fields            A list of fields from the instance table to search on. This should be an array like
     *                            (fieldname => label). For example array('idnumber'=>'IDNumber','firstname'=>'First Name')
     *
     * forced_custom_field_vals   An array like field_shortname => value that can be used to restrict results to
     *                            ones that have specific values for specific custom fields. Note that any fields
     *                            you enter here MUST be valid fields for the specified context level, and must be
     *                            included in the 'custom_fields' option (or have the 'custom_fields' set to '*')
     *
     * custom_fields              A list of the shortnames of custom fields to allow for searching. Note that this is NOT
     *                            the fields which will end up being searched/displayed - but those available for searching.
     *                            The actual list of fields to search/display is configured by an administration using
     *                            the configuration interface.
     *                            This can be an array of field shortnames, or '*' for all applicable fields.
     *
     * @param  array  $options   The options array passed into the class
     */
    public function load_options($options) {
        global $CFG, $DB;

        //instance fields - fields to search from the instance's table
        if (!isset($options['instance_fields'])) {
            print_error('autocomplete_noinstance', 'elis_program');
        }
        $this->instance_fields = $options['instance_fields'];

        //get instance contextid
        if (!isset($options['contextlevel']) || !isset($this->context_level_map[$options['contextlevel']])) {
            print_error('autocomplete_nocontext', 'elis_program');
        }
        $this->instancetable = 'crlm_'.$this->context_level_map[$options['contextlevel']];
        $this->contextlevel = $options['contextlevel'];

        if (!empty($options['forced_custom_field_vals']) && is_array($options['forced_custom_field_vals'])) {
            $this->forced_custom_vals = $options['forced_custom_field_vals'];
        }

        //fetch info for custom fields
        if (!empty($options['custom_fields']) && (is_array($options['custom_fields']) || $options['custom_fields'] === '*')) {

            $sql = 'SELECT f.id, f.name, f.shortname, f.datatype '
                .'FROM {elis_field} f'
                .' JOIN {elis_field_contextlevels} c ON c.fieldid=f.id'
                .' WHERE (f.datatype="char" OR f.datatype="text") AND c.contextlevel='.$options['contextlevel'];
            if (is_array($options['custom_fields'])) {
                $ids = implode('","',$options['custom_fields']);
                $sql .= ' AND f.shortname IN ("'.$ids.'")';
            }

            $custom_fields = $DB->get_records_sql($sql);
            if (is_array($custom_fields)) {
                foreach ($custom_fields as $field) {
                    $field_info = array(
                        'fieldid' => $field->id,
                        'label' => $field->name,
                        'shortname' => $field->shortname,
                        'datatype' => $field->datatype
                    );

                    $this->custom_fields[$field->shortname] = $field_info;
                }
            }
        }
    }


    /**
     * Allows configuring which instance and custom fields are used in search.
     * @return  \autocomplete_eliswithcustomfields_config  Instance of the config form
     */
    public function get_config_form() {
        $customdata = array(
            'config' => filt_autoc_get_config($this->_parent_report,$this->_uniqueid),
            'instance_fields' => $this->instance_fields,
            'custom_fields' => $this->custom_fields
        );

        return new autocomplete_eliswithcustomfields_config(qualified_me(),$customdata);
    }

    /**
     * Processes incoming data from the config form.
     * This ensures that if a field is set to be searched, it also is displayed.
     * Also cleans up the data so that if a field is not searched or displayed, it is removed from the config data
     * @param   stdClass  $configdata Data from the form
     * @return  stdClass  The modified data.
     */
    public function process_config_data($configdata) {
        unset($configdata->MAX_FILE_SIZE,$configdata->submitbutton);

        $configdata = (array)$configdata;
        foreach ($configdata as $table => $fields) {
            foreach ($fields as $field => $opts) {
                foreach ($opts as $type => $val) {
                    $configdata[$table][$field][$type] = (int)$val;
                }
                if (!empty($opts['search'])) {
                    $configdata[$table][$field]['disp'] = 1;
                }
                if (empty($opts['search']) && empty($opts['disp'])) {
                    unset($configdata[$table][$field]);
                }
            }
        }

        return $configdata;
    }

    /**
     * Gets the labels for each column of the results table.
     * @return  array  An array of strings with values in the same order as $this->get_results_fields();
     */
    public function get_results_headers() {
        $headers = array_values($this->get_display_instance_fields());

        $enabled_fields = $this->get_display_custom_fields();
        foreach ($enabled_fields as $field_info) {
            $headers[] = $field_info['label'];
        }

        return $headers;
    }

    /**
     * Gets the fields for each column of the results table.
     * @return  array  An array of strings corresponding to members of a SQL result row with values
     *                  in the same order as $this->get_results_headers();
     */
    public function get_results_fields() {
        $fields = array_keys($this->get_display_instance_fields());

        $enabled_fields = $this->get_display_custom_fields();
        foreach ($enabled_fields as $field_info) {
            $fields[] = strtolower($field_info['shortname']);
        }

        return $fields;
    }

    /**
     * Get instance that have been enabled for display.
     * @return array A slice of $this->instance_fields corresponding to fields set to be displayed
     */
    public function get_display_instance_fields() {
        $config = filt_autoc_get_config($this->_parent_report,$this->_uniqueid);
        $enabled_instance_fields = array();
        if (!empty($config['instance']) && is_array($config['instance'])) {
            foreach ($config['instance'] as $field => $opts) {
                if (isset($this->instance_fields[$field]) && !empty($opts['disp'])) {
                    $enabled_instance_fields[$field] = $this->instance_fields[$field];
                }
            }
        }
        $label_fields = $this->get_label_fields();
        $instance_label_fields = array_intersect_key($this->instance_fields,array_flip($label_fields));

        $enabled_instance_fields = array_unique(array_merge($enabled_instance_fields,$instance_label_fields));
        return $enabled_instance_fields;
    }

    /**
     * Get instance that have been enabled for search.
     * @return array A slice of $this->instance_fields corresponding to fields set to be searched
     */
    public function get_search_instance_fields() {
        $config = filt_autoc_get_config($this->_parent_report,$this->_uniqueid);
        $enabled_instance_fields = array();
        if (!empty($config['instance']) && is_array($config['instance'])) {
            foreach ($config['instance'] as $field => $opts) {
                if (isset($this->instance_fields[$field]) && !empty($opts['search'])) {
                    $enabled_instance_fields[$field] = $this->instance_fields[$field];
                }
            }
        }
        return $enabled_instance_fields;
    }

    /**
     * Get custom fields that have been enabled for display
     * @return array A slice of $this->custom_fields corresponding to fields set to be displayed
     */
    public function get_display_custom_fields() {
        $config = filt_autoc_get_config($this->_parent_report,$this->_uniqueid);
        $enabled_custom_fields = array();
        if (!empty($config['custom_field']) && is_array($config['custom_field'])) {
            foreach ($config['custom_field'] as $field => $opts) {
                if (isset($this->custom_fields[$field]) && !empty($opts['disp'])) {
                    $enabled_custom_fields[$field] = $this->custom_fields[$field];
                }
            }
        }

        $label_fields = $this->get_label_fields();
        $custom_field_label_fields = array_intersect_key($this->custom_fields,array_flip($label_fields));

        $enabled_custom_fields = array_merge($enabled_custom_fields,$custom_field_label_fields);
        return $enabled_custom_fields;
    }

    public function get_configured_forced_custom_vals() {
        $config = filt_autoc_get_config($this->_parent_report,$this->_uniqueid);
        $forced_custom_fields = array();
        if (!empty($config['custom_field']) && is_array($config['custom_field'])) {
            foreach ($config['custom_field'] as $field => $opts) {
                if (isset($this->custom_fields[$field]) && !empty($opts['restrict'])) {
                    $forced_custom_fields[$field] = $this->custom_fields[$field];
                }
            }
        }
        return $forced_custom_fields;
    }

    /**
     * Get custom fields that have been enabled for search
     * @return array A slice of $this->custom_fields corresponding to fields set to be searched
     */
    public function get_search_custom_fields() {
        $config = filt_autoc_get_config($this->_parent_report,$this->_uniqueid);
        $enabled_custom_fields = array();
        if (!empty($config['custom_field']) && is_array($config['custom_field'])) {
            foreach ($config['custom_field'] as $field => $opts) {
                if (isset($this->custom_fields[$field]) && !empty($opts['search'])) {
                    $enabled_custom_fields[$field] = $this->custom_fields[$field];
                }
            }
        }
        return $enabled_custom_fields;
    }

    /**
     * Gets the autocomplete search SQL for the autocomplete UI
     * Note that this is the SQL used to select a value, not the SQL used in the report SQL
     * @param string $q The query string
     * @return string The SQL query
     */
    public function get_search_results($q) {
        global $CFG, $DB, $USER;

        $q = explode(' ',$q);


        //get enabled instance and custom fields
        $display_instance_fields = $this->get_display_instance_fields();
        $search_instance_fields = $this->get_search_instance_fields();

        $display_custom_fields = $this->get_display_custom_fields();
        $search_custom_fields = $this->get_search_custom_fields();

        $configured_forced_custom_vals = $this->get_configured_forced_custom_vals();

        if (empty($search_custom_fields) && empty($search_instance_fields)) {
            echo get_string('filt_autoc_no_fields_enabled','elis_core');
            die();
        }

        //assemble SELECT
        $select = array('i.id');
        foreach ($display_instance_fields as $field => $label) {
            $select[] = 'i.'.$field;
        }
        foreach ($display_custom_fields as $field_info) {
            $select[] = 'f'.$field_info['fieldid'].'.data as '.strtolower($field_info['shortname']);
        }
        $select = 'SELECT '.implode(',',$select);


        //assemble FROM/JOINs
        $joins = array(
            'JOIN {context} c ON c.instanceid=i.id AND c.contextlevel='.$this->contextlevel
        );
        foreach ($display_custom_fields as $field_info) {
            $table = $this->custom_fields_data_tables[$field_info['datatype']];
            $alias = 'f'.$field_info['fieldid'];
            $joins[] = ' LEFT JOIN {'.$table.'} '.$alias.' ON '.$alias.'.contextid=c.id AND '.$alias.'.fieldid='.$field_info['fieldid'];
        }

        foreach ($this->forced_custom_vals as $field_shortname => $forced_val) {
            if (isset($this->custom_fields[$field_shortname]) && !isset($display_custom_fields[$field_shortname])) {
                $field_info = $this->custom_fields[$field_shortname];
                $table = $this->custom_fields_data_tables[$field_info['datatype']];
                $alias = 'f'.$field_info['fieldid'];
                $joins[] = ' LEFT JOIN {'.$table.'} '.$alias.' ON '.$alias.'.contextid=c.id AND '.$alias.'.fieldid='.$field_info['fieldid'];
            }
        }

        if ($this->config_allowed() !== true) {
            foreach ($configured_forced_custom_vals as $field_shortname => $field_info) {
                if (!isset($this->custom_fields[$field_shortname]) && !isset($display_custom_fields[$field_shortname])) {
                    $table = $this->custom_fields_data_tables[$field_info['datatype']];
                    $alias = 'f'.$field_info['fieldid'];
                    $joins[] = ' LEFT JOIN {'.$table.'} '.$alias.' ON '.$alias.'.contextid=c.id AND '.$alias.'.fieldid='.$field_info['fieldid'];
                }
            }
        }

        $from = 'FROM {'.$this->instancetable.'} i '.implode(' ',$joins);


        //assemble WHERE
        $search = array();
        $search_params = array();
        foreach ($q as $i => $q_word) {

            //add search SQL for each instance field
            $search_by_field = array();
            foreach ($search_instance_fields as $field => $label) {
                $search_by_field[] = 'i.'.$field.' LIKE ?';
                $search_params[] = '%'.$q_word.'%';
            }

            //add search SQL for each custom field
            foreach ($search_custom_fields as $field_info) {
                $search_by_field[] = 'f'.$field_info['fieldid'].'.data LIKE ?';
                $search_params[] = '%'.$q_word.'%';
            }

            $search[] = implode(' OR ',$search_by_field);
        }

        //get code-forced custom vals
        foreach ($this->forced_custom_vals as $field_shortname => $forced_val) {
            if (isset($this->custom_fields[$field_shortname])) {
                $field_info = $this->custom_fields[$field_shortname];
                $search[] = 'f'.$field_info['fieldid'].'.data = ?';
                $search_params[] = $forced_val;
            }
        }

        //get configured forced custom vals
        if ($this->config_allowed() !== true) {
            foreach ($configured_forced_custom_vals as $field_shortname => $field_info) {
                if (isset($USER->profile[$field_shortname])) {
                    $search[] = 'f'.$field_info['fieldid'].'.data = ?';
                    $search_params[] = $USER->profile[$field_shortname];
                }
            }
        }

        if (!empty($this->_restriction_sql)) {
            $search[] = $this->_restriction_sql;
        }

        //CONSTRUCT PERMISSIONS SQL FILTER
        $contextname = $this->context_level_map[$this->contextlevel];
        $perms_filter = array();
        $perm_params = array();

        // obtain all course contexts where this user can view reports

        $contexts = get_contexts_by_capability_for_user(
                $contextname,
                $this->parent_report_instance->access_capability,
                $this->parent_report_instance->userid
        );
        $filter_obj = $contexts->get_filter('id', $contextname);
        $filter_sql = $filter_obj->get_sql(false, '');

        if (isset($filter_sql['where'])) {
            $perms_filter[] = $filter_sql['where'];
            $perm_params = $filter_sql['where_parameters'];
        }

        if (!is_array($perm_params)) {
            $perm_params = array();
        }

        if ($contextname === 'user') {
            // ELIS-5807 -- Always be sure to include the user accessing the filter in the results!
            if ($cm_user_id = cm_get_crlmuserid($USER->id)) {
                $perms_filter[] = 'i.id = "'.$cm_user_id.'"';
            }
            /*if ($cm_user_id = cm_get_crlmuserid($USER->id)) {
                $wherestr .= ' OR {'.$found_filter->options['table'].'}.id = :self_user_id';
                $params   += array('self_user_id' => $cm_user_id);
            }
            */
        }

        if (empty($perms_filter)) {
            $search[] = '('.implode(') OR (',$perms_filter).')';
        }
        $where = 'WHERE ('.implode(') AND (',$search).')';

        //assemble + run the query
        $sql = $select.' '.$from.' '.$where.' GROUP BY i.id LIMIT 0,20';
        $params = array_merge($search_params,$perm_params);

        return $DB->get_records_sql($sql,$params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param   array   $data  Filter settings
     * @return  string         Active filter label
     */
    public function get_label($data) {
        if (!$this->_useid) {
            return parent::get_label($data);
        } else {
            $value = $data['value'];

            $a = new object();
            $a->label = $this->_label;
            if ($cmuser = new user($value)) {
                $cmuser->load();
                $value = fullname($cmuser->to_object());
            }
            $a->value = '"'.s($value).'"';
            $a->operator = get_string('isequalto','filters');

            return get_string('selectlabel', 'filters', $a);
        }
    }
}
