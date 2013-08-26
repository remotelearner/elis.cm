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
 * @subpackage blocks-course_request
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Action';
$string['action_fields_class'] = 'Custom class instance field';
$string['action_fields_course'] = 'Custom course description field';
$string['add_course_field'] = 'Add Course Field';
$string['add_field_class'] = 'Add another class instance field';
$string['add_field_course'] = 'Add another course description field';
$string['blockname'] = 'Class Request';
$string['block/course_request:approve'] = 'Approve or deny pending course requests';

$string['class_role'] = 'Class Instance Role';
$string['classes_requested'] = 'Requested Class Instances';
$string['comments'] = 'Comments';
$string['comments_description'] = 'You may enter an optional note for the user who made this request in addition to denying or approving this course request.';
$string['configclass_role'] = 'Role to assign to the class requester in the newly-created class.';
$string['configcourse_role'] = 'Role to assign to the course requester in the newly-created course.';
$string['configuse_class_fields'] = 'Allow class field information to be added to requests. This setting also allows
                                     administrators to define which class fields can be specified in this manner.';
$string['configuse_course_fields'] = 'Allow course field information to be added to requests. This setting also allows
                                      administrators to define which course fields can be specified in this manner.';
$string['configuse_template_by_default'] = 'If enabled, requests will default to enabling the option of associating CM classes with Moodle courses (effective only for requesting users with the appropriate approval capability set).';
$string['configcreate_class_with_course'] = 'If enabled, requests for a new course will also create a new class.  Otherwise, a course will be created without a class.';
$string['course'] = 'Course Description';
$string['course_request:addinstance'] = 'Add a new Class Request block';
$string['course_request:approve'] = 'Approve class requests';
$string['course_request:config'] = 'Configure class request form';
$string['course_request:request'] = 'Request creation of new classes';
$string['course_role'] = 'Course Description Role';
$string['courserequestpages'] = 'Class Request Page';
$string['create_class_with_course'] = 'Create a class when creating a new course description';
$string['createclassheader'] = 'Class Instance Information';
$string['createcourseheader'] = 'Course Description Information';
$string['createuserheader'] = 'User Information';
$string['current'] = 'Current';
$string['current_classes'] = 'Current Class Instances';
$string['custom_field'] = 'Custom Fields';
$string['delete'] = 'Delete';
$string['denialconfirm'] = 'You may enter an optional note for the user who made this request in addition to ' .
                           'denying this course request.';
$string['deniedmessage'] = 'Request has been successfully denied';
$string['denythisrequest'] = 'Deny this request';
$string['department'] = 'Department';

$string['editrequestpages'] = 'Edit Request Page';
$string['email'] = 'Email';
$string['existing'] = 'From Existing Course';
$string['existing_fields'] = 'Existing Fields';
$string['exit'] = 'Revert changes';
$string['feedbackheader'] = 'Feedback';
$string['firstname'] = 'First Name';
$string['lastname'] = 'Last Name';
$string['newcourse'] = 'From New Course Description';
$string['new_request_notification'] = '{$a->first} {$a->last} has submitted a class request with title {$a->title}.';
$string['no_courses'] = 'No Classes';
$string['no_requests'] = 'No Requests';
$string['phone'] = 'Phone Number';
$string['use_class_fields'] = 'Use Class Instance Fields';
$string['use_course_fields'] = 'Use Course Description Fields';
$string['use_template_by_default'] = 'Use Course Template By Default';

$string['approvalconfirm'] = 'You may enter an optional note for the user who made this request in addition to ' .
                             'approving this course request.';
$string['approve'] = 'Approve';
$string['approvethisrequest'] = 'Approve this request';
$string['approvependingrequests'] = 'Approve Pending Requests';
$string['classidnumber'] = 'Class Instance ID Number';
$string['courseidnumber'] = 'Course Description ID Number';
$string['created'] = 'Created';
$string['deny'] = 'Deny';
$string['error_duplicateidnumber'] = 'Error: this ID Number is already used';
$string['errorinvalidrequestid'] = 'Error: invalid request id {$a}';
$string['moreclasses'] = 'View full class list';
$string['none'] = 'None';
$string['nopendingrequests'] = 'No pending requests found';
$string['note'] = 'Note';
$string['notice'] = 'Notice';
$string['notification_classrequestapproved'] = 'Your request for new class "{$a->classidnumber}" has been approved for existing course '.
                                               '"{$a->coursename}". You can view your new class with the following URL: {$a->link}';
$string['notification_classrequestdenied'] = 'Your request for a new class has been denied for existing course '.
                                             '"{$a->coursename}". For more information, see the list of requests here: {$a->link}';
$string['notification_courserequestapproved'] = 'Your request for new course "{$a->coursename}" has been approved. ' .
                                                'You can view your new course with the following URL: {$a->link}';
$string['notification_courserequestdenied'] = 'Your request for new course "{$a->coursename}" has been denied. ' .
                                              'For more information, see the list of requests here: {$a->link}';
$string['notification_courseandclassrequestapproved'] = 'Your request for new class "{$a->classidnumber}" has been approved for new course '.
                                                        '"{$a->coursename}". You can view your new class with the following URL: {$a->link}';
$string['notification_courseandclassrequestdenied'] = 'Your request for a new class has been denied for new course '.
                                                      '"{$a->coursename}". For more information, see the list of requests here: {$a->link}';
$string['notification_statusnote'] = '

An additional note was provided:

{$a->statusnote}';
$string['pendingrequests'] = 'Pending requests';
$string['pluginname'] = 'Class Request';

$string['remove'] = 'Remove';
$string['request'] = 'Create New Request';
$string['requestapproved'] = 'Request approved';
$string['requests'] = 'Requests';
$string['request_notice'] = 'Request Notice';
$string['request_status'] = 'Request Status';
$string['request_submitted'] = 'Your request has been submitted';
$string['request_submitted_and_auto_approved'] = 'Your request has been submitted and automatically approved';
$string['request_title'] = 'Class Request';
$string['required'] = 'Required';
$string['supervisor'] = 'Supervisor';
$string['title'] = 'Course Description Name';
$string['update'] = 'Apply changes';
$string['use_course_template'] = 'Use Course Template';

$string['view'] = 'View';
$string['view_request'] = 'View Request';

