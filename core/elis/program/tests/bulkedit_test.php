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
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Libs.
require_once($CFG->dirroot.'/elis/program/accesslib.php');
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('associationpage.class.php'));
require_once(elispm::lib('lib.php'));
require_once(elispm::file('tests/other/datagenerator.php'));
require_once($CFG->dirroot.'/elis/program/studentpage.class.php');

/**
 * Test bulkedit
 * @group elis_program
 */
class bulkedit_testcase extends elis_database_test {

    /**
     * Tests associationpage's bulk_apply_all function.
     * Tests applying values to all saved checkboxes.
     */
    public function test_bulkapplyall() {
        global $SESSION, $_GET;

        $_GET['s'] = 'test';
        $_GET['id'] = 1;
        $_GET['target'] = 'test';

        // The record present in $SESSION.
        $SESSION->associationpage['test1test'] = array();
        $rec = new stdClass;
        $rec->selected = true;
        $rec->enrolment_date = new stdClass;
        $rec->completion_date = new stdClass;
        $SESSION->associationpage['test1test'][1] = $rec;
        $rec2 = new stdClass;
        $rec2->selected = false;
        $rec->enrolment_date = new stdClass;
        $rec->completion_date = new stdClass;
        $SESSION->associationpage['test1test'][2] = $rec2;

        // The new values to apply.
        $blktpl = new stdClass;
        $blktpl->enrolment_date_checked = true;
        $blktpl->enrolment_date = new stdClass;
        $blktpl->enrolment_date->day = 1;
        $blktpl->enrolment_date->month = 1;
        $blktpl->enrolment_date->year = 1;
        $blktpl->completion_date_checked = true;
        $blktpl->completion_date = new stdClass;
        $blktpl->completion_date->day = 1;
        $blktpl->completion_date->month = 1;
        $blktpl->completion_date->year = 1;
        $blktpl->status_checked = true;
        $blktpl->status = 1;
        $blktpl->grade_checked = true;
        $blktpl->grade = 1;
        $blktpl->credits_checked = true;
        $blktpl->credits = 1;
        $blktpl->locked_checked = true;
        $blktpl->locked = 1;
        $_GET['bulktpl'] = json_encode($blktpl);

        $associationpage = new associationpage();
        $associationpage->bulk_apply_all();

        $this->assertInternalType('object', $SESSION);
        $this->assertObjectHasAttribute('associationpage', $SESSION);

        $this->assertInternalType('array', $SESSION->associationpage);
        $this->assertArrayHasKey('test1test', $SESSION->associationpage);

        $this->assertInternalType('array', $SESSION->associationpage['test1test']);

        // Test that values were applied to users with 'selected' attribute.
        $this->assertArrayHasKey(1, $SESSION->associationpage['test1test']);

        $rec = $SESSION->associationpage['test1test'][1];
        $this->assertInternalType('object', $rec);

        $this->assertObjectHasAttribute('enrolment_date', $rec);
        $this->assertInternalType('object', $rec->enrolment_date);
        $this->assertObjectHasAttribute('day', $rec->enrolment_date);
        $this->assertObjectHasAttribute('month', $rec->enrolment_date);
        $this->assertObjectHasAttribute('year', $rec->enrolment_date);
        $this->assertEquals($blktpl->enrolment_date->day, $rec->enrolment_date->day);
        $this->assertEquals($blktpl->enrolment_date->month, $rec->enrolment_date->month);
        $this->assertEquals($blktpl->enrolment_date->year, $rec->enrolment_date->year);

        $this->assertObjectHasAttribute('completion_date', $rec);
        $this->assertInternalType('object', $rec->completion_date);
        $this->assertObjectHasAttribute('day', $rec->completion_date);
        $this->assertObjectHasAttribute('month', $rec->completion_date);
        $this->assertObjectHasAttribute('year', $rec->completion_date);
        $this->assertEquals($blktpl->completion_date->day, $rec->completion_date->day);
        $this->assertEquals($blktpl->completion_date->month, $rec->completion_date->month);
        $this->assertEquals($blktpl->completion_date->year, $rec->completion_date->year);

        $properties = array('status', 'grade', 'credits', 'locked');
        foreach ($properties as $prop) {
            $this->assertObjectHasAttribute($prop, $rec);
            $this->assertEquals($blktpl->$prop, $rec->$prop);
        }

        // Test that values were NOT applied to users without 'selected' attribute.
        $this->assertArrayHasKey(2, $SESSION->associationpage['test1test']);
        $rec = $SESSION->associationpage['test1test'][2];
        $this->assertInternalType('object', $rec);
        $this->assertObjectNotHasAttribute('enrolment_date', $rec);
        $this->assertObjectNotHasAttribute('completion_date', $rec);
        $this->assertObjectNotHasAttribute('status', $rec);
        $this->assertObjectNotHasAttribute('grade', $rec);
        $this->assertObjectNotHasAttribute('credits', $rec);
        $this->assertObjectNotHasAttribute('locked', $rec);
    }

    /**
     * Tests associationpage's bulk_checkbox_selection_deselectall function
     * De-selects all saved checkbox selections.
     */
    public function test_bulkcheckboxselection_deselectall() {
        global $SESSION, $_GET;

        $_GET['s'] = 'test';
        $_GET['id'] = 1;
        $_GET['target'] = 'test';

        $rec = new stdClass;
        $rec->selected = true;
        $SESSION->associationpage['test1test'] = array(
            1 => $rec,
            2 => $rec
        );

        $associationpage = new associationpage();
        $associationpage->bulk_checkbox_selection_deselectall();

        foreach ($SESSION->associationpage['test1test'] as $uid => $rec) {
            $this->assertInternalType('object', $rec);
            $this->assertObjectNotHasAttribute('selected', $rec);
        }
    }

    /**
     * Tests associationpage's bulk_checkbox_selection_reset function.
     * Resets all saved changes.
     */
    public function test_bulkcheckboxselection_reset() {
        global $SESSION, $_GET;

        $_GET['s'] = 'test';
        $_GET['id'] = 1;
        $target = 'test';

        $SESSION->associationpage['test1test'] = 'somevalue';

        $associationpage = new associationpage();
        $associationpage->bulk_checkbox_selection_reset($target);

        $this->assertInternalType('object', $SESSION);
        $this->assertObjectHasAttribute('associationpage', $SESSION);
        $this->assertInternalType('array', $SESSION->associationpage);
        $this->assertArrayNotHasKey('test1test', $SESSION->associationpage);
    }

    /**
     * Tests associationpage's bulk_checkbox_selection_session function.
     * Saves values to the session.
     */
    public function test_bulkcheckboxselection_session() {
        global $SESSION, $_GET, $_POST;

        $_GET['s'] = 'test';
        $_GET['id'] = 1;
        $_GET['target'] = 'test';

        $select1 = new stdClass;
        $select1->id = 5;
        $select1->changed = true;
        $select1->selected = true;
        $select1->enrolment_date = new stdClass;
        $select1->enrolment_date->day = 24;
        $select1->enrolment_date->month = 7;
        $select1->enrolment_date->year = 2011;
        $select1->completion_date = new stdClass;
        $select1->completion_date->day = 24;
        $select1->completion_date->month = 7;
        $select1->completion_date->year = 2011;
        $select1->unenrol = 0;
        $select1->status = 2;
        $select1->grade = '95.000';
        $select1->credits = '150.00';
        $select1->locked = true;
        $select1->associd = 6;

        $select2 = new stdClass;
        $select2->id = 6;
        $select2->changed = false;
        $select2->selected = false;
        $select2->enrolment_date = new stdClass;
        $select2->enrolment_date->day = 24;
        $select2->enrolment_date->month = 7;
        $select2->enrolment_date->year = 2011;
        $select2->completion_date = new stdClass;
        $select2->completion_date->day = 24;
        $select2->completion_date->month = 7;
        $select2->completion_date->year = 2011;
        $select2->unenrol = 0;
        $select2->status = 2;
        $select2->grade = '95.000';
        $select2->credits = '150.00';
        $select2->locked = true;
        $select2->associd = 6;

        $_POST['selected_checkboxes'] = json_encode(array(json_encode($select1), json_encode($select2)));

        $associationpage = new associationpage();
        $associationpage->bulk_checkbox_selection_session();

        // Assert we have the correct basic keys.
        $this->assertInternalType('object', $SESSION);
        $this->assertObjectHasAttribute('associationpage', $SESSION);
        $this->assertInternalType('array', $SESSION->associationpage);
        $this->assertArrayHasKey('test1test', $SESSION->associationpage);
        $this->assertInternalType('array', $SESSION->associationpage['test1test']);

        // Assert changed user has session entry.
        $this->assertArrayHasKey(5, $SESSION->associationpage['test1test']);
        $this->assertInternalType('object', $SESSION->associationpage['test1test'][5]);

        // Enrolment date.
        $this->assertObjectHasAttribute('enrolment_date', $SESSION->associationpage['test1test'][5]);
        $this->assertInternalType('object', $SESSION->associationpage['test1test'][5]->enrolment_date);
        $this->assertObjectHasAttribute('day', $SESSION->associationpage['test1test'][5]->enrolment_date);
        $this->assertObjectHasAttribute('month', $SESSION->associationpage['test1test'][5]->enrolment_date);
        $this->assertObjectHasAttribute('year', $SESSION->associationpage['test1test'][5]->enrolment_date);
        $this->assertEquals($select1->enrolment_date->day, $SESSION->associationpage['test1test'][5]->enrolment_date->day);
        $this->assertEquals($select1->enrolment_date->month, $SESSION->associationpage['test1test'][5]->enrolment_date->month);
        $this->assertEquals($select1->enrolment_date->year, $SESSION->associationpage['test1test'][5]->enrolment_date->year);

        // Completion date.
        $this->assertObjectHasAttribute('completion_date', $SESSION->associationpage['test1test'][5]);
        $this->assertInternalType('object', $SESSION->associationpage['test1test'][5]->completion_date);
        $this->assertObjectHasAttribute('day', $SESSION->associationpage['test1test'][5]->completion_date);
        $this->assertObjectHasAttribute('month', $SESSION->associationpage['test1test'][5]->completion_date);
        $this->assertObjectHasAttribute('year', $SESSION->associationpage['test1test'][5]->completion_date);
        $this->assertEquals($select1->completion_date->day, $SESSION->associationpage['test1test'][5]->completion_date->day);
        $this->assertEquals($select1->completion_date->month, $SESSION->associationpage['test1test'][5]->completion_date->month);
        $this->assertEquals($select1->completion_date->year, $SESSION->associationpage['test1test'][5]->completion_date->year);

        // Other properties.
        $properties = array('unenrol', 'status', 'grade', 'credits', 'locked', 'associd');
        foreach ($properties as $prop) {
            $this->assertObjectHasAttribute($prop, $SESSION->associationpage['test1test'][5]);
            $this->assertEquals($select1->$prop, $SESSION->associationpage['test1test'][5]->$prop);
        }

        // Assert non-changed user does NOT have a session entry.
        $this->assertArrayNotHasKey(6, $SESSION->associationpage['test1test']);
    }

    /**
     * Tests retrieve_session_selection_bulkedit()
     * Tests retrieving saved information from the session.
     */
    public function test_retrieve_session_selection_bulkedit() {
        global $SESSION, $_GET;

        $_GET['s'] = 'test';
        $_GET['id'] = 1;
        $_GET['target'] = 'test';

        $SESSION->associationpage['test1test'] = array(5 => 'somevalue');
        $retrieved = retrieve_session_selection_bulkedit(5, 'test');
        $this->assertEquals('somevalue', $retrieved);
    }

    /**
     * Tests retrieve_session_selection()
     * Tests retrieving saved information from the session.
     */
    public function test_retrieve_session_selection() {
        global $SESSION, $_GET;

        $_GET['s'] = 'test';
        $_GET['id'] = 1;
        $_GET['target'] = 'test';

        $select = new stdClass;
        $select->id = 5;
        $select->somevalue = 'somevalue';

        $SESSION->associationpage['test1test'] = array(json_encode($select));

        $result = retrieve_session_selection(5, 'test');

        $this->AssertNotEquals(false, $result);
        $result = @json_decode($result);
        $this->assertInternalType('object', $result);
        $this->assertObjectHasAttribute('id', $result);
        $this->assertObjectHasAttribute('somevalue', $result);
        $this->assertEquals(5, $result->id);
        $this->assertEquals('somevalue', $result->somevalue);
    }

    /**
     * Tests the print_checkbox_selection() function.
     * Tests printing out saved checkboxes for use by javascript.
     */
    public function test_print_checkbox_selection() {
        global $SESSION;

        $page = 'test';
        $classid = 1;
        $target = 'test';
        $pagename = $page.$classid.$target;

        $id1 = new stdClass;
        $id1->id = 2;

        $id2 = new stdClass;
        $id2->id = 3;

        $id3 = new stdClass;
        $id3->id = 4;

        $id4 = new stdClass;
        $id4->id = 5;

        $SESSION->associationpage[$pagename] = array(
            json_encode($id1),
            json_encode($id2),
            json_encode($id3),
            json_encode($id4)
        );

        ob_start();
        print_checkbox_selection($classid, $page, $target);
        $actual = ob_get_contents();
        ob_end_clean();

        // Generate expected output.
        $baseurl = get_pm_url()->out_omit_querystring().'?&id='.$classid.'&s='.$page.'&target='.$target;
        $expected = '<input type="hidden" id="baseurl" value="'.$baseurl.'" /> ';
        $ids = '2,3,4,5';
        $expected .= '<input type="hidden" id="selected_checkboxes" value="'.$ids.'" /> ';

        // Assert.
        $this->assertEquals($expected, $actual);
    }

    /**
     * Tests print_ids_for_checkbox_selection()
     * Tests printing out saved information for use by javascript.
     */
    public function test_print_ids_for_checkbox_selection() {
        $page = 'test';
        $classid = 1;
        $target = 'test';
        $ids = array(1, 2, 3, 4, 5, 6, 7);

        ob_start();
        print_ids_for_checkbox_selection($ids, $classid, $page, $target);
        $actual = ob_get_contents();
        ob_end_clean();

        $baseurl = get_pm_url()->out_omit_querystring().'?&id='.$classid.'&s='.$page.'&target='.$target;
        $expected = '<input type="hidden" id="baseurl" value="'.$baseurl.'" /> ';
        $expected .= '<input type="hidden" id="selfurl" value="'.qualified_me().'" /> ';
        $expected .= '<input type="hidden" id="persist_ids_this_page" value="'.implode(',', $ids).'" /> ';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Tests session_selection_deletion()
     * Tests deleting saved information for a specific page.
     */
    public function test_session_selection_deletion() {
        global $SESSION, $_GET;

        $_GET['s'] = 'test';
        $_GET['id'] = 1;
        $target = 'test';

        $SESSION->associationpage['test1test'] = 'somevalue';

        session_selection_deletion($target);

        $this->assertInternalType('object', $SESSION);
        $this->assertObjectHasAttribute('associationpage', $SESSION);
        $this->assertInternalType('array', $SESSION->associationpage);
        $this->assertArrayNotHasKey('test1test', $SESSION->associationpage);
    }
}