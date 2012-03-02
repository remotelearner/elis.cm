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
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once($CFG->dirroot.'/elis/core/lib/filtering/multifilter.php');

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
            'crlm_field_data_char' => 'updc',
            'crlm_field_data_int'  => 'updi',
            'crlm_field_data_num'  => 'updn',
            'crlm_field_data_text' => 'updt',
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
        )
    );

    //this defines the different sections of the report
    protected $sections = array(
        'up' => array(), // User profile
        'cust' => array(), // Custom field data
    );

    //this defines the main language file
    protected $languagefile = 'elis_core';

    /**
     * Constructor
     *
     * @param string $uniqueid Unique id for filter
     * @param string $label    Filter label
     * @param array  $options  Filter options (see above)
     * @return array of sub-filters
     */
    function generalized_filter_elisuserprofile($uniqueid, $label, $options = array()) {
        parent::multifilter($uniqueid, $label, $options);

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

        // Get the custom fields that belong to the user context
        $ctxlvl = context_level_base::get_custom_context_level('user', 'block_curr_admin');

        $fields = field::get_for_context_level($ctxlvl);
        $fields = $fields ? $fields : array();
        $this->get_custom_fields('up', $fields);

        //create field listing, including display names
        $allfields = array();
        foreach ($options['choices'] as $choice) {
            $choicestring = $choice;

            if ( array_key_exists($choice, $this->labels['up']) ) {
                $choicestring = get_string($this->labels['up'][$choice], $this->languagefile);
            }

            if ( 0 == strcmp($choice, 'customfields') ) {
                foreach ($fields as $field) {
                    $allfields['customfield-'.$field->id] = $field->name;
                }
            } else {
                $allfields[$choice] = $choicestring;
            }
        }

        //add all fields
        foreach ($allfields as $userfield => $fieldlabel) {
            //calculate any necessary custom options
            $myoptions = $this->make_filter_options('up', $userfield, $options['help'],
                                                    $options['tables']);

            //determine field type from mapping
            $ftype = (string)$this->fieldtofiltermap['up'][$userfield];

            $advanced = (!empty($options['advanced']) &&
                         in_array($userfield, $options['advanced']))
                        || (!empty($options['notadvanced']) &&
                            !in_array($userfield, $options['notadvanced']));
            //create the sub-filter object
            $this->_filters['up'][$userfield] = new generalized_filter_entry($userfield, $myoptions['talias'],
                                                $myoptions['dbfield'], $fieldlabel, $advanced, $ftype, $myoptions);
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
     * @return array The customized options for the selected sub-filter
     */
    function make_filter_options_custom($options, $group, $name) {

        switch ($name) {
            case 'fullname':
                //combine the firstname and lastname into a fullname field
                $firstname = $this->tables[$group]['crlm_user'] .'.firstname';
                $lastname  = $this->tables[$group]['crlm_user'] .'.lastname';
                $options['dbfield'] = sql_fullname($firstname, $lastname);
                $options['talias'] = '';
                //todo: find a better way to do this
                $this->fieldtofiltermap[$group][$options['dbfield']] =
                        generalized_filter_elisuserprofile::filtertypetext;
                break;
            case 'country':
                //populate dropdown entries for countries
                $countries = cm_get_list_of_countries();
                $options['choices'] = $countries;
                break;
            case 'language':
                //populate dropdown entries for languages
                $languages = cm_get_list_of_languages();
                $options['choices'] = $languages;
                break;
            case 'inactive':
                //populate dropdown entries for inactive flag filtering options
                $options['choices'] = array('0' => get_string('no'), 1 => get_string('yes'));
                $options['numeric'] = 1;
                break;
        }

        $pos = strpos($name, 'customfield-');

        if ($pos !== false) {
            $options['contextlevel'] = context_level_base::get_custom_context_level('user', 'block_curr_admin');
        }

        return $options;
    }
}

?>