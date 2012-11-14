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
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/core/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::lib('lib.php'));

/**
 * Class for testing the storage and retrieval of custom field data
 */
class orphanedCustomFields extends elis_database_test {

        /**
     * Return the list of tables that should be overlayed.
     */
    static protected function get_overlay_tables() {
        return array(
            field::TABLE                       => 'elis_core',
            field_category::TABLE              => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            field_contextlevel::TABLE          => 'elis_core',
            field_data_char::TABLE             => 'elis_core'
        );
    }

    /**
     * Validate that an invalid category for a field causes a field
     * to be moved to the Miscellaneous category
     */
    public function testOrphanedCustomFieldsMovedToMiscellaneousCategory() {
        global $CFG, $DB;

        $contextlevel = CONTEXT_ELIS_USERSET;
        $misc_cat = get_string('misc_category','elis_program');

        //set up a custom field with an invalid category
        $field = new field(array(
            'name'        => 'testcustomfieldname',
            'datatype'    => 'char',
            'categoryid' => '99',
            'multivalued' => 1
        ));
        $field->save();

        //set up the default data
        $default_params = array(
            'fieldid'   => $field->id,
            'contextid' => $contextlevel,
            'data'      => 'value1'
        );
        $default_data = new field_data_char($default_params);
        $default_data->save();

        //also create a contextlevel record for the field
        $fieldcontext = new field_contextlevel();
        $fieldcontext->fieldid = $field->id;
        $fieldcontext->contextlevel = $contextlevel;
        $fieldcontext->save();

        // call function to check if this is an orphaned field
        pm_fix_orphaned_fields();

        //assert that the field exists
        $result = $DB->get_field(field::TABLE,'id',array('id'=>$field->id));
        $this->assertEquals($field->id,$result);

        //assert that the field is in the correct context still
        $sql = "SELECT field.id
                  FROM {".field::TABLE."} field
                  JOIN {".field_contextlevel::TABLE."} ctx
                    ON ctx.fieldid = field.id
                 WHERE ctx.contextlevel = ?
                   AND field.id = ?";
        $params = array($contextlevel, $field->id);
        $result = $DB->get_field_sql($sql, $params);
        $this->assertEquals($field->id,$result);

        //assert that the Miscellaneous category exists
        $result = $DB->get_field(field_category::TABLE,'name',array('name'=>$misc_cat));
        $this->assertEquals($misc_cat,$result);

        //assert that the Miscellaneous category is in the correct context
        $sql = "SELECT category.name
                  FROM {".field_category::TABLE."} category
                  JOIN {".field_category_contextlevel::TABLE."} category_context
                    ON category.id = category_context.categoryid
                 WHERE category_context.contextlevel = ?
                   AND category.name = ?";
        $params = array($contextlevel,$misc_cat);
        $result = $DB->get_field_sql($sql, $params);
        $this->assertEquals($misc_cat,$result);

        //assert that the field is in the Miscellaneous category
        $sql = "SELECT field.id
                  FROM {".field::TABLE."} field
                  JOIN {".field_category::TABLE."} category
                    ON category.id = field.categoryid
                 WHERE field.id = ?
                   AND category.name = ?";
        $params = array($field->id,$misc_cat);
        $result = $DB->get_field_sql($sql, $params);
        $this->assertEquals($field->id,$result);

        // Now check that an invalid field will be moved to the existing Miscellaneous category
        // and not create a second Miscellaneous category

        //set up a custom field with an invalid category
        $field = new field(array(
            'name'        => 'testcustomfieldname2',
            'datatype'    => 'char',
            'categoryid' => '109'
        ));
        $field->save();

        //set up the default data
        $default_params = array(
            'fieldid'   => $field->id,
            'contextid' => $contextlevel,
            'data'      => 'value2'
        );
        $default_data = new field_data_char($default_params);
        $default_data->save();

        //also create a contextlevel record for the field
        $fieldcontext = new field_contextlevel();
        $fieldcontext->fieldid = $field->id;
        $fieldcontext->contextlevel = $contextlevel;
        $fieldcontext->save();

        // call function to check if this is an orphaned field
        pm_fix_orphaned_fields();

        //assert that only one Miscellaneous category exists
        $result = $DB->get_records(field_category::TABLE, array('name'=>$misc_cat));
        $this->assertEquals(1,count($result));

        //assert that the field is in the Miscellaneous category
        $sql = "SELECT field.id
                  FROM {".field::TABLE."} field
                  JOIN {".field_category::TABLE."} category
                    ON category.id = field.categoryid
                 WHERE field.id = ?
                   AND category.name = ?";
        $params = array($field->id,$misc_cat);
        $result = $DB->get_field_sql($sql, $params);
        $this->assertEquals($field->id,$result);
    }
}
