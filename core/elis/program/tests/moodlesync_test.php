<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once(__DIR__.'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once($CFG->dirroot.'/elis/program/lib/moodlesync.class.php');
require_once($CFG->dirroot.'/elis/program/lib/data/classmoodlecourse.class.php');
require_once($CFG->dirroot.'/elis/program/tests/other/datagenerator.php');

/**
 * Tests the Synchronize class's individual methods.
 *
 * @group elis_program
 */
class synchronize_testcase extends elis_database_test {

    /**
     * Test get_syncable_users method.
     */
    public function test_get_syncable_users() {
        global $DB, $CFG;

        $timenow = time();
        $edatagenerator = new elis_program_datagenerator($DB);

        $mcourses = array(
            'linked1' => $this->getDataGenerator()->create_course(),
            'linked2' => $this->getDataGenerator()->create_course(),
            'linkedmultiple1' => $this->getDataGenerator()->create_course(),
            'notlinked1' => $this->getDataGenerator()->create_course(),
            'notlinked2' => $this->getDataGenerator()->create_course(),
        );

        $ecourses = array(
            0 => $edatagenerator->create_course(array('completion_grade' => 50, 'credits' => 12)),
            'linkedmultiple1_2' => $edatagenerator->create_course(array('completion_grade' => 51, 'credits' => 13)),
            'linkedmultiple3' => $edatagenerator->create_course(array('completion_grade' => 52, 'credits' => 14)),
        );

        $eclasses = array(
            'linked1' => $edatagenerator->create_pmclass(array('courseid' => $ecourses[0]->id)),
            'linked2' => $edatagenerator->create_pmclass(array('courseid' => $ecourses[0]->id)),
            'linkedmultiple1' => $edatagenerator->create_pmclass(array('courseid' => $ecourses['linkedmultiple1_2']->id)),
            'linkedmultiple2' => $edatagenerator->create_pmclass(array('courseid' => $ecourses['linkedmultiple1_2']->id)),
            'linkedmultiple3' => $edatagenerator->create_pmclass(array('courseid' => $ecourses['linkedmultiple3']->id)),
        );

        $classmoodlecourses = array(
                $edatagenerator->assign_pmclass_to_moodlecourse($eclasses['linked1']->id, $mcourses['linked1']->id),
                $edatagenerator->assign_pmclass_to_moodlecourse($eclasses['linked2']->id, $mcourses['linked2']->id),
                $edatagenerator->assign_pmclass_to_moodlecourse($eclasses['linkedmultiple1']->id, $mcourses['linkedmultiple1']->id),
                $edatagenerator->assign_pmclass_to_moodlecourse($eclasses['linkedmultiple2']->id, $mcourses['linkedmultiple1']->id),
                $edatagenerator->assign_pmclass_to_moodlecourse($eclasses['linkedmultiple3']->id, $mcourses['linkedmultiple1']->id),
        );

        // Create users in different setups.
        $cases = array();
        for ($i = 0; $i <= 7; $i++) {

            // Holds data for each case so we can use it later for assertions.
            $case = array();
            $case['muser'] = $this->getDataGenerator()->create_user(array('username' => 'testuser'.$i, 'idnumber' => 'testuser'.$i));;

            switch ($i) {
                case 0:
                    // User w/ no student entry.
                    // We should get one entry for this user that does not include the ELIS student information.
                    $case['cuser'] = $edatagenerator->create_user((array)$case['muser']);
                    $case['usermoodle'] = $edatagenerator->assign_euser_to_muser($case['cuser']->id, $case['muser']->id, $case['cuser']->idnumber);
                    $case['mcourse'][] = $mcourses['linked1'];
                    $case['pmcourse'] = $ecourses[0];
                    $case['pmclass'][] = $eclasses['linked1'];
                    $mcrsctx = context_course::instance($case['mcourse'][0]->id);
                    $gbookroles = explode(',', $CFG->gradebookroles);
                    $case['roleassignment'] = $DB->insert_record('role_assignments', array(
                        'roleid' => $gbookroles[0],
                        'contextid' => $mcrsctx->id,
                        'userid' => $case['muser']->id
                    ));
                    break;

                case 1:
                    // User w/ student entry.
                    // We should get one entry for this user that includes the ELIS student information.
                    $case['cuser'] = $edatagenerator->create_user((array)$case['muser']);
                    $case['usermoodle'] = $edatagenerator->assign_euser_to_muser($case['cuser']->id, $case['muser']->id, $case['cuser']->idnumber);
                    $case['mcourse'][] = $mcourses['linked1'];
                    $case['pmcourse'] = $ecourses[0];
                    $case['pmclass'][] = $eclasses['linked1'];
                    $mcrsctx = context_course::instance($case['mcourse'][0]->id);
                    $gbookroles = explode(',', $CFG->gradebookroles);
                    $case['roleassignment'] = $DB->insert_record('role_assignments', array(
                        'roleid' => $gbookroles[0],
                        'contextid' => $mcrsctx->id,
                        'userid' => $case['muser']->id
                    ));
                    $case['student'] = new student(array(
                        'classid' => $case['pmclass'][0]->id,
                        'userid' => $case['cuser']->id,
                        'enrolmenttime' => $timenow,
                        'completetime' => 0,
                        'endtime' => 0,
                        'completestatusid' => STUSTATUS_NOTCOMPLETE,
                        'grade' => 0,
                        'credits' => 12,
                        'locked' => 0,
                    ));
                    $case['student']->save();
                    $case['student'] = $case['student']->to_object();
                    break;

                case 2:
                    // User with multiple enrolments.
                    // We should get two entries for this user.
                    $case['cuser'] = $edatagenerator->create_user((array)$case['muser']);
                    $case['usermoodle'] = $edatagenerator->assign_euser_to_muser($case['cuser']->id, $case['muser']->id, $case['cuser']->idnumber);

                    $case['mcourse'][] = $mcourses['linked1'];
                    $case['pmcourse'] = $ecourses[0];
                    $case['pmclass'][] = $eclasses['linked1'];
                    $mcrsctx = context_course::instance($case['mcourse'][0]->id);
                    $gbookroles = explode(',', $CFG->gradebookroles);
                    $case['roleassignment'] = $DB->insert_record('role_assignments', array(
                        'roleid' => $gbookroles[0],
                        'contextid' => $mcrsctx->id,
                        'userid' => $case['muser']->id
                    ));

                    $case['mcourse'][] = $mcourses['linked2'];
                    $case['pmcourse'] = $ecourses[0];
                    $case['pmclass'][] = $eclasses['linked2'];
                    $mcrsctx = context_course::instance($case['mcourse'][1]->id);
                    $gbookroles = explode(',', $CFG->gradebookroles);
                    $case['roleassignment'] = $DB->insert_record('role_assignments', array(
                        'roleid' => $gbookroles[0],
                        'contextid' => $mcrsctx->id,
                        'userid' => $case['muser']->id
                    ));
                    break;

                case 3:
                    // User with multiple enrolments (one with a student entry).
                    // We should get two entries for this user, one with ELIS student information.
                    $case['cuser'] = $edatagenerator->create_user((array)$case['muser']);
                    $case['usermoodle'] = $edatagenerator->assign_euser_to_muser($case['cuser']->id, $case['muser']->id, $case['cuser']->idnumber);

                    $case['mcourse'][] = $mcourses['linked1'];
                    $case['pmcourse'] = $ecourses[0];
                    $case['pmclass'][] = $eclasses['linked1'];
                    $mcrsctx = context_course::instance($case['mcourse'][0]->id);
                    $gbookroles = explode(',', $CFG->gradebookroles);
                    $case['roleassignment'] = $DB->insert_record('role_assignments', array(
                        'roleid' => $gbookroles[0],
                        'contextid' => $mcrsctx->id,
                        'userid' => $case['muser']->id
                    ));

                    $case['mcourse'][] = $mcourses['linked2'];
                    $case['pmcourse'] = $ecourses[0];
                    $case['pmclass'][] = $eclasses['linked2'];
                    $mcrsctx = context_course::instance($case['mcourse'][1]->id);
                    $gbookroles = explode(',', $CFG->gradebookroles);
                    $case['roleassignment'] = $DB->insert_record('role_assignments', array(
                        'roleid' => $gbookroles[0],
                        'contextid' => $mcrsctx->id,
                        'userid' => $case['muser']->id
                    ));
                    $case['student'] = new student(array(
                        'classid' => $case['pmclass'][1]->id,
                        'userid' => $case['cuser']->id,
                        'enrolmenttime' => $timenow,
                        'completetime' => 0,
                        'endtime' => 0,
                        'completestatusid' => STUSTATUS_NOTCOMPLETE,
                        'grade' => 0,
                        'credits' => 5,
                        'locked' => 0,
                    ));
                    $case['student']->save();
                    $case['student'] = $case['student']->to_object();
                    break;

                case 4:
                    // User enroled in a moodle class that is linked to multiple elis classes.
                    // The course this user is enroled in is linked to two ELIS classes that are part of one course, and another
                    // elis class that is part of another course.
                    // We should get three entries for this user (one for each linked ELIS class).
                    $case['cuser'] = $edatagenerator->create_user((array)$case['muser']);
                    $case['usermoodle'] = $edatagenerator->assign_euser_to_muser($case['cuser']->id, $case['muser']->id, $case['cuser']->idnumber);

                    $case['mcourse'][] = $mcourses['linkedmultiple1'];
                    $case['pmcourse'][] = $ecourses['linkedmultiple1_2'];
                    $case['pmcourse'][] = $ecourses['linkedmultiple3'];
                    $case['pmclass'][] = $eclasses['linkedmultiple1'];
                    $case['pmclass'][] = $eclasses['linkedmultiple2'];
                    $case['pmclass'][] = $eclasses['linkedmultiple3'];

                    $mcrsctx = context_course::instance($case['mcourse'][0]->id);
                    $gbookroles = explode(',', $CFG->gradebookroles);
                    $case['roleassignment'] = $DB->insert_record('role_assignments', array(
                        'roleid' => $gbookroles[0],
                        'contextid' => $mcrsctx->id,
                        'userid' => $case['muser']->id
                    ));
                    break;

                case 5:
                    // Enroled in moodle course in non-gradebook role.
                    // We should not get an entry for this user.
                    $case['cuser'] = $edatagenerator->create_user((array)$case['muser']);
                    $case['usermoodle'] = $edatagenerator->assign_euser_to_muser($case['cuser']->id, $case['muser']->id, $case['cuser']->idnumber);
                    $case['mcourse'][] = $mcourses['linked1'];
                    $case['pmcourse'] = $ecourses[0];
                    $case['pmclass'][] = $eclasses['linked1'];
                    $mcrsctx = context_course::instance($case['mcourse'][0]->id);
                    $gbookroles = explode(',', $CFG->gradebookroles);
                    $nongbookrole = max($gbookroles)+1;
                    $case['roleassignment'] = $DB->insert_record('role_assignments', array(
                        'roleid' => $nongbookrole,
                        'contextid' => $mcrsctx->id,
                        'userid' => $case['muser']->id
                    ));
                    break;

                case 6:
                    // No connected ELIS user.
                    // We should not get an entry for this user.
                    $case['cuser'] = null;
                    $case['usermoodle'] = null;
                    $case['mcourse'][] = $mcourses['linked1'];
                    $case['pmcourse'] = $ecourses[0];
                    $case['pmclass'][] = $eclasses['linked1'];
                    $mcrsctx = context_course::instance($case['mcourse'][0]->id);
                    $gbookroles = explode(',', $CFG->gradebookroles);
                    $case['roleassignment'] = $DB->insert_record('role_assignments', array(
                        'roleid' => $gbookroles[0],
                        'contextid' => $mcrsctx->id,
                        'userid' => $case['muser']->id
                    ));
                    break;

                case 7:
                    // Course not connected to ELIS class.
                    // We should not get an entry for this user.
                    $case['cuser'] = $edatagenerator->create_user((array)$case['muser']);
                    $case['usermoodle'] = $edatagenerator->assign_euser_to_muser($case['cuser']->id, $case['muser']->id, $case['cuser']->idnumber);
                    $case['mcourse'][] = $mcourses['notlinked1'];
                    $case['pmcourse'] = null;
                    $case['pmclass'] = array();
                    $mcrsctx = context_course::instance($case['mcourse'][0]->id);
                    $gbookroles = explode(',', $CFG->gradebookroles);
                    $case['roleassignment'] = $DB->insert_record('role_assignments', array(
                        'roleid' => $gbookroles[0],
                        'contextid' => $mcrsctx->id,
                        'userid' => $case['muser']->id
                    ));
                    break;
            }
            $cases[$i] = $case;
        }

        $sync = new moodlesync();
        $rs = $sync->get_syncable_users();

        // Convert rs to array to better assert results.
        $syncableusers = array();
        foreach ($rs as $i => $rec) {
            $syncableusers[$rec->muid.'_'.$rec->moodlecourseid.'_'.$rec->pmclassid] = $rec;
        }
        ksort($syncableusers);

        $expectedresult = array(
            // Case 0. Single user w/ single enrolment.
            $cases[0]['muser']->id.'_'.$cases[0]['mcourse'][0]->id.'_'.$cases[0]['pmclass'][0]->id => (object)array(
                'muid' => (string)$cases[0]['muser']->id,
                'username' => (string)$cases[0]['muser']->username,
                'cmid' => (string)$cases[0]['cuser']->id,
                'moodlecourseid' => (string)$cases[0]['mcourse'][0]->id,
                'pmclassid' => (string)$cases[0]['pmclass'][0]->id,
                'pmcourseid' => (string)$cases[0]['pmcourse']->id,
                'pmcoursecompletiongrade' => (string)$cases[0]['pmcourse']->completion_grade,
                'pmcoursecredits' => (string)$cases[0]['pmcourse']->credits,
                'id' => null,
                'classid' => null,
                'userid' => null,
                'enrolmenttime' => null,
                'completetime' => null,
                'endtime' => null,
                'completestatusid' => null,
                'grade' => null,
                'credits' => null,
                'locked' => null,
            ),
            // Case 1. Single user w/ single enrolment and student entry.
            $cases[1]['muser']->id.'_'.$cases[1]['mcourse'][0]->id.'_'.$cases[1]['pmclass'][0]->id => (object)array(
                'muid' => (string)$cases[1]['muser']->id,
                'username' => (string)$cases[1]['muser']->username,
                'cmid' => (string)$cases[1]['cuser']->id,
                'moodlecourseid' => (string)$cases[1]['mcourse'][0]->id,
                'pmclassid' => (string)$cases[1]['pmclass'][0]->id,
                'pmcourseid' => (string)$cases[1]['pmcourse']->id,
                'pmcoursecompletiongrade' => (string)$cases[1]['pmcourse']->completion_grade,
                'pmcoursecredits' => (string)$cases[1]['pmcourse']->credits,
                'id' => (string)$cases[1]['student']->id,
                'classid' => (string)$cases[1]['student']->classid,
                'userid' => (string)$cases[1]['student']->userid,
                'enrolmenttime' => (string)$cases[1]['student']->enrolmenttime,
                'completetime' => (string)$cases[1]['student']->completetime,
                'endtime' => (string)$cases[1]['student']->endtime,
                'completestatusid' => (string)$cases[1]['student']->completestatusid,
                'grade' => (string)$cases[1]['student']->grade,
                'credits' => (string)$cases[1]['student']->credits,
                'locked' => (string)$cases[1]['student']->locked,
            ),
            // Case 2. Single user w/ multiple enrolments (1/2)
            $cases[2]['muser']->id.'_'.$cases[2]['mcourse'][0]->id.'_'.$cases[2]['pmclass'][0]->id => (object)array(
                'muid' => (string)$cases[2]['muser']->id,
                'username' => (string)$cases[2]['muser']->username,
                'cmid' => (string)$cases[2]['cuser']->id,
                'moodlecourseid' => (string)$cases[2]['mcourse'][0]->id,
                'pmclassid' => (string)$cases[2]['pmclass'][0]->id,
                'pmcourseid' => (string)$cases[2]['pmcourse']->id,
                'pmcoursecompletiongrade' => (string)$cases[2]['pmcourse']->completion_grade,
                'pmcoursecredits' => (string)$cases[2]['pmcourse']->credits,
                'id' => null,
                'classid' => null,
                'userid' => null,
                'enrolmenttime' => null,
                'completetime' => null,
                'endtime' => null,
                'completestatusid' => null,
                'grade' => null,
                'credits' => null,
                'locked' => null,
            ),
            // Case 2. Single user w/ multiple enrolments (2/2)
            $cases[2]['muser']->id.'_'.$cases[2]['mcourse'][1]->id.'_'.$cases[2]['pmclass'][1]->id => (object)array(
                'muid' => (string)$cases[2]['muser']->id,
                'username' => (string)$cases[2]['muser']->username,
                'cmid' => (string)$cases[2]['cuser']->id,
                'moodlecourseid' => (string)$cases[2]['mcourse'][1]->id,
                'pmclassid' => (string)$cases[2]['pmclass'][1]->id,
                'pmcourseid' => (string)$cases[2]['pmcourse']->id,
                'pmcoursecompletiongrade' => (string)$cases[2]['pmcourse']->completion_grade,
                'pmcoursecredits' => (string)$cases[2]['pmcourse']->credits,
                'id' => null,
                'classid' => null,
                'userid' => null,
                'enrolmenttime' => null,
                'completetime' => null,
                'endtime' => null,
                'completestatusid' => null,
                'grade' => null,
                'credits' => null,
                'locked' => null,
            ),
            // Case 3. Single user w/ multiple enrolments, one with student entry (1/2)
            $cases[3]['muser']->id.'_'.$cases[3]['mcourse'][0]->id.'_'.$cases[3]['pmclass'][0]->id => (object)array(
                'muid' => (string)$cases[3]['muser']->id,
                'username' => (string)$cases[3]['muser']->username,
                'cmid' => (string)$cases[3]['cuser']->id,
                'moodlecourseid' => (string)$cases[3]['mcourse'][0]->id,
                'pmclassid' => (string)$cases[3]['pmclass'][0]->id,
                'pmcourseid' => (string)$cases[3]['pmcourse']->id,
                'pmcoursecompletiongrade' => (string)$cases[3]['pmcourse']->completion_grade,
                'pmcoursecredits' => (string)$cases[3]['pmcourse']->credits,
                'id' => null,
                'classid' => null,
                'userid' => null,
                'enrolmenttime' => null,
                'completetime' => null,
                'endtime' => null,
                'completestatusid' => null,
                'grade' => null,
                'credits' => null,
                'locked' => null,
            ),
            // Case 3. Single user w/ multiple enrolments, one with student entry (2/2)
            $cases[3]['muser']->id.'_'.$cases[3]['mcourse'][1]->id.'_'.$cases[3]['pmclass'][1]->id => (object)array(
                'muid' => (string)$cases[3]['muser']->id,
                'username' => (string)$cases[3]['muser']->username,
                'cmid' => (string)$cases[3]['cuser']->id,
                'moodlecourseid' => (string)$cases[3]['mcourse'][1]->id,
                'pmclassid' => (string)$cases[3]['pmclass'][1]->id,
                'pmcourseid' => (string)$cases[3]['pmcourse']->id,
                'pmcoursecompletiongrade' => (string)$cases[3]['pmcourse']->completion_grade,
                'pmcoursecredits' => (string)$cases[3]['pmcourse']->credits,
                'id' => (string)$cases[3]['student']->id,
                'classid' => (string)$cases[3]['student']->classid,
                'userid' => (string)$cases[3]['student']->userid,
                'enrolmenttime' => (string)$cases[3]['student']->enrolmenttime,
                'completetime' => (string)$cases[3]['student']->completetime,
                'endtime' => (string)$cases[3]['student']->endtime,
                'completestatusid' => (string)$cases[3]['student']->completestatusid,
                'grade' => (string)$cases[3]['student']->grade,
                'credits' => (string)$cases[3]['student']->credits,
                'locked' => (string)$cases[3]['student']->locked,
            ),
            // Case 4. Single user with a single enrolment into a Moodle course linked to multiple ELIS classes.
            $cases[4]['muser']->id.'_'.$cases[4]['mcourse'][0]->id.'_'.$cases[4]['pmclass'][0]->id => (object)array(
                'muid' => (string)$cases[4]['muser']->id,
                'username' => (string)$cases[4]['muser']->username,
                'cmid' => (string)$cases[4]['cuser']->id,
                'moodlecourseid' => (string)$cases[4]['mcourse'][0]->id,
                'pmclassid' => (string)$cases[4]['pmclass'][0]->id,
                'pmcourseid' => (string)$cases[4]['pmcourse'][0]->id,
                'pmcoursecompletiongrade' => (string)$cases[4]['pmcourse'][0]->completion_grade,
                'pmcoursecredits' => (string)$cases[4]['pmcourse'][0]->credits,
                'id' => null,
                'classid' => null,
                'userid' => null,
                'enrolmenttime' => null,
                'completetime' => null,
                'endtime' => null,
                'completestatusid' => null,
                'grade' => null,
                'credits' => null,
                'locked' => null,
            ),
            // Case 4. Single user with a single enrolment into a Moodle course linked to multiple ELIS classes.
            $cases[4]['muser']->id.'_'.$cases[4]['mcourse'][0]->id.'_'.$cases[4]['pmclass'][1]->id => (object)array(
                'muid' => (string)$cases[4]['muser']->id,
                'username' => (string)$cases[4]['muser']->username,
                'cmid' => (string)$cases[4]['cuser']->id,
                'moodlecourseid' => (string)$cases[4]['mcourse'][0]->id,
                'pmclassid' => (string)$cases[4]['pmclass'][1]->id,
                'pmcourseid' => (string)$cases[4]['pmcourse'][0]->id,
                'pmcoursecompletiongrade' => (string)$cases[4]['pmcourse'][0]->completion_grade,
                'pmcoursecredits' => (string)$cases[4]['pmcourse'][0]->credits,
                'id' => null,
                'classid' => null,
                'userid' => null,
                'enrolmenttime' => null,
                'completetime' => null,
                'endtime' => null,
                'completestatusid' => null,
                'grade' => null,
                'credits' => null,
                'locked' => null,
            ),
            // Case 4. Single user with a single enrolment into a Moodle course linked to multiple ELIS classes.
            $cases[4]['muser']->id.'_'.$cases[4]['mcourse'][0]->id.'_'.$cases[4]['pmclass'][2]->id => (object)array(
                'muid' => (string)$cases[4]['muser']->id,
                'username' => (string)$cases[4]['muser']->username,
                'cmid' => (string)$cases[4]['cuser']->id,
                'moodlecourseid' => (string)$cases[4]['mcourse'][0]->id,
                'pmclassid' => (string)$cases[4]['pmclass'][2]->id,
                'pmcourseid' => (string)$cases[4]['pmcourse'][1]->id,
                'pmcoursecompletiongrade' => (string)$cases[4]['pmcourse'][1]->completion_grade,
                'pmcoursecredits' => (string)$cases[4]['pmcourse'][1]->credits,
                'id' => null,
                'classid' => null,
                'userid' => null,
                'enrolmenttime' => null,
                'completetime' => null,
                'endtime' => null,
                'completestatusid' => null,
                'grade' => null,
                'credits' => null,
                'locked' => null,
            ),
        );

        $this->assertNotEmpty($syncableusers);
        $this->assertEquals(count($syncableusers), count($expectedresult));
        $this->assertEquals(array_keys($syncableusers), array_keys($expectedresult));
        foreach ($expectedresult as $k => $v) {
            $this->assertArrayHasKey($k, $syncableusers);
            $this->assertEquals($v, $syncableusers[$k], $k.' not equal');
        }

        // Test individual user fetching.
        foreach ($cases as $i => $case) {
            $sync = new moodlesync();
            $rs = $sync->get_syncable_users($case['muser']->id);

            if ($i <= 4) {
                // These users must return something.
                $this->assertTrue($rs->valid());
                foreach ($rs as $i => $rec) {
                    $k = $rec->muid.'_'.$rec->moodlecourseid.'_'.$rec->pmclassid;
                    $this->assertArrayHasKey($k, $expectedresult);
                    $this->assertEquals($expectedresult[$k], $rec);
                }
            } else {
                // These users must not return anything.
                $this->assertEmpty($rs);
            }
        }

    }

    /**
     * Test get_elis_coursecompletion_grades method.
     */
    public function test_get_elis_coursecompletion_grades() {
        global $DB;
        $edatagenerator = new elis_program_datagenerator($DB);

        $pmcourse = $edatagenerator->create_course();
        $pmclass1 = $edatagenerator->create_pmclass(array('courseid' => $pmcourse->id));
        $pmclass2 = $edatagenerator->create_pmclass(array('courseid' => $pmcourse->id));

        $eusers = array();
        $musers = array();
        for ($i = 0; $i < 6; $i++) {
            $euser = $edatagenerator->create_user(array('username' => 'testuser'.$i, 'idnumber' => 'testuser'.$i));
            $muser = $this->getDataGenerator()->create_user(array('username' => 'testuser'.$i, 'idnumber' => 'testuser'.$i));
            $edatagenerator->assign_euser_to_muser($euser->id, $muser->id, $euser->idnumber);
            $eusers[] = $euser;
            $musers[] = $muser;
        }
        $eusernomoodleuser = $edatagenerator->create_user(array('username' => 'testuser5', 'idnumber' => 'testuser5'));

        // Create grades.

        // 1. One user one class one grade.
        $grade1 = new student_grade(array(
            'userid' => $eusers[0]->id,
            'classid' => $pmclass1->id,
            'completionid' => 1,
            'grade' => 71,
        ));
        $grade1->save();

        // 2. One user different class one grade.
        $grade2 = new student_grade(array(
            'userid' => $eusers[1]->id,
            'classid' => $pmclass2->id,
            'completionid' => 10,
            'grade' => 72,
        ));
        $grade2->save();

        // 3. One user first class one grade (this tests a subsequent call with a larger muid but smaller pmclassid).
        $grade3 = new student_grade(array(
            'userid' => $eusers[2]->id,
            'classid' => $pmclass1->id,
            'completionid' => 1,
            'grade' => 72,
        ));
        $grade3->save();

        // 4. One user one class two grades.
        $grade4 = new student_grade(array(
            'userid' => $eusers[3]->id,
            'classid' => $pmclass1->id,
            'completionid' => 1,
            'grade' => 73,
        ));
        $grade4->save();
        $grade5 = new student_grade(array(
            'userid' => $eusers[3]->id,
            'classid' => $pmclass1->id,
            'completionid' => 2,
            'grade' => 74,
        ));
        $grade5->save();

        // 5. One user two classes one grade in each.
        $grade6 = new student_grade(array(
            'userid' => $eusers[4]->id,
            'classid' => $pmclass1->id,
            'completionid' => 1,
            'grade' => 75,
        ));
        $grade6->save();
        $grade7 = new student_grade(array(
            'userid' => $eusers[4]->id,
            'classid' => $pmclass2->id,
            'completionid' => 10,
            'grade' => 76,
        ));
        $grade7->save();

        // 6. One user two classes one grade in one two grades in the other.
        $grade8 = new student_grade(array(
            'userid' => $eusers[5]->id,
            'classid' => $pmclass1->id,
            'completionid' => 1,
            'grade' => 77,
        ));
        $grade8->save();
        $grade9 = new student_grade(array(
            'userid' => $eusers[5]->id,
            'classid' => $pmclass2->id,
            'completionid' => 10,
            'grade' => 78,
        ));
        $grade9->save();
        $grade10 = new student_grade(array(
            'userid' => $eusers[5]->id,
            'classid' => $pmclass2->id,
            'completionid' => 11,
            'grade' => 79,
        ));
        $grade10->save();

        $sync = new moodlesync();

        // 1. One user one class one grade.
        $cmgrades = $sync->get_elis_coursecompletion_grades(0, $musers[0]->id, $pmclass1->id);
        $expected = (object)array(
            'userid' => (string)$eusers[0]->id,
            'classid' => (string)$pmclass1->id,
            'completionid' => '1',
            'grade' => '71.00000',
            'muid' => (string)$musers[0]->id,
            'pmclassid' => $pmclass1->id,
            'id' => $grade1->id,
            'locked' => '0',
            'timegraded' => '0',
            'timemodified' => $grade1->timemodified,
        );
        $this->assertNotEmpty($cmgrades);
        $this->assertArrayHasKey($expected->completionid, $cmgrades);
        $this->assertEquals($expected, $cmgrades[$expected->completionid]);

        // 2. One user different class one grade.
        $cmgrades = $sync->get_elis_coursecompletion_grades(0, $musers[1]->id, $pmclass2->id);
        $expected = (object)array(
            'userid' => (string)$eusers[1]->id,
            'classid' => (string)$pmclass2->id,
            'completionid' => '10',
            'grade' => '72.00000',
            'muid' => (string)$musers[1]->id,
            'pmclassid' => (string)$pmclass2->id,
            'id' => $grade2->id,
            'locked' => '0',
            'timegraded' => '0',
            'timemodified' => $grade2->timemodified,
        );
        $this->assertNotEmpty($cmgrades);
        $this->assertArrayHasKey($expected->completionid, $cmgrades);
        $this->assertEquals($expected, $cmgrades[$expected->completionid]);

        // 3. One user first class one grade (this tests a subsequent call with a larger muid but smaller pmclassid).
        $cmgrades = $sync->get_elis_coursecompletion_grades(0, $musers[2]->id, $pmclass1->id);
        $expected = (object)array(
            'userid' => (string)$eusers[2]->id,
            'classid' => (string)$pmclass1->id,
            'completionid' => '1',
            'grade' => '72.00000',
            'muid' => (string)$musers[2]->id,
            'pmclassid' => (string)$pmclass1->id,
            'id' => $grade3->id,
            'locked' => '0',
            'timegraded' => '0',
            'timemodified' => $grade3->timemodified,
        );
        $this->assertNotEmpty($cmgrades);
        $this->assertArrayHasKey($expected->completionid, $cmgrades);
        $this->assertEquals($expected, $cmgrades[$expected->completionid]);

        // 4. One user one class two grades.
        $cmgrades = $sync->get_elis_coursecompletion_grades(0, $musers[3]->id, $pmclass1->id);
        $expected1 = (object)array(
            'userid' => (string)$eusers[3]->id,
            'classid' => (string)$pmclass1->id,
            'completionid' => '1',
            'grade' => '73.00000',
            'muid' => (string)$musers[3]->id,
            'pmclassid' => (string)$pmclass1->id,
            'id' => $grade4->id,
            'locked' => '0',
            'timegraded' => '0',
            'timemodified' => $grade4->timemodified,
        );
        $expected2 = (object)array(
            'userid' => (string)$eusers[3]->id,
            'classid' => (string)$pmclass1->id,
            'completionid' => '2',
            'grade' => '74.00000',
            'muid' => (string)$musers[3]->id,
            'pmclassid' => (string)$pmclass1->id,
            'id' => $grade5->id,
            'locked' => '0',
            'timegraded' => '0',
            'timemodified' => $grade5->timemodified,
        );
        $this->assertNotEmpty($cmgrades);
        $this->assertArrayHasKey($expected1->completionid, $cmgrades);
        $this->assertEquals($expected1, $cmgrades[$expected1->completionid]);
        $this->assertArrayHasKey($expected2->completionid, $cmgrades);
        $this->assertEquals($expected2, $cmgrades[$expected2->completionid]);

        // 5. One user two classes one grade in each.
        $cmgrades = $sync->get_elis_coursecompletion_grades(0, $musers[4]->id, $pmclass1->id);
        $expected = (object)array(
            'userid' => (string)$eusers[4]->id,
            'classid' => (string)$pmclass1->id,
            'completionid' => '1',
            'grade' => '75.00000',
            'muid' => (string)$musers[4]->id,
            'pmclassid' => (string)$pmclass1->id,
            'id' => $grade6->id,
            'locked' => '0',
            'timegraded' => '0',
            'timemodified' => $grade6->timemodified,
        );
        $this->assertNotEmpty($cmgrades);
        $this->assertArrayHasKey($expected->completionid, $cmgrades);
        $this->assertEquals($expected, $cmgrades[$expected->completionid]);

        $cmgrades = $sync->get_elis_coursecompletion_grades(0, $musers[4]->id, $pmclass2->id);
        $expected = (object)array(
            'userid' => (string)$eusers[4]->id,
            'classid' => (string)$pmclass2->id,
            'completionid' => '10',
            'grade' => '76.00000',
            'muid' => (string)$musers[4]->id,
            'pmclassid' => (string)$pmclass2->id,
            'id' => $grade7->id,
            'locked' => '0',
            'timegraded' => '0',
            'timemodified' => $grade7->timemodified,
        );
        $this->assertNotEmpty($cmgrades);
        $this->assertArrayHasKey($expected->completionid, $cmgrades);
        $this->assertEquals($expected, $cmgrades[$expected->completionid]);

        // 6. One user two classes one grade in one two grades in the other.
        $cmgrades = $sync->get_elis_coursecompletion_grades(0, $musers[5]->id, $pmclass1->id);
        $expected1 = (object)array(
            'userid' => (string)$eusers[5]->id,
            'classid' => (string)$pmclass1->id,
            'completionid' => '1',
            'grade' => '77.00000',
            'muid' => (string)$musers[5]->id,
            'pmclassid' => (string)$pmclass1->id,
            'id' => $grade8->id,
            'locked' => '0',
            'timegraded' => '0',
            'timemodified' => $grade8->timemodified,
        );
        $this->assertNotEmpty($cmgrades);
        $this->assertArrayHasKey($expected1->completionid, $cmgrades);
        $this->assertEquals($expected1, $cmgrades[$expected1->completionid]);

        $cmgrades = $sync->get_elis_coursecompletion_grades(0, $musers[5]->id, $pmclass2->id);
        $expected1 = (object)array(
            'userid' => (string)$eusers[5]->id,
            'classid' => (string)$pmclass2->id,
            'completionid' => '10',
            'grade' => '78.00000',
            'muid' => (string)$musers[5]->id,
            'pmclassid' => (string)$pmclass2->id,
            'id' => $grade9->id,
            'locked' => '0',
            'timegraded' => '0',
            'timemodified' => $grade9->timemodified,
        );
        $expected2 = (object)array(
            'userid' => (string)$eusers[5]->id,
            'classid' => (string)$pmclass2->id,
            'completionid' => '11',
            'grade' => '79.00000',
            'muid' => (string)$musers[5]->id,
            'pmclassid' => (string)$pmclass2->id,
            'id' => $grade10->id,
            'locked' => '0',
            'timegraded' => '0',
            'timemodified' => $grade10->timemodified,
        );
        $this->assertNotEmpty($cmgrades);
        $this->assertArrayHasKey($expected1->completionid, $cmgrades);
        $this->assertEquals($expected1, $cmgrades[$expected1->completionid]);
    }

    /**
     * Test fetch_course_items method.
     */
    public function test_fetch_course_items() {
        global $DB;

        $gradeitems = array(
            (object)array(
                'courseid' => 2,
                'itemtype' => 'course',
            ),
            (object)array(
                'courseid' => 2,
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
            ),
            (object)array(
                'courseid' => 3,
                'itemtype' => 'course',
            ),
            (object)array(
                'courseid' => 3,
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
            ),
        );

        foreach ($gradeitems as $i => $gradeitem) {
            $gradeitems[$i]->id = $DB->insert_record('grade_items', $gradeitem);
        }

        $sync = new moodlesync();
        $requestedcourses = array(2, 3, 4);
        $courseitems = $sync->fetch_course_items($requestedcourses);

        $this->assertEquals(count($requestedcourses), count($courseitems));
        $courseids = array();
        foreach ($courseitems as $id => $item) {
            $this->assertTrue($item instanceof grade_item);
            $this->assertEquals('course', $item->itemtype);
            $courseids[] = $item->courseid;
        }
        sort($courseids);
        $this->assertEquals($requestedcourses, $courseids);
    }

    /**
     * Test get_courses_to_create_enrolments method.
     */
    public function test_get_courses_to_create_enrolments() {
        global $DB;

        $DB->insert_record(classmoodlecourse::TABLE, array(
            'classid' => 1,
            'moodlecourseid' => 1,
        ));
        $DB->insert_record(classmoodlecourse::TABLE, array(
            'classid' => 2,
            'moodlecourseid' => 2,
        ));
        $DB->insert_record(classmoodlecourse::TABLE, array(
            'classid' => 3,
            'moodlecourseid' => 3,
        ));
        $DB->insert_record(classmoodlecourse::TABLE, array(
            'classid' => 4,
            'moodlecourseid' => 3,
        ));
        $DB->insert_record(classmoodlecourse::TABLE, array(
            'classid' => 5,
            'moodlecourseid' => 4,
        ));

        $expectedresult = array(
            1 => 1,
            2 => 2,
            4 => 4,
        );

        $sync = new moodlesync();
        $actualresult = $sync->get_courses_to_create_enrolments();
        $this->assertEquals($actualresult, $expectedresult);
    }

    /**
     * Test create_enrolment_record method.
     */
    public function test_create_enrolment_record() {
        global $DB;

        $muserid = 3;
        $pmuserid = 100;
        $mcourseid = 5;
        $pmclassid = 101;

        $time = time();
        $time1 = $time - 100;
        $time2 = $time - 75;
        $time3 = $time - 50;

        // Test without moodle enrolment records.
        $sync = new moodlesync();
        $sturec = $sync->create_enrolment_record($pmuserid, $muserid, $mcourseid, $pmclassid, $time1);

        // Validate.
        $this->assertNotEmpty($sturec);
        $dbrec = $DB->get_record(student::TABLE, array('id' => $sturec->id));
        $this->assertNotEmpty($dbrec);
        $this->assertEquals($sturec, $dbrec);
        $expectedresult = array(
            'userid' => $pmuserid,
            'classid' => $pmclassid,
            'enrolmenttime' => (string)$time1,
            'completetime' => 0,
            'endtime' => 0,
            'completestatusid' => student::STUSTATUS_NOTCOMPLETE,
            'grade' => 0,
            'credits' => 0,
            'locked' => 0,
            'id' => $dbrec->id,
        );
        $this->assertEquals($expectedresult, (array)$sturec);

        // Set up moodle enrolment records.
        $enrolid = $DB->insert_record('enrol', array(
            'courseid' => $mcourseid,
            'enrol' => 'manual'
        ));
        $enrolid2 = $DB->insert_record('enrol', array(
            'courseid' => $mcourseid,
            'enrol' => 'manual2'
        ));

        $DB->insert_record('user_enrolments', array(
            'enrolid' => $enrolid,
            'userid' => $muserid,
            'timestart' => $time3
        ));
        $DB->insert_record('user_enrolments', array(
            'enrolid' => $enrolid2,
            'userid' => $muserid,
            'timestart' => $time2
        ));
        // Validate the method is looking only at enrolments for the given user.
        $DB->insert_record('user_enrolments', array(
            'enrolid' => $enrolid,
            'userid' => $muserid + 1,
            'timestart' => $time1
        ));
        // Validate the method is looking only at valid enrolids.
        $DB->insert_record('user_enrolments', array(
            'enrolid' => $enrolid2 + 1,
            'userid' => $muserid,
            'timestart' => $time1
        ));

        // Test with moodle enrolment records.
        $sync = new moodlesync();
        $sturec = $sync->create_enrolment_record($pmuserid, $muserid, $mcourseid, $pmclassid);

        // Validate.
        $this->assertNotEmpty($sturec);
        $dbrec = $DB->get_record(student::TABLE, array('id' => $sturec->id));
        $this->assertNotEmpty($dbrec);
        $this->assertEquals($sturec, $dbrec);
        $expectedresult = array(
            'userid' => $pmuserid,
            'classid' => $pmclassid,
            'enrolmenttime' => (string)$time2,
            'completetime' => 0,
            'endtime' => 0,
            'completestatusid' => student::STUSTATUS_NOTCOMPLETE,
            'grade' => 0,
            'credits' => 0,
            'locked' => 0,
            'id' => $dbrec->id,
        );
        $this->assertEquals($expectedresult, (array)$sturec);
    }

    /**
     * Test get_grade_and_completion_elements method.
     */
    public function test_get_grade_and_completion_elements() {
        global $DB;

        $pmcourseid1 = 100;
        $pmcourseid2 = 125;
        $mcourseid1 = 50;
        $mcourseid2 = 75;

        // Create coursecompletions.
        $ccmps = array(
                array(
                    'idnumber' => 'CMP_1_1',
                    'courseid' => $pmcourseid1,
                    'completion_grade' => 50,
                    'name' => 'CourseCompletion 1',
                ),
                array(
                    'idnumber' => 'CMP_1_2',
                    'courseid' => $pmcourseid1,
                    'completion_grade' => 50,
                    'name' => 'CourseCompletion 2',
                ),
                array(
                    'idnumber' => 'CMP_1_3',
                    'courseid' => $pmcourseid1,
                    'completion_grade' => 50,
                    'name' => 'CourseCompletion 3',
                ),
                array(
                    'idnumber' => 'CMP_unlinked',
                    'courseid' => $pmcourseid1,
                    'completion_grade' => 50,
                    'name' => 'Unlinked CourseCompletion',
                ),
                array(
                    'idnumber' => 'CMP_2_1',
                    'courseid' => $pmcourseid2,
                    'completion_grade' => 50,
                    'name' => 'CourseCompletion 4',
                ),
                array(
                    'idnumber' => 'CMP_2_2',
                    'courseid' => $pmcourseid2,
                    'completion_grade' => 50,
                    'name' => 'CourseCompletion 5',
                ),
                array(
                    'idnumber' => 'CMP_2_3',
                    'courseid' => $pmcourseid2,
                    'completion_grade' => 50,
                    'name' => 'CourseCompletion 6',
                ),
        );
        foreach ($ccmps as $i => $ccmp) {
            $ccmps[$i]['id'] = $DB->insert_record(coursecompletion::TABLE, $ccmp);
        }

        // Create elis classes.
        $pmclasses = array(
                array(
                    'courseid' => $pmcourseid1,
                    'idnumber' => 'ELIS_CLS_1'
                ),
                array(
                    'courseid' => $pmcourseid2,
                    'idnumber' => 'ELIS_CLS_2'
                ),
        );
        foreach ($pmclasses as $i => $pmclass) {
            $pmclasses[$i]['id'] = $DB->insert_record(pmclass::TABLE, $pmclass);
        }

        // Create classmoodlecourses.
        $cmcs = array(
                array(
                    'classid' => $pmclasses[0]['id'],
                    'moodlecourseid' => $mcourseid1,
                ),
                array(
                    'classid' => $pmclasses[1]['id'],
                    'moodlecourseid' => $mcourseid2,
                )
        );
        foreach ($cmcs as $i => $cmc) {
            $cmcs[$i]['id'] = $DB->insert_record(classmoodlecourse::TABLE, $cmc);
        }

        // Create course modules.
        $crsmods = array(
                // Course module linked to first coursecompletion for first class.
                array('idnumber' => $ccmps[0]['idnumber']),
                // Course module linked to second coursecompletion for first class.
                array('idnumber' => $ccmps[1]['idnumber']),
                // Course module linked to non-existent coursecompletion for first class.
                array('idnumber' => 'CMP_1_4'),

                // Course module linked to first coursecompletion for second class.
                array('idnumber' => $ccmps[4]['idnumber']),
                // Course module linked to second coursecompletion for second class.
                array('idnumber' => $ccmps[5]['idnumber']),
                // Course module linked to non-existent coursecompletion for second class.
                array('idnumber' => 'CMP_2_4'),
        );
        foreach ($crsmods as $i => $crsmod) {
            $crsmods[$i]['id'] = $DB->insert_record('course_modules', $crsmod);
        }

        // Create Grade Items.
        $createdgis = array(
                // FIRST PMCLASS. Grade item linked to first coursecompletion by idnumber. (1/2 links to first coursecompletion).
                array(
                    'courseid' => $mcourseid1,
                    'idnumber' => $ccmps[0]['idnumber'],
                ),
                // FIRST PMCLASS. Grade item linked to first coursecompletion by course_modules foreign key. (2/2 links to first coursecompletion).
                array(
                    'courseid' => $mcourseid1,
                    'idnumber' => $crsmods[0]['id'],
                ),
                // FIRST PMCLASS. Grade item linked to second coursecompletion by idnumber. (Only link to second coursecompletion).
                array(
                    'courseid' => $mcourseid1,
                    'idnumber' => $crsmods[1]['id'],
                ),
                // FIRST PMCLASS. Grade item linked to third coursecompletion by idnumber. (Only link to third coursecompletion).
                array(
                    'courseid' => $mcourseid1,
                    'idnumber' => $ccmps[2]['idnumber'],
                ),
                // FIRST PMCLASS. Grade item linked to course_module which is linked to non-existent coursecompletion.
                array(
                    'courseid' => $mcourseid1,
                    'idnumber' => $crsmods[2]['id'],
                ),
                // FIRST PMCLASS. Grade item linked to course_module which is linked to non-existent coursecompletion.
                array(
                    'courseid' => $mcourseid1,
                    'idnumber' => 'CMP_1_5',
                ),
                // FIRST PMCLASS. Grade item linked to first coursecompletion, but for wrong course.
                array(
                    'courseid' => $mcourseid1 + 1,
                    'idnumber' => $ccmps[0]['idnumber'],
                ),
                // FIRST PMCLASS. Grade item linked to non-existent coursecompletion, and for wrong course.
                array(
                    'courseid' => $mcourseid1 + 1,
                    'idnumber' => 'CMP_1_6',
                ),

                // SECOND PMCLASS. Grade item linked to first coursecompletion by idnumber. (1/2 links to first coursecompletion).
                array(
                    'courseid' => $mcourseid2,
                    'idnumber' => $ccmps[4]['idnumber'],
                ),
                // SECOND PMCLASS. Grade item linked to first coursecompletion by course_modules foreign key. (2/2 links to first coursecompletion).
                array(
                    'courseid' => $mcourseid2,
                    'idnumber' => $crsmods[3]['id'],
                ),
                // SECOND PMCLASS. Grade item linked to second coursecompletion by idnumber. (Only link to second coursecompletion).
                array(
                    'courseid' => $mcourseid2,
                    'idnumber' => $crsmods[4]['id'],
                ),
                // SECOND PMCLASS. Grade item linked to third coursecompletion by idnumber. (Only link to third coursecompletion).
                array(
                    'courseid' => $mcourseid2,
                    'idnumber' => $ccmps[6]['idnumber'],
                ),
                // SECOND PMCLASS. Grade item linked to course_module which is linked to non-existent coursecompletion.
                array(
                    'courseid' => $mcourseid2,
                    'idnumber' => $crsmods[5]['id'],
                ),
                // SECOND PMCLASS. Grade item linked to course_module which is linked to non-existent coursecompletion.
                array(
                    'courseid' => $mcourseid2,
                    'idnumber' => 'CMP_1_5',
                ),
                // SECOND PMCLASS. Grade item linked to first coursecompletion, but for wrong course.
                array(
                    'courseid' => $mcourseid2 + 1,
                    'idnumber' => $ccmps[4]['idnumber'],
                ),
                // SECOND PMCLASS. Grade item linked to non-existent coursecompletion, and for wrong course.
                array(
                    'courseid' => $mcourseid2 + 1,
                    'idnumber' => 'CMP_1_6',
                ),
        );
        foreach ($createdgis as $i => $gitocreate) {
            $createdgis[$i]['id'] = $DB->insert_record('grade_items', $gitocreate);
        }

        // Test.
        $sync = new moodlesync();
        list($gis, $linkedcompelems, $compelems) = $sync->get_grade_and_completion_elements();

        $expectedgis = array(
            $mcourseid1 => array(
                    $createdgis[0]['id'] => (object)array(
                        'id' => $createdgis[0]['id'],
                        'grademax' => 100.00000
                    ),
                    $createdgis[1]['id'] => (object)array(
                        'id' => $createdgis[1]['id'],
                        'grademax' => 100.00000
                    ),
                    $createdgis[2]['id'] => (object)array(
                        'id' => $createdgis[2]['id'],
                        'grademax' => 100.00000
                    ),
                    $createdgis[3]['id'] => (object)array(
                        'id' => $createdgis[3]['id'],
                        'grademax' => 100.00000
                    ),
            ),
            $mcourseid2 => array(
                    $createdgis[8]['id'] => (object)array(
                        'id' => $createdgis[8]['id'],
                        'grademax' => 100.00000
                    ),
                    $createdgis[9]['id'] => (object)array(
                        'id' => $createdgis[9]['id'],
                        'grademax' => 100.00000
                    ),
                    $createdgis[10]['id'] => (object)array(
                        'id' => $createdgis[10]['id'],
                        'grademax' => 100.00000
                    ),
                    $createdgis[11]['id'] => (object)array(
                        'id' => $createdgis[11]['id'],
                        'grademax' => 100.00000
                    ),
            )
        );
        $this->assertEquals($expectedgis, $gis);

        $expectedlinkedcompelems = array(
            $pmcourseid1 => array(
                    $createdgis[0]['id'] => (object)array(
                        'id' => $ccmps[0]['id'],
                        'completion_grade' => 50,
                    ),
                    $createdgis[1]['id'] => (object)array(
                        'id' => $ccmps[0]['id'],
                        'completion_grade' => 50,
                    ),
                    $createdgis[2]['id'] => (object)array(
                        'id' => $ccmps[1]['id'],
                        'completion_grade' => 50,
                    ),
                    $createdgis[3]['id'] => (object)array(
                        'id' => $ccmps[2]['id'],
                        'completion_grade' => 50,
                    )
            ),
            $pmcourseid2 => array(
                    $createdgis[8]['id'] => (object)array(
                        'id' => $ccmps[4]['id'],
                        'completion_grade' => 50,
                    ),
                    $createdgis[9]['id'] => (object)array(
                        'id' => $ccmps[4]['id'],
                        'completion_grade' => 50,
                    ),
                    $createdgis[10]['id'] => (object)array(
                        'id' => $ccmps[5]['id'],
                        'completion_grade' => 50,
                    ),
                    $createdgis[11]['id'] => (object)array(
                        'id' => $ccmps[6]['id'],
                        'completion_grade' => 50,
                    )
            )
        );

        $this->assertEquals($expectedlinkedcompelems, $linkedcompelems);

        $expectedcompelems = array(
            $pmcourseid1 => array(
                    $ccmps[0]['id'] => (object)array(
                        'id' => $ccmps[0]['id'],
                    ),
                    $ccmps[1]['id'] => (object)array(
                        'id' => $ccmps[1]['id'],
                    ),
                    $ccmps[2]['id'] => (object)array(
                        'id' => $ccmps[2]['id'],
                    ),
                    $ccmps[3]['id'] => (object)array(
                        'id' => $ccmps[3]['id'],
                    ),
            ),
            $pmcourseid2 => array(
                    $ccmps[4]['id'] => (object)array(
                        'id' => $ccmps[4]['id'],
                    ),
                    $ccmps[5]['id'] => (object)array(
                        'id' => $ccmps[5]['id'],
                    ),
                    $ccmps[6]['id'] => (object)array(
                        'id' => $ccmps[6]['id'],
                    ),
            )
        );
        $this->assertEquals($expectedcompelems, $compelems);

    }

    /**
     * Dataprovider for test_sync_coursegrade.
     *
     * @return array Array of test parameters.
     */
    public function dataprovider_sync_coursegrade() {
        $timenow = time();
        return array(
                // A course with no completion elements and a student with a grade below the completiongrade.
                // Should sync the grade, but leave the student as notcomplete.
                array(
                        // Sturec.
                        array(
                            'completestatusid' => STUSTATUS_NOTCOMPLETE,
                        ),
                        // Coursegradeitem.
                        array(
                            'courseid' => 100,
                            'itemtype' => 'course',
                            'grademax' => 100
                        ),
                        // Usercoursegradeddata.
                        array(
                            'finalgrade' => 75
                        ),
                        // Compelements.
                        array(),
                        // Completiongrade.
                        80,
                        // Credits.
                        12,
                        // Timenow.
                        $timenow,
                        // Expectedsyncredstudent.
                        array(
                            'completetime' => '0',
                            'completestatusid' => (string)STUSTATUS_NOTCOMPLETE,
                            'grade' => '75.00000',
                            'credits' => '0.00'
                        ),
                ),
                // A course with no completion elements and a student with a grade above the completiongrade.
                // Should sync the grade from moodle, set the student to passed, and assign credits + completiontime.
                array(
                        // Sturec.
                        array(
                            'completestatusid' => STUSTATUS_NOTCOMPLETE,
                        ),
                        // Coursegradeitem.
                        array(
                            'courseid' => 100,
                            'itemtype' => 'course',
                            'grademax' => 100
                        ),
                        // Usercoursegradeddata.
                        array(
                            'finalgrade' => 75
                        ),
                        // Compelements.
                        array(),
                        // Completiongrade.
                        50,
                        // Credits.
                        12,
                        // Timenow.
                        $timenow,
                        // Expectedsyncredstudent.
                        array(
                            'completetime' => (string)$timenow,
                            'completestatusid' => (string)STUSTATUS_PASSED,
                            'grade' => '75.00000',
                            'credits' => '12.00'
                        ),
                ),
                // A course with completion elements and a student with a grade below the completiongrade.
                // Should sync the grade, but leave the student notcomplete.
                array(
                        // Sturec.
                        array(
                            'completestatusid' => STUSTATUS_NOTCOMPLETE,
                        ),
                        // Coursegradeitem.
                        array(
                            'courseid' => 100,
                            'itemtype' => 'course',
                            'grademax' => 100
                        ),
                        // Usercoursegradeddata.
                        array(
                            'finalgrade' => 75
                        ),
                        // Compelements.
                        array(
                                array(
                                    'idnumber' => 'CCMP1',
                                    'completion_grade' => 76
                                )
                        ),
                        // Completiongrade.
                        80,
                        // Credits.
                        12,
                        // Timenow.
                        $timenow,
                        // Expectedsyncredstudent.
                        array(
                            'completetime' => '0',
                            'completestatusid' => (string)STUSTATUS_NOTCOMPLETE,
                            'grade' => '75.00000',
                            'credits' => '0.00'
                        ),
                ),
                // A course with completion elements and a student with a grade above the completiongrade.
                // Should sync the grade, but leave the student notcomplete.
                array(
                        // Sturec.
                        array(
                            'completestatusid' => STUSTATUS_NOTCOMPLETE,
                        ),
                        // Coursegradeitem.
                        array(
                            'courseid' => 100,
                            'itemtype' => 'course',
                            'grademax' => 100
                        ),
                        // Usercoursegradeddata.
                        array(
                            'finalgrade' => 75
                        ),
                        // Compelements.
                        array(
                                array(
                                    'idnumber' => 'CCMP1',
                                    'completion_grade' => 76
                                )
                        ),
                        // Completiongrade.
                        50,
                        // Credits.
                        12,
                        // Timenow.
                        $timenow,
                        // Expectedsyncredstudent.
                        array(
                            'completetime' => '0',
                            'completestatusid' => (string)STUSTATUS_NOTCOMPLETE,
                            'grade' => '75.00000',
                            'credits' => '0.00'
                        ),
                ),
                // A locked student with different moodle grade data.
                // Should not sync anything.
                array(
                        // Sturec.
                        array(
                            'completestatusid' => STUSTATUS_NOTCOMPLETE,
                            'locked' => 1,
                        ),
                        // Coursegradeitem.
                        array(
                            'courseid' => 100,
                            'itemtype' => 'course',
                            'grademax' => 100
                        ),
                        // Usercoursegradeddata.
                        array(
                            'finalgrade' => 75
                        ),
                        // Compelements.
                        array(),
                        // Completiongrade.
                        50,
                        // Credits.
                        12,
                        // Timenow.
                        $timenow,
                        // Expectedsyncredstudent.
                        array(
                            'completetime' => '0',
                            'completestatusid' => (string)STUSTATUS_NOTCOMPLETE,
                            'grade' => '0.00000',
                            'credits' => '0.00',
                            'locked' => '1',
                        ),
                ),
                // Non-100 grademax (not-pass).
                //   - No completion elements
                //   - A student with a nominal grade above the completiongrade, but which will be below when shaped.
                //   - A moodle course with a non-100 grademax.
                // Should shape the moodle grade, sync it, do not update completestatusid or credits.
                array(
                        // Sturec.
                        array(
                            'completestatusid' => STUSTATUS_NOTCOMPLETE,
                        ),
                        // Coursegradeitem.
                        array(
                            'courseid' => 100,
                            'itemtype' => 'course',
                            'grademax' => 200
                        ),
                        // Usercoursegradeddata.
                        array(
                            'finalgrade' => 80
                        ),
                        // Compelements.
                        array(),
                        // Completiongrade.
                        50,
                        // Credits.
                        12,
                        // Timenow.
                        $timenow,
                        // Expectedsyncredstudent.
                        array(
                            'completetime' => '0',
                            'completestatusid' => (string)STUSTATUS_NOTCOMPLETE,
                            'grade' => '40.00000',
                            'credits' => '0.00'
                        ),
                ),
                // Non-100 grademax (pass).
                //   - No completion elements
                //   - A student with a nominal grade below the completiongrade, but which will be above when shaped.
                //   - A moodle course with a non-100 grademax.
                // Should shape the moodle grade, sync it, set the student to passed, and assign credits + completiontime.
                array(
                        // Sturec.
                        array(
                            'completestatusid' => STUSTATUS_NOTCOMPLETE,
                        ),
                        // Coursegradeitem.
                        array(
                            'courseid' => 100,
                            'itemtype' => 'course',
                            'grademax' => 60
                        ),
                        // Usercoursegradeddata.
                        array(
                            'finalgrade' => 45
                        ),
                        // Compelements.
                        array(),
                        // Completiongrade.
                        50,
                        // Credits.
                        12,
                        // Timenow.
                        $timenow,
                        // Expectedsyncredstudent.
                        array(
                            'completetime' => (string)$timenow,
                            'completestatusid' => (string)STUSTATUS_PASSED,
                            'grade' => '75.00000',
                            'credits' => '12.00'
                        ),
                ),
        );
    }

    /**
     * Test sync_coursegrade method.
     *
     * @dataProvider dataprovider_sync_coursegrade
     * @param array $sturec Array of parameters to create student record.
     * @param array $coursegradeitem Array of parameters to create course grade_item.
     * @param array $coursegradegrade Array of parameters to create course grade_grade.
     * @param array $compelements Array of completion elements for the linked ELIS course/class.
     * @param array $completiongrade The grade a student must exceed to be marked as passed.
     * @param array $credits The number of credits a successful student will receive.
     * @param array $timenow The current timestamp, to enable testing with time.
     * @param array $expectedsyncedstudent Array of parameters we expect on the student grade record.
     */
    public function test_sync_coursegrade($sturec, $coursegradeitem, $coursegradegrade, array $compelements = array(),
                                          $completiongrade, $credits, $timenow, $expectedsyncedstudent) {
        global $DB;
        $crs = new course(array(
            'idnumber' => 'CRS1',
            'name' => 'Course 1',
            'syllabus' => '',
        ));
        $crs->save();

        $cls = new pmclass(array(
            'courseid' => $crs->id,
            'idnumber' => 'CLS1',
        ));
        $cls->save();

        $usr = new user(array(
            'username' => 'test1',
            'idnumber' => 'test2',
            'firstname' => 'test',
            'lastname' => 'user',
            'email' => 'testuser@example.com',
            'country' => 'CA'
        ));
        $usr->save();

        $sturec['classid'] = $cls->id;
        $sturec['userid'] = $usr->id;
        $sturec = new student($sturec);
        $sturec->save();
        $sturec = new student($sturec->id);
        $sturec->load();
        $sturec = $sturec->to_object();

        $musr = $this->getDataGenerator()->create_user();

        $coursegradeitem = new grade_item($coursegradeitem, false);
        $coursegradeitem->insert();

        $coursegradegrade['userid'] = $musr->id;
        $coursegradegrade['itemid'] = $coursegradeitem->id;
        $coursegradegrade = new grade_grade($coursegradegrade, false);
        $coursegradegrade->insert();

        foreach ($compelements as $i => $compelement) {
            $compelement['courseid'] = $crs->id;
            $compelements[$i] = new coursecompletion($compelement);
            $compelements[$i]->save();
        }

        $sync = new moodlesync();
        $sync->sync_coursegrade($sturec, $coursegradeitem, $coursegradegrade, $compelements, $completiongrade, $credits, $timenow);

        $syncedsturec = $DB->get_record(student::TABLE, array('id' => $sturec->id));
        $expectedrec = array(
            'id' => $sturec->id,
            'classid' => (string)$cls->id,
            'userid' => (string)$usr->id,
            'enrolmenttime' => '0',
            'completetime' => 0,
            'endtime' => '0',
            'completestatusid' => (string)STUSTATUS_NOTCOMPLETE,
            'grade' => '0.00000',
            'credits' => '0.00',
            'locked' => '0',
        );
        $expectedrec = (object)array_merge($expectedrec, $expectedsyncedstudent);
        $this->assertEquals($expectedrec, $syncedsturec);
    }

    /**
     * Dataprovider for test_sync_completionelements.
     *
     * @return array Array of test parameters.
     */
    public function dataprovider_sync_completionelements() {
        $timenow = time();
        return array(
                // Test successful sync to preexisting student_grade.
                array(
                        // Grade_item.
                        array(
                            'itemtype' => 'mod',
                            'itemmodule' => 'assignment',
                            'courseid' => 88,
                            'grademax' => 100,
                            'idnumber' => 'CMP1',
                        ),
                        // Grade_grade.
                        array(
                            'finalgrade' => 80,
                            'timemodified' => $timenow - 50
                        ),
                        // Coursecompletion.
                        array(
                            'idnumber' => 'CMP1',
                            'name' => 'Completion1',
                            'completion_grade' => 50
                        ),
                        // Studentgrade.
                        array(
                            'timegraded' => $timenow - 100,
                        ),
                        // Timenow.
                        $timenow,
                        // Expectedstudentgrade.
                        array(
                            'grade' => 80,
                            'locked' => 1,
                            'timegraded' => $timenow - 50,
                            'timemodified' => $timenow
                        ),
                ),
                // Test successful sync to new student_grade.
                array(
                        // Grade_item.
                        array(
                            'itemtype' => 'mod',
                            'itemmodule' => 'assignment',
                            'courseid' => 88,
                            'grademax' => 100,
                            'idnumber' => 'CMP1',
                        ),
                        // Grade_grade.
                        array(
                            'finalgrade' => 80,
                            'timemodified' => $timenow - 50
                        ),
                        // Coursecompletion.
                        array(
                            'idnumber' => 'CMP1',
                            'name' => 'Completion1',
                            'completion_grade' => 50
                        ),
                        // Studentgrade.
                        false,
                        // Timenow.
                        $timenow,
                        // Expectedstudentgrade.
                        array(
                            'grade' => 80,
                            'locked' => 1,
                            'timegraded' => $timenow - 50,
                            'timemodified' => $timenow
                        ),
                ),
                // Test grades for grade_items with non-100 grademax are shaped correctly.
                array(
                        // Grade_item.
                        array(
                            'itemtype' => 'mod',
                            'itemmodule' => 'assignment',
                            'courseid' => 88,
                            'grademax' => 200,
                            'idnumber' => 'CMP1',
                        ),
                        // Grade_grade.
                        array(
                            'finalgrade' => 140,
                            'timemodified' => $timenow - 50
                        ),
                        // Coursecompletion.
                        array(
                            'idnumber' => 'CMP1',
                            'name' => 'Completion1',
                            'completion_grade' => 50
                        ),
                        // Studentgrade.
                        array(
                            'timegraded' => $timenow - 100,
                        ),
                        // Timenow.
                        $timenow,
                        // Expectedstudentgrade.
                        array(
                            'grade' => 70,
                            'locked' => 1,
                            'timegraded' => $timenow - 50,
                            'timemodified' => $timenow
                        ),
                ),
                // Test grades for grade_items with non-100 grademax are shaped correctly (and if the resulting grade is below
                // the completion_grade the student_grade is not locked).
                array(
                        // Grade_item.
                        array(
                            'itemtype' => 'mod',
                            'itemmodule' => 'assignment',
                            'courseid' => 88,
                            'grademax' => 80,
                            'idnumber' => 'CMP1',
                        ),
                        // Grade_grade.
                        array(
                            'finalgrade' => 30,
                            'timemodified' => $timenow - 50
                        ),
                        // Coursecompletion.
                        array(
                            'idnumber' => 'CMP1',
                            'name' => 'Completion1',
                            'completion_grade' => 50
                        ),
                        // Studentgrade.
                        array(
                            'timegraded' => $timenow - 100,
                        ),
                        // Timenow.
                        $timenow,
                        // Expectedstudentgrade.
                        array(
                            'grade' => 37.5,
                            'locked' => 0,
                            'timegraded' => $timenow - 50,
                            'timemodified' => $timenow
                        ),
                ),
                // Test if grade higher than completion grade, preexisting student_grade is locked.
                array(
                        // Grade_item.
                        array(
                            'itemtype' => 'mod',
                            'itemmodule' => 'assignment',
                            'courseid' => 88,
                            'grademax' => 100,
                            'idnumber' => 'CMP1',
                        ),
                        // Grade_grade.
                        array(
                            'finalgrade' => 80,
                            'timemodified' => $timenow - 50
                        ),
                        // Coursecompletion.
                        array(
                            'idnumber' => 'CMP1',
                            'name' => 'Completion1',
                            'completion_grade' => 50
                        ),
                        // Studentgrade.
                        array(
                            'timegraded' => $timenow - 100,
                        ),
                        // Timenow.
                        $timenow,
                        // Expectedstudentgrade.
                        array(
                            'grade' => 80,
                            'locked' => 1,
                            'timegraded' => $timenow - 50,
                            'timemodified' => $timenow
                        ),
                ),
                // Test if grade lower than completion grade, preexisting student_grade is not locked.
                array(
                        // Grade_item.
                        array(
                            'itemtype' => 'mod',
                            'itemmodule' => 'assignment',
                            'courseid' => 88,
                            'grademax' => 100,
                            'idnumber' => 'CMP1',
                        ),
                        // Grade_grade.
                        array(
                            'finalgrade' => 45,
                            'timemodified' => $timenow - 50
                        ),
                        // Coursecompletion.
                        array(
                            'idnumber' => 'CMP1',
                            'name' => 'Completion1',
                            'completion_grade' => 50
                        ),
                        // Studentgrade.
                        array(
                            'timegraded' => $timenow - 100,
                        ),
                        // Timenow.
                        $timenow,
                        // Expectedstudentgrade.
                        array(
                            'grade' => 45,
                            'locked' => 0,
                            'timegraded' => $timenow - 50,
                            'timemodified' => $timenow
                        ),
                ),
                // Test doesn't sync to locked student_grade.
                array(
                        // Grade_item.
                        array(
                            'itemtype' => 'mod',
                            'itemmodule' => 'assignment',
                            'courseid' => 88,
                            'grademax' => 100,
                            'idnumber' => 'CMP1',
                        ),
                        // Grade_grade.
                        array(
                            'finalgrade' => 80,
                            'timemodified' => $timenow - 50
                        ),
                        // Coursecompletion.
                        array(
                            'idnumber' => 'CMP1',
                            'name' => 'Completion1',
                            'completion_grade' => 50
                        ),
                        // Studentgrade.
                        array(
                            'locked' => 1,
                            'grade' => 70,
                            'timegraded' => $timenow - 100,
                        ),
                        // Timenow.
                        $timenow,
                        // Expectedstudentgrade.
                        array(
                            'grade' => 70,
                            'locked' => 1,
                            'timegraded' => $timenow - 100,
                        ),
                ),
                // Test doesn't sync if student_grade timegraded > grade_grade->get_dategraded().
                array(
                        // Grade_item.
                        array(
                            'itemtype' => 'mod',
                            'itemmodule' => 'assignment',
                            'courseid' => 88,
                            'grademax' => 100,
                            'idnumber' => 'CMP1',
                        ),
                        // Grade_grade.
                        array(
                            'finalgrade' => 80,
                            'timemodified' => $timenow - 100
                        ),
                        // Coursecompletion.
                        array(
                            'idnumber' => 'CMP1',
                            'name' => 'Completion1',
                            'completion_grade' => 50
                        ),
                        // Studentgrade.
                        array(
                            'locked' => 1,
                            'grade' => 70,
                            'timegraded' => $timenow - 50,
                        ),
                        // Timenow.
                        $timenow,
                        // Expectedstudentgrade.
                        array(
                            'grade' => 70,
                            'locked' => 1,
                            'timegraded' => $timenow - 50,
                        ),
                ),
        );
    }

    /**
     * Test sync_completionelements method.
     *
     * @dataProvider dataprovider_sync_completionelements
     * @param array $gradeitem Array of parameters to create grade_item.
     * @param array $gradegrade Array of parameters to create grade_grade.
     * @param array $coursecompletion Array of parameters to create coursecompletion.
     * @param array $studentgrade Array of parameters to create student_grade.
     * @param int $timenow Current timestamp to enable testing with time.
     * @param array $expectedstudentgrade Array of parameters we expect to be set in the student_grade.
     */
    public function test_sync_completionelements($gradeitem, $gradegrade, $coursecompletion, $studentgrade, $timenow, $expectedstudentgrade) {
        global $DB;

        $sync = new moodlesync();

        // Test data setup.
        $crs = new course(array(
            'idnumber' => 'CRS1',
            'name' => 'Course 1',
            'syllabus' => '',
        ));
        $crs->save();

        $cls = new pmclass(array(
            'courseid' => $crs->id,
            'idnumber' => 'CLS1',
        ));
        $cls->save();

        $usr = new user(array(
            'username' => 'test1',
            'idnumber' => 'test2',
            'firstname' => 'test',
            'lastname' => 'user',
            'email' => 'testuser@example.com',
            'country' => 'CA'
        ));
        $usr->save();

        $musr = $this->getDataGenerator()->create_user();

        $gradeitem = new grade_item($gradeitem, false);
        $gradeitem->insert();

        $gradegrade['itemid'] = $gradeitem->id;
        $gradegrade['userid'] = $musr->id;
        $gradegrade = new grade_grade($gradegrade, false);
        $gradegrade->insert();

        $coursecompletion['courseid'] = $crs->id;
        $coursecompletion = new coursecompletion($coursecompletion);
        $coursecompletion->save();

        if ($studentgrade !== false) {
            $studentgrade['classid'] = $cls->id;
            $studentgrade['userid'] = $usr->id;
            $studentgrade['completionid'] = $coursecompletion->id;
            $studentgrade = new student_grade($studentgrade);
            $studentgrade->save();
            $studentgrade = new student_grade($studentgrade->id);
            $studentgrade->load();
        }

        // Method parameter setup.
        $causer = (object)array(
            'cmid' => $usr->id,
            'pmclassid' => $cls->id,
        );

        $gis = array(
            $gradeitem->id => (object)array(
                'id' => $gradeitem->id,
                'grademax' => $gradeitem->grademax,
            )
        );

        $compelements = array(
            $gradeitem->id => (object)array(
                'id' => $coursecompletion->id,
                'completion_grade' => $coursecompletion->completion_grade
            )
        );

        $moodlegrades = array(
            $gradeitem->id => $gradegrade
        );

        if ($studentgrade !== false) {
            $cmgrades = array(
                $coursecompletion->id => $studentgrade->to_object()
            );
        } else {
            $cmgrades = array();
        }

        $sync->sync_completionelements($causer, $gis, $compelements, $moodlegrades, $cmgrades, $timenow);

        $actualstudentgrade = false;
        if ($studentgrade !== false) {
            $actualstudentgrade = $DB->get_record(student_grade::TABLE, array('id' => $studentgrade->id));
        } else {
            $actualstudentgrades = $DB->get_records(student_grade::TABLE, array(), 'id DESC');
            if (!empty($actualstudentgrades)) {
                $actualstudentgrade = array_shift($actualstudentgrades);
            }
        }

        if ($actualstudentgrade !== false) {
            if ($expectedstudentgrade !== false) {
                $expectedstudentgrade['id'] = $actualstudentgrade->id;
                $expectedstudentgrade['classid'] = $cls->id;
                $expectedstudentgrade['userid'] = $usr->id;
                $expectedstudentgrade['completionid'] = $coursecompletion->id;

                // This is here for tests where we can't reliably predetermine timemodified (i.e. no-sync cases).
                if (!isset($expectedstudentgrade['timemodified'])) {
                    $expectedstudentgrade['timemodified'] = $actualstudentgrade->timemodified;
                }
                $expectedstudentgrade = (object)$expectedstudentgrade;
                $this->assertEquals($expectedstudentgrade, $actualstudentgrade);
            } else {
                $this->assertTrue(false, 'A student_grade was created when one was not expected.');
            }
        } else {
            // If $expectedstudentgrade is false we were expected no grade to be created. If not, we have a problem.
            if ($expectedstudentgrade !== false) {
                $this->assertTrue(false, 'No student_grade created when one was expected');
            } else {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * Test get_moodlegrades method.
     */
    public function test_get_moodlegrades() {
        global $DB;

        // Create grade_items.
        $gradeitems = array();
        foreach (array(10, 20) as $courseid) {
            $gradeitems[$courseid]['course'] = (object)array(
                'courseid' => $courseid,
                'itemname' => null,
                'itemtype' => 'course',
                'idnumber' => null,
            );
            $gradeitems[$courseid]['course']->id = $DB->insert_record('grade_items', $gradeitems[$courseid]['course']);

            $gradeitems[$courseid]['optionalexam'] = (object)array(
                'courseid' => $courseid,
                'itemname' => 'Optional Exam',
                'itemtype' => 'mod',
                'itemmodule' => 'assignment',
                'idnumber' => 'optionalexam',
            );
            $gradeitems[$courseid]['optionalexam']->id = $DB->insert_record('grade_items', $gradeitems[$courseid]['optionalexam']);

            $gradeitems[$courseid]['midtermexam'] = (object)array(
                'courseid' => $courseid,
                'itemname' => 'Midterm Exam',
                'itemtype' => 'mod',
                'itemmodule' => 'assignment',
                'idnumber' => 'midtermexam',
            );
            $gradeitems[$courseid]['midtermexam']->id = $DB->insert_record('grade_items', $gradeitems[$courseid]['midtermexam']);

            $gradeitems[$courseid]['finalexam'] = (object)array(
                'courseid' => $courseid,
                'itemname' => 'Final Exam',
                'itemtype' => 'mod',
                'itemmodule' => 'assignment',
                'idnumber' => 'finalexam',
            );
            $gradeitems[$courseid]['finalexam']->id = $DB->insert_record('grade_items', $gradeitems[$courseid]['finalexam']);
        }

        // Create grade_grades.
        $gradegrades = array();
        $i = 60;
        foreach (array(100, 110) as $userid) {
            $usergrades = array();
            foreach ($gradeitems as $courseid => $items) {
                $usercoursegrades = array();
                foreach ($items as $item) {
                    if ($item->idnumber !== 'optionalexam') {
                        $usercoursegrades[$item->id] = (object)array(
                            'itemid' => $item->id,
                            'userid' => $userid,
                            'finalgrade' => $i,
                        );
                        $usercoursegrades[$item->id]->id = $DB->insert_record('grade_grades', $usercoursegrades[$item->id]);
                        $i++;
                    }
                }
                $usergrades[$courseid] = $usercoursegrades;
            }
            $gradegrades[$userid] = $usergrades;
        }

        $userid = 100;
        $courseid = 10;
        $gis = array();
        foreach ($gradeitems[$courseid] as $item) {
            $gis[$item->id] = $item;
        }

        $sync = new moodlesync();
        $moodlegrades = $sync->get_moodlegrades($userid, $courseid, $gis);

        // Check that we have data for all passed itemids, and only data for the passed itemids.
        $this->assertEquals(count($gis), count($moodlegrades));
        $receiveditemids = array();
        foreach ($moodlegrades as $gradegrade) {
            $receiveditemids[] = $gradegrade->itemid;
        }
        $this->assertEquals($receiveditemids, array_keys($gis));

        // Assert returned data.
        foreach ($gradeitems[$courseid] as $item) {
            $this->assertArrayHasKey($item->id, $moodlegrades);

            $this->assertTrue($moodlegrades[$item->id] instanceof grade_grade);
            $this->assertEquals($userid, $moodlegrades[$item->id]->userid);

            if ($item->idnumber === 'optionalexam') {
                // Assert we have a grade_grade object even if no grade data was found in the db for a given item.
                $this->assertEmpty($moodlegrades[$item->id]->finalgrade);
            } else {
                // Assert we have accurate finalgrade data.
                $this->assertEquals($gradegrades[$userid][$courseid][$item->id]->finalgrade, $moodlegrades[$item->id]->finalgrade);
            }
        }
    }
}