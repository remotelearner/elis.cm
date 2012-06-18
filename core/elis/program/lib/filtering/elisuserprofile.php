<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis-programmanagement
 * @subpackage filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/elis/core/lib/filtering/multifilter.php');

/**
 * Filter that handles filtering on ELIS core user information, as well as ELIS
 * user-level custom field data
 */
class generalized_filter_elisuserprofile extends generalized_filter_multifilter {
    /**
     * Class contants: Additional required sub-filter types
     */

    //this maps entities to their appropriate database tables
    var $tables = array(
        'up' => array(
            'crlm_user'            => 'u',
            'elis_field_data_char' => 'updc',
            'elis_field_data_int'  => 'updi',
            'elis_field_data_num'  => 'updn',
            'elis_field_data_text' => 'updt',
        )
    );

    //this maps fields to their data types
    var $fieldtofiltermap = array(
        'up' => array('fullname' => self::filtertypetext,
                      'lastname' => self::filtertypetext,
                      'firstname'=> self::filtertypetext,
                      'idnumber' => self::filtertypetext,
                      'email'    => self::filtertypetext,
                      'city'     => self::filtertypetext,
                      'country'  => self::filtertypecountry,
                      'username' => self::filtertypetext,
                      'language' => self::filtertypeselect,
                      'inactive' => self::filtertypetristate,
                      )
    );

    //this maps fields to the language strings that define their labels
    var $labels = array(
        'up' => array('fullname'  => 'fld_fullname',
                      'lastname'  => 'fld_lastname',
                      'firstname' => 'fld_firstname',
                      'idnumber'  => 'fld_idnumber',
                      'email'     => 'fld_email',
                      'city'      => 'fld_city',
                      'country'   => 'fld_country',
                      'username'  => 'fld_username',
                      'language'  => 'fld_lang',
                      'inactive'  => 'fld_inactive'
        ),
        //'custom' => array()
    );

    //this defines the different sections of the report
    protected $sections = array(
        'up' => array('name' => 'user'), // User profile
        //'cust' => array('name' => 'user'), // Custom field data
    );

    //this defines the main language file
    protected $languagefile = 'elis_core';

    protected $_fieldids = array();

    /**
     * Constructor
     *
     * @param string $uniqueid Unique id for filter
     * @param string $label    Filter label
     * @param array  $options  Filter options (see above)
     * @return array of sub-filters
     */
    function generalized_filter_elisuserprofile($uniqueid, $label, $options = array()) {
        parent::__construct($uniqueid, $label, $options);

        foreach ($this->tables as $key => $val) {
            foreach ($val as $table => $alias) {
                if (empty($options['tables'][$key][$table])) {
                    //use defaults table aliases since not specified
                    $options['tables'][$key][$table] = $alias;
                }
            }
        }

        //default help setup
        if (empty($options['help'])) {
            $options['help'] = array();
        }

        $this->_filters = array();
        foreach ($this->_fields as $group => $fields) {
            $this->_filters[$group] = array();
            foreach ($fields as $userfield => $fieldlabel) {
                //error_log("elisuserprofile.php: creating filter for {$userfield} => {$fieldlabel}");
                if ($fieldlabel instanceof field) {
                    $fieldlabel = $fieldlabel->name;
                }

                // must setup select choices for specific fields
                $myoptions = $this->make_filter_options($group, $userfield, $options['help']);
                $filterid = $uniqueid . substr($userfield, 0, MAX_FILTER_SUFFIX_LEN);
                $ftype = (string)$this->fieldtofiltermap[$group][$userfield];
                $advanced = (!empty($options['advanced']) &&
                             in_array($userfield, $options['advanced']))
                            || (!empty($options['notadvanced']) &&
                                !in_array($userfield, $options['notadvanced']));
                //error_log("elisuserprofile.php: creating filter using: new generalized_filter_entry( $filterid, {$myoptions['talias']}, {$myoptions['dbfield']}, $fieldlabel, $advanced, $ftype, myoptions)");
                // Create the filter
                $this->_filters[$group][$userfield] =
                    new generalized_filter_entry($filterid, $myoptions['talias'], $myoptions['dbfield'],
                        $fieldlabel, $advanced, $ftype, $myoptions);
            }
        }

    }

    /**
     * Make Custom Filter Options
     *
     * This function handles filters that require custom values (languages, countries, etc).
     *
     * @param string $group  The index of the group to which the sub filter belongs to.
     * @param string $name   The name of the sub filter to process.
     * @param array  $help   An array representing the help icon for the filter
     * @uses  $DB
     * @return array The customized options for the selected sub-filter
     */
    function make_filter_options_custom($options, $group, $name) {
        global $DB;

        $options['tables'] = $this->tables[$group]; // TBD: default?
        $options['dbfield'] = $name; // TBD: default?
        if (isset($this->tables[$group]['crlm_user'])) {
            $options['talias'] = $this->tables[$group]['crlm_user']; // default table?
        } else {
            $options['talias'] = ''; // TBD???
            error_log("elisuserprofile::make_filter_options_custom(options, $group, $name) ... setting 'talias' empty!");
        }

        switch ($name) {
            case 'fullname':
                //combine the firstname and lastname into a fullname field
                $firstname = $this->tables[$group]['crlm_user'] .'.firstname';
                $lastname  = $this->tables[$group]['crlm_user'] .'.lastname';
                $options['dbfield'] = $DB->sql_concat($firstname, "' '", $lastname);
                $options['talias'] = '';
                //todo: find a better way to do this
                $this->fieldtofiltermap[$group][$options['dbfield']] =
                        generalized_filter_elisuserprofile::filtertypetext;
                break;
            case 'country':
                //populate dropdown entries for countries
                $countries = get_string_manager()->get_list_of_countries();
                $options['choices'] = $countries;
                break;
            case 'language':
                //populate dropdown entries for languages
                $languages = get_string_manager()->get_list_of_translations(true);
                $options['choices'] = $languages;
                break;
            case 'inactive':
                //populate dropdown entries for inactive flag filtering options
                $options['choices'] = array('0' => get_string('no'),
                                             1 => get_string('yes'));
                $options['numeric'] = 1;
                break;
        }

        if (array_key_exists($name, $this->_choices)) {
            $options['choices'] = $this->_choices[$name];
        }
        if (array_key_exists($name, $this->_fieldids)) {
            $options['fieldid'] = $this->_fieldids[$name];
        }

        $pos = strpos($name, 'customfield-');
        if ($pos !== false) {
            $options['contextlevel'] = CONTEXT_ELIS_USER;
        }

        return $options;
    }
}

