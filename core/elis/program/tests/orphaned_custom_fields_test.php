<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */


require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/core/lib/setup.php');

// Libs.
require_once(elis::lib('data/customfield.class.php'));
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elispm::lib('lib.php'));

/**
 * Class for testing the storage and retrieval of custom field data
 * @group elis_program
 */
class orphanedcustomfields_testcase extends elis_database_test {

    /**
     * Validate that an invalid category for a field causes a field
     * to be moved to the Miscellaneous category
     */
    public function test_orphanedcustomfieldsmovedtomiscellaneouscategory() {
        global $CFG, $DB;

        $contextlevel = CONTEXT_ELIS_USERSET;
        $misccat = get_string('misc_category', 'elis_program');

        // Set up a custom field with an invalid category.
        $field = new field(array(
            'name'        => 'testcustomfieldname',
            'datatype'    => 'char',
            'categoryid' => '99',
            'multivalued' => 1
        ));
        $field->save();

        // Set up the default data.
        $defaultparams = array(
            'fieldid'   => $field->id,
            'contextid' => $contextlevel,
            'data'      => 'value1'
        );
        $defaultdata = new field_data_char($defaultparams);
        $defaultdata->save();

        // Also create a contextlevel record for the field.
        $fieldcontext = new field_contextlevel();
        $fieldcontext->fieldid = $field->id;
        $fieldcontext->contextlevel = $contextlevel;
        $fieldcontext->save();

        // Call function to check if this is an orphaned field.
        pm_fix_orphaned_fields();

        // Assert that the field exists.
        $result = $DB->get_field(field::TABLE, 'id', array('id' => $field->id));
        $this->assertEquals($field->id, $result);

        // Assert that the field is in the correct context still.
        $sql = "SELECT field.id
                  FROM {".field::TABLE."} field
                  JOIN {".field_contextlevel::TABLE."} ctx
                    ON ctx.fieldid = field.id
                 WHERE ctx.contextlevel = ?
                   AND field.id = ?";
        $params = array($contextlevel, $field->id);
        $result = $DB->get_field_sql($sql, $params);
        $this->assertEquals($field->id, $result);

        // Assert that the Miscellaneous category exists.
        $result = $DB->get_field(field_category::TABLE, 'name', array('name' => $misccat));
        $this->assertEquals($misccat, $result);

        // Assert that the Miscellaneous category is in the correct context.
        $sql = "SELECT category.name
                  FROM {".field_category::TABLE."} category
                  JOIN {".field_category_contextlevel::TABLE."} category_context
                    ON category.id = category_context.categoryid
                 WHERE category_context.contextlevel = ?
                   AND category.name = ?";
        $params = array($contextlevel, $misccat);
        $result = $DB->get_field_sql($sql, $params);
        $this->assertEquals($misccat, $result);

        // Assert that the field is in the Miscellaneous category.
        $sql = "SELECT field.id
                  FROM {".field::TABLE."} field
                  JOIN {".field_category::TABLE."} category
                    ON category.id = field.categoryid
                 WHERE field.id = ?
                   AND category.name = ?";
        $params = array($field->id, $misccat);
        $result = $DB->get_field_sql($sql, $params);
        $this->assertEquals($field->id, $result);

        // Now check that an invalid field will be moved to the existing Miscellaneous category
        // and not create a second Miscellaneous category.

        // Set up a custom field with an invalid category.
        $field = new field(array(
            'name' => 'testcustomfieldname2',
            'datatype' => 'char',
            'categoryid' => '109'
        ));
        $field->save();

        // Set up the default data.
        $defaultparams = array(
            'fieldid'   => $field->id,
            'contextid' => $contextlevel,
            'data'      => 'value2'
        );
        $defaultdata = new field_data_char($defaultparams);
        $defaultdata->save();

        // Also create a contextlevel record for the field.
        $fieldcontext = new field_contextlevel();
        $fieldcontext->fieldid = $field->id;
        $fieldcontext->contextlevel = $contextlevel;
        $fieldcontext->save();

        // Call function to check if this is an orphaned field.
        pm_fix_orphaned_fields();

        // Assert that only one Miscellaneous category exists.
        $result = $DB->get_records(field_category::TABLE, array('name'=>$misccat));
        $this->assertEquals(1, count($result));

        // Assert that the field is in the Miscellaneous category.
        $sql = "SELECT field.id
                  FROM {".field::TABLE."} field
                  JOIN {".field_category::TABLE."} category
                    ON category.id = field.categoryid
                 WHERE field.id = ?
                   AND category.name = ?";
        $params = array($field->id, $misccat);
        $result = $DB->get_field_sql($sql, $params);
        $this->assertEquals($field->id, $result);
    }
}