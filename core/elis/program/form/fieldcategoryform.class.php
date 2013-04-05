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

global $CFG;
require_once elispm::file('form/cmform.class.php');
require_once elis::lib('data/customfield.class.php');

class fieldcategoryform extends cmform {

    // Define the form
    function definition () {
        global $USER, $CFG, $DB;

        $mform =& $this->_form;

        $strrequired = get_string('required');

        $category_name = '';
        $field_category = $DB->get_record(field_category::TABLE, array('id'=>optional_param('id', 0, PARAM_INT)));
        if (!empty($field_category)) {
            $category_name = $field_category->name;
        }

        $mform->addElement('text', 'name', get_string('profilecategoryname', 'admin'), array('maxlength'=>'255', 'size'=>'30', 'value'=>$category_name));
        $mform->setType('name', PARAM_MULTILANG);
        $mform->addRule('name', $strrequired, 'required', null, 'client');

        $this->add_action_buttons(true);
    }

    public function validation($data, $files) {
        global $DB;
        $errors = array();
        $level = required_param('level', PARAM_ACTION);
        $ctxlvl = context_elis_helper::get_level_from_name($level);
        if (!empty($ctxlvl)) {
            $sql = 'SELECT cat.id
                FROM {elis_field_categories} cat
                JOIN {elis_field_category_contexts} ctx ON cat.id = ctx.categoryid
                WHERE ctx.contextlevel = ? AND cat.name=?';
            $params = array($ctxlvl,$data['name']);
            $existing_catname = $DB->get_recordset_sql($sql,$params);
            if ($existing_catname->valid() === true) {
                $a = new stdClass;
                $a->tablename = 'elis_field_categories';
                $a->fields = 'name';
                $errors['name'] = get_string('data_object_validation_unique', 'elis_core', $a);
            }
            unset($existing_catname);
        }
        return $errors;
    }

}
