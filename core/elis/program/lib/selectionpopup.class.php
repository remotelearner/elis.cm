<?php
/**
 * General class for displaying pages in the curriculum management system.
 *
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

/**
 * This file contains table_sql child classes used to override the display code
 * for certain columns
 */
require_once($CFG->libdir.'/tablelib.php');

global $OUTPUT;

class trackselectiontable extends table_sql {

    /**
     * A hidden HTML element that gets updated, with the track id, when the
     * track name is clicked
     */
    var $update_element;

    /**
     * The onclick event handler function.  The function should accept the
     * following parameters: HTML element to update (see comments on
     * $update_element), name and id parameters. (ex. function myonclickhandler
     * (update_element, name, id)
     */
    var $onclick_func;

    function __construct($uniqueid, $update_element, $onclick_func) {
        parent::__construct($uniqueid);

        $this->update_element   = $update_element;
        $this->onclick_func     = $onclick_func;
    }

    function col_name($trackdata) {
        $output = 'n/a';

        if (!empty($trackdata->name)) {
            $id = ' id="'. $trackdata->id . '" ';
            $event = " onclick=\"{$this->onclick_func}('{$this->update_element}', '{$trackdata->name}', '{$trackdata->id}');\"";

            $output = "<a href=\"#\" $id $event > {$trackdata->name}</a>";
        }

        return $output;
    }
}

class classselectiontable extends table_sql {

    /**
     * A hidden HTML element that gets updated, with the class id, when the
     * class name is clicked
     */
    var $update_element;

    /**
     * The onclick event handler function.  The function should accept the
     * following parameters: HTML element to update (see comments on
     * $update_element), name and id parameters. (ex. function myonclickhandler
     * (update_element, name, id)
     */
    var $onclick_func;

    function __construct($uniqueid, $update_element, $onclick_func) {
        parent::__construct($uniqueid);

        $this->update_element   = $update_element;
        $this->onclick_func     = $onclick_func;
    }

    function col_idnumber($classdata) {
        $output = 'n/a';

        if (!empty($classdata->idnumber)) {
            $id = ' id="'. $classdata->id . '" ';
            $event = " onclick=\"{$this->onclick_func}('{$this->update_element}', '{$classdata->idnumber}', '{$classdata->id}');\"";

            $output = "<a href=\"#\" $id $event > {$classdata->idnumber}</a>";
        }

        return $output;
    }
}