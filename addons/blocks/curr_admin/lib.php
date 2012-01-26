<?php
/**
 * Common functions.
 *
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

function create_views($viewprefix=false) {
    global $CFG, $db;
    $result = true;

    // if no prefix specified, use the default Moodle prefix
    if ($viewprefix === false) {
        $viewprefix = $CFG->prefix;
    }

    $AS = sql_as();

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}GradesListing4Transcript AS
                SELECT c.fullname $AS coursename, c.shortname $AS courseABBR, i.itemname $AS itemname,
                       i.itemtype $AS itemtype, i.gradetype $AS gradetype, i.grademax $AS grademax,
                       i.grademin $AS grademin, i.scaleid $AS scaleid, i.outcomeid $AS outcomeid,
                       i.sortorder $AS sortorder, g.rawgrade $AS rawgrade, i.courseid $AS courseid,
                       g.itemid $AS itemid, g.userid $AS userid, g.rawgrademax $AS rawgrademax,
                       g.rawgrademin $AS rawgrademin, g.finalgrade $AS finalgrade, c.idnumber AS idnumber,
                       u.username $AS username, u.firstname $AS firstname, u.lastname $AS lastname,
                       u.address $AS address, u.city $AS city, u.country $AS country, u.institution $AS institution,
                       u.department $AS department, u.firstaccess $AS firstaccess, u.lastaccess $AS lastaccess,
                       u.lastlogin $AS lastlogin, u.firstname || ' ' || u.lastname $AS FullBy1st,
                       u.lastname || ', ' || u.firstname $AS FullByLast
                FROM {$CFG->prefix}course c
                     JOIN {$CFG->prefix}grade_items i ON c.id = i.courseid
                     JOIN {$CFG->prefix}grade_grades g ON i.id = g.itemid
                     JOIN {$CFG->prefix}user u ON g.userid = u.id
                WHERE i.itemtype = 'course'";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}GradesListing4Transcript AS
                SELECT c.fullname $AS coursename, c.shortname $AS courseABBR, i.itemname $AS itemname,
                       i.itemtype $AS itemtype, i.gradetype $AS gradetype, i.grademax $AS grademax,
                       i.grademin $AS grademin, i.scaleid $AS scaleid, i.outcomeid $AS outcomeid,
                       i.sortorder $AS sortorder, g.rawgrade $AS rawgrade, i.courseid $AS courseid,
                       g.itemid $AS itemid, g.userid $AS userid, g.rawgrademax $AS rawgrademax,
                       g.rawgrademin $AS rawgrademin, g.finalgrade $AS finalgrade, c.idnumber AS idnumber,
                       u.username $AS username, u.firstname $AS firstname, u.lastname $AS lastname,
                       u.address $AS address, u.city $AS city, u.country $AS country, u.institution $AS institution,
                       u.department $AS department, u.firstaccess $AS firstaccess, u.lastaccess $AS lastaccess,
                       u.lastlogin $AS lastlogin, concat(u.firstname, _utf8' ', u.lastname) $AS FullBy1st,
                       concat(u.lastname, _utf8', ', u.firstname) $AS FullByLast
                FROM {$CFG->prefix}course c
                     JOIN {$CFG->prefix}grade_items i ON c.id = i.courseid
                     JOIN {$CFG->prefix}grade_grades g ON i.id = g.itemid
                     JOIN {$CFG->prefix}user u ON g.userid = u.id
                WHERE i.itemtype = _utf8'course'";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}UserExt AS
                SELECT u.id $AS id, u.auth $AS auth, u.confirmed $AS confirmed, u.policyagreed $AS policyagreed,
                       u.deleted $AS deleted, u.mnethostid $AS mnethostid, u.username $AS username,
                       u.password $AS password, u.idnumber $AS idnumber, u.firstname $AS firstname,
                       u.lastname $AS lastname, u.firstname || ' ' || u.lastname $AS FullBy1st,
                       u.lastname || ', ' || u.firstname $AS FullByLast, u.email AS email,
                       u.emailstop $AS emailstop, u.icq $AS icq,u. skype $AS skype, u.yahoo $AS yahoo,
                       u.aim $AS aim, u.msn $AS msn, u.phone1 $AS phone1, u.phone2 $AS phone2,
                       u.institution $AS institution, u.department $AS department, u.address $AS address,
                       u.city $AS city, u.country $AS country, u.lang $AS lang, u.theme $AS theme,
                       u.timezone $AS timezone, u.firstaccess $AS firstaccess, u.lastaccess $AS lastaccess,
                       u.lastlogin $AS lastlogin, u.currentlogin $AS currentlogin, u.lastip $AS lastip,
                       u.secret $AS secret, u.picture $AS picture, u.url $AS url, u.description $AS description,
                       u.mailformat $AS mailformat, u.maildigest $AS maildigest, u.maildisplay $AS maildisplay,
                       u.htmleditor $AS htmleditor, u.ajax $AS ajax, u.autosubscribe $AS autosubscribe,
                       u.trackforums $AS trackforums, u.timemodified $AS timemodified, u.trustbitmask AS trustbitmask,
                       u.imagealt $AS imagealt, u.screenreader $AS screenreader
                FROM {$CFG->prefix}user u
                ORDER BY u.lastname, u.firstname";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}UserExt AS
                SELECT u.id $AS id, u.auth $AS auth, u.confirmed $AS confirmed, u.policyagreed $AS policyagreed,
                       u.deleted $AS deleted, u.mnethostid $AS mnethostid, u.username $AS username,
                       u.password $AS password, u.idnumber $AS idnumber, u.firstname $AS firstname,
                       u.lastname $AS lastname, concat(u.firstname, _utf8' ', u.lastname) $AS FullBy1st,
                       concat(u.lastname, _utf8', ', u.firstname) $AS FullByLast, u.email AS email,
                       u.emailstop $AS emailstop, u.icq $AS icq,u. skype $AS skype, u.yahoo $AS yahoo,
                       u.aim $AS aim, u.msn $AS msn, u.phone1 $AS phone1, u.phone2 $AS phone2,
                       u.institution $AS institution, u.department $AS department, u.address $AS address,
                       u.city $AS city, u.country $AS country, u.lang $AS lang, u.theme $AS theme,
                       u.timezone $AS timezone, u.firstaccess $AS firstaccess, u.lastaccess $AS lastaccess,
                       u.lastlogin $AS lastlogin, u.currentlogin $AS currentlogin, u.lastip $AS lastip,
                       u.secret $AS secret, u.picture $AS picture, u.url $AS url, u.description $AS description,
                       u.mailformat $AS mailformat, u.maildigest $AS maildigest, u.maildisplay $AS maildisplay,
                       u.htmleditor $AS htmleditor, u.ajax $AS ajax, u.autosubscribe $AS autosubscribe,
                       u.trackforums $AS trackforums, u.timemodified $AS timemodified, u.trustbitmask AS trustbitmask,
                       u.imagealt $AS imagealt, u.screenreader $AS screenreader
                FROM {$CFG->prefix}user u
                ORDER BY u.lastname, u.firstname";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}Top5ForumUserTest AS
                SELECT f.scale $AS forumScale, r.rating $AS postRating, p.userid $AS postUserid,
                       r.userid $AS ratingUserid, d.userid $AS DiscUserid, d.name $AS DiscName, f.course $AS course,
                       f.type $AS forumType, f.name $AS forumName, f.assessed $AS assessed,d.firstpost $AS firstpost,
                       p.totalscore $AS postTotalScore, p.created $AS created, p.modified $AS modified,
                       d.forum $AS forumid, c.shortname $AS courseABBR, u.FullByLast $AS Participant
                FROM {$CFG->prefix}forum f
                     JOIN {$CFG->prefix}forum_discussions d ON f.id = d.forum
                     JOIN {$CFG->prefix}forum_posts p ON d.id = p.discussion
                     LEFT JOIN {$CFG->prefix}forum_ratings r ON p.id = r.post
                     JOIN {$CFG->prefix}course c ON f.course = c.id
                     JOIN {$viewprefix}UserExt u ON p.userid = u.id";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}Top5ForumUserTest AS
                SELECT f.scale $AS forumScale, r.rating $AS postRating, p.userid $AS postUserid,
                       r.userid $AS ratingUserid, d.userid $AS DiscUserid, d.name $AS DiscName, f.course $AS course,
                       f.type $AS forumType, f.name $AS forumName, f.assessed $AS assessed,d.firstpost $AS firstpost,
                       p.totalscore $AS postTotalScore, p.created $AS created, p.modified $AS modified,
                       d.forum $AS forumid, c.shortname $AS courseABBR, u.FullByLast $AS Participant
                FROM {$CFG->prefix}forum f
                     JOIN {$CFG->prefix}forum_discussions d ON f.id = d.forum
                     JOIN {$CFG->prefix}forum_posts p ON d.id = p.discussion
                     LEFT JOIN {$CFG->prefix}forum_ratings r ON p.id = r.post
                     JOIN {$CFG->prefix}course c ON f.course = c.id
                     JOIN {$viewprefix}UserExt u ON p.userid = u.id";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}Role2RoleAssignments AS
                SELECT a.roleid $AS roleid, c.id $AS courseid, a.userid $AS userid, u.username $AS username,
                       u.firstname $AS firstname, u.lastname $AS lastname, u.FullBy1st $AS FullBy1st,
                       c.fullname $AS coursename, c.shortname $AS courseABBR, r1.name $AS roleName
                FROM {$CFG->prefix}role_assignments a
                JOIN {$viewprefix}UserExt u ON a.userid = u.id
                JOIN {$CFG->prefix}context ctx ON ctx.id = a.contextid AND ctx.contextlevel = " . CONTEXT_COURSE . "
                JOIN {$CFG->prefix}course c ON c.id = ctx.instanceid
                JOIN {$CFG->prefix}role r1 ON r1.id = a.roleid";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}Role2RoleAssignments AS
                SELECT a.roleid $AS roleid, c.id $AS courseid, a.userid $AS userid, u.username $AS username,
                       u.firstname $AS firstname, u.lastname $AS lastname, u.FullBy1st $AS FullBy1st,
                       c.fullname $AS coursename, c.shortname $AS courseABBR, r1.name $AS roleName
                FROM {$CFG->prefix}role_assignments a
                JOIN {$viewprefix}UserExt u ON a.userid = u.id
                JOIN {$CFG->prefix}context ctx ON ctx.id = a.contextid AND ctx.contextlevel = " . CONTEXT_COURSE . "
                JOIN {$CFG->prefix}course c ON c.id = ctx.instanceid
                JOIN {$CFG->prefix}role r1 ON r1.id = a.roleid";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}Role2RoleAssignments5 AS
                SELECT a.roleid $AS roleid, a.contextid $AS courseid, a.userid $AS userid, u.username $AS username,
                       u.firstname $AS firstname, u.lastname $AS lastname, u.FullBy1st $AS FullBy1st,
                       c.fullname $AS coursename, c.shortname $AS courseABBR, a.timestart $AS timestart,
                       a.timeend $AS timeend, TO_TIMESTAMP(a.timeend) $AS EndDate,
                       TO_TIMESTAMP(a.timestart) $AS StartDate, u.FullByLast $AS FullByLast,
                       a.timemodified $AS timemodified
                FROM {$CFG->prefix}role_assignments a
                JOIN {$CFG->prefix}role role
                ON a.roleid = role.id
                JOIN {$viewprefix}UserExt u ON a.userid = u.id
                JOIN {$CFG->prefix}context ctx ON ctx.id = a.contextid AND ctx.contextlevel = " . CONTEXT_COURSE . "
                JOIN {$CFG->prefix}course c ON c.id = ctx.instanceid
                WHERE role.shortname = 'student'";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}Role2RoleAssignments5 AS
                SELECT a.roleid $AS roleid, a.contextid $AS courseid, a.userid $AS userid, u.username $AS username,
                       u.firstname $AS firstname, u.lastname $AS lastname, u.FullBy1st $AS FullBy1st,
                       c.fullname $AS coursename, c.shortname $AS courseABBR, a.timestart $AS timestart,
                       a.timeend $AS timeend, FROM_UNIXTIME(a.timeend) $AS EndDate,
                       FROM_UNIXTIME(a.timestart) $AS StartDate, u.FullByLast $AS FullByLast,
                       a.timemodified $AS timemodified
                FROM {$CFG->prefix}role_assignments a
                JOIN {$CFG->prefix}role role
                ON a.roleid = role.id
                JOIN {$viewprefix}UserExt u ON a.userid = u.id
                JOIN {$CFG->prefix}context ctx ON ctx.id = a.contextid AND ctx.contextlevel = " . CONTEXT_COURSE . "
                JOIN {$CFG->prefix}course c ON c.id = ctx.instanceid
                WHERE role.shortname = 'student'";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}grade_grades_with_outcomes_ext AS
                SELECT gg.userid $AS userid, gg.itemid $AS itemid, i.courseid $AS courseid, gg.rawgrade $AS rawgrade,
                       gg.rawgrademax $AS rawgrademax, gg.rawgrademin $AS rawgrademin, gg.rawscaleid $AS rawscaleid,
                       o.shortname $AS outcomeABBR, o.fullname $AS outcomename, gg.finalgrade $AS finalgrade,
                       i.itemname $AS itemname, i.itemtype $AS itemtype, i.itemmodule $AS itemmodule,
                       i.calculation $AS calculation, i.gradetype $AS gradetype, i.grademax $AS grademax,
                       i.grademin $AS grademin, i.scaleid $AS scaleid, i.gradepass $AS gradepass,
                       i.multfactor $AS multfactor, i.plusfactor $AS plusfactor, i.categoryid $AS categoryid,
                       cc.name $AS cat, cc.description $AS catDesc, cc.parent $AS catParent, cc.sortorder $AS catSort,
                       cc.coursecount $AS CatCourseCount, u.FullBy1st $AS FullBy1st, u.FullByLast $AS FullByLast,
                       u.institution $AS institution, u.department $AS department, u.email $AS email,
                       gg.excluded $AS excluded, i.sortorder $AS itemSort, c.sortorder $AS courseSort,
                       c.fullname $AS coursename, c.shortname $AS courseABBR, i.outcomeid $AS outcomeid,
                       u.username $AS username, o.scaleid $AS oscaleid, s.scale $AS scale
                FROM {$CFG->prefix}grade_items i
                     JOIN {$CFG->prefix}grade_grades gg ON i.id = gg.itemid
                     JOIN {$CFG->prefix}grade_outcomes o ON i.outcomeid = o.id
                     LEFT JOIN {$CFG->prefix}course_categories cc ON i.categoryid = cc.id
                     JOIN {$viewprefix}UserExt u ON gg.userid = u.id
                     JOIN {$CFG->prefix}course c ON i.courseid = c.id
                     JOIN {$CFG->prefix}scale s ON o.scaleid = s.id";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}grade_grades_with_outcomes_ext AS
                SELECT gg.userid $AS userid, gg.itemid $AS itemid, i.courseid $AS courseid, gg.rawgrade $AS rawgrade,
                       gg.rawgrademax $AS rawgrademax, gg.rawgrademin $AS rawgrademin, gg.rawscaleid $AS rawscaleid,
                       o.shortname $AS outcomeABBR, o.fullname $AS outcomename, gg.finalgrade $AS finalgrade,
                       i.itemname $AS itemname, i.itemtype $AS itemtype, i.itemmodule $AS itemmodule,
                       i.calculation $AS calculation, i.gradetype $AS gradetype, i.grademax $AS grademax,
                       i.grademin $AS grademin, i.scaleid $AS scaleid, i.gradepass $AS gradepass,
                       i.multfactor $AS multfactor, i.plusfactor $AS plusfactor, i.categoryid $AS categoryid,
                       cc.name $AS cat, cc.description $AS catDesc, cc.parent $AS catParent, cc.sortorder $AS catSort,
                       cc.coursecount $AS CatCourseCount, u.FullBy1st $AS FullBy1st, u.FullByLast $AS FullByLast,
                       u.institution $AS institution, u.department $AS department, u.email $AS email,
                       gg.excluded $AS excluded, i.sortorder $AS itemSort, c.sortorder $AS courseSort,
                       c.fullname $AS coursename, c.shortname $AS courseABBR, i.outcomeid $AS outcomeid,
                       u.username $AS username, o.scaleid $AS oscaleid, s.scale $AS scale
                FROM {$CFG->prefix}grade_items i
                     JOIN {$CFG->prefix}grade_grades gg ON i.id = gg.itemid
                     JOIN {$CFG->prefix}grade_outcomes o ON i.outcomeid = o.id
                     LEFT JOIN {$CFG->prefix}course_categories cc ON i.categoryid = cc.id
                     JOIN {$viewprefix}UserExt u ON gg.userid = u.id
                     JOIN {$CFG->prefix}course c ON i.courseid = c.id
                     JOIN {$CFG->prefix}scale s ON o.scaleid = s.id";
    }

    $result = $result && execute_sql($sql);

    require_once($CFG->libdir.'/gradelib.php');

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}grade_grades_with_outcome_counts AS
                SELECT SUM(CASE WHEN m.finalgrade = 1 THEN m.finalgrade ELSE 0 END) $AS Level1s,
                       SUM(CASE WHEN m.finalgrade = 2 THEN m.finalgrade ELSE 0 END) $AS Level2s,
                       SUM(CASE WHEN m.finalgrade = 3 THEN m.finalgrade ELSE 0 END) $AS Level3s,
                       SUM(CASE WHEN m.finalgrade = 4 THEN m.finalgrade ELSE 0 END) $AS Level4s,
                       SUM(CASE WHEN m.finalgrade = 5 THEN m.finalgrade ELSE 0 END) $AS Level5s,
                       SUM(CASE WHEN m.finalgrade = 6 THEN m.finalgrade ELSE 0 END) $AS Level6s,
                       SUM(CASE WHEN m.finalgrade = 1 THEN 1 ELSE 0 END) $AS Level1,
                       SUM(CASE WHEN m.finalgrade = 2 THEN 1 ELSE 0 END) $AS Level2,
                       SUM(CASE WHEN m.finalgrade = 3 THEN 1 ELSE 0 END) $AS Level3,
                       SUM(CASE WHEN m.finalgrade = 4 THEN 1 ELSE 0 END) $AS Level4,
                       SUM(CASE WHEN m.finalgrade = 5 THEN 1 ELSE 0 END) $AS Level5,
                       SUM(CASE WHEN m.finalgrade = 6 THEN 1 ELSE 0 END) $AS Level6,
                       SUM(m.finalgrade) $AS SumTotal, COUNT(m.finalgrade) $AS CountTotal,
                       SUM(m.finalgrade) / COUNT(m.finalgrade) $AS ScaleAVG, m.userid $AS userid,
                       COUNT(m.itemid) $AS itemCount, m.outcomeid $AS outcomeid, m.username $AS username,
                       m.outcomeABBR $AS outcomeABBR, m.outcomename $AS outcomename, m.finalgrade $AS finalgrade,
                       ROUND(m.grademin, 0) || '-' || ROUND(m.grademax, 0) $AS ScaleExp,
                       m.FullByLast $AS FullByLast, m.itemid $AS itemid, m.scale $AS scale
                FROM {$viewprefix}grade_grades_with_outcomes_ext m
                WHERE m.gradetype = " . GRADE_TYPE_SCALE . "
                GROUP BY m.userid, m.outcomeid, m.username, m.outcomeABBR, m.outcomename, m.finalgrade,
                         m.grademin, m.grademax, m.FullByLast, m.itemid, m.scale";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}grade_grades_with_outcome_counts AS
                SELECT SUM(IF((m.finalgrade = 1), m.finalgrade, 0)) $AS Level1s,
                       SUM(IF((m.finalgrade = 2), m.finalgrade, 0)) $AS Level2s,
                       SUM(IF((m.finalgrade = 3), m.finalgrade, 0)) $AS Level3s,
                       SUM(IF((m.finalgrade = 4), m.finalgrade, 0)) $AS Level4s,
                       SUM(IF((m.finalgrade = 5), m.finalgrade, 0)) $AS Level5s,
                       SUM(IF((m.finalgrade = 6), m.finalgrade, 0)) $AS Level6s,
                       SUM(IF((m.finalgrade = 1), 1, 0)) $AS Level1, SUM(IF((m.finalgrade = 2), 1, 0)) $AS Level2,
                       SUM(IF((m.finalgrade = 3), 1, 0)) $AS Level3, SUM(IF((m.finalgrade = 4), 1, 0)) $AS Level4,
                       SUM(IF((m.finalgrade = 5), 1, 0)) $AS Level5, SUM(IF((m.finalgrade = 6), 1, 0)) $AS Level6,
                       SUM(m.finalgrade) $AS SumTotal, COUNT(m.finalgrade) $AS CountTotal,
                       (SUM(m.finalgrade) / COUNT(m.finalgrade)) $AS ScaleAVG, m.userid $AS userid,
                       COUNT(m.itemid) $AS itemCount, m.outcomeid $AS outcomeid, m.username $AS username,
                       m.outcomeABBR $AS outcomeABBR, m.outcomename $AS outcomename, m.finalgrade $AS finalgrade,
                       CONCAT(FORMAT(m.grademin, 0), _utf8'-', FORMAT(m.grademax, 0)) $AS ScaleExp,
                       m.FullByLast $AS FullByLast, m.itemid $AS itemid, m.scale $AS scale
                FROM {$viewprefix}grade_grades_with_outcomes_ext m
                WHERE m.gradetype = " . GRADE_TYPE_SCALE . "
                GROUP BY m.userid, m.outcomeid, m.itemid";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}GroupsNMembers AS
                SELECT g.courseid $AS courseid, g.name $AS groupname, gm.groupid $AS groupid, gm.userid $AS userid,
                       c.fullname $AS courseName, c.shortname $AS CourseABBR,
                       c.shortname || ' - ' || COALESCE(g.name, '<No Group>') $AS courseGroupExp,
                       gm.timeadded $AS timeadded
                FROM {$CFG->prefix}groups g
                     JOIN {$CFG->prefix}groups_members gm ON g.id = gm.groupid
                     JOIN {$CFG->prefix}course c ON g.courseid = c.id
                ORDER BY c.shortname, g.name, gm.userid";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}GroupsNMembers AS
                SELECT g.courseid $AS courseid, g.name $AS groupname, gm.groupid $AS groupid, gm.userid $AS userid,
                       c.fullname $AS courseName, c.shortname $AS CourseABBR,
                       CONCAT(c.shortname, _utf8' - ', IFNULL(g.name, _utf8'<No Group>')) $AS courseGroupExp,
                       gm.timeadded $AS timeadded
                FROM {$CFG->prefix}groups g
                     JOIN {$CFG->prefix}groups_members gm ON g.id = gm.groupid
                     JOIN {$CFG->prefix}course c ON g.courseid = c.id
                ORDER BY c.shortname, g.name, gm.userid";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}moodleLog AS
                SELECT l.id $AS id, l.time $AS time,
                       TO_CHAR(TO_TIMESTAMP(l.time), 'Dy') $AS LogWeekDay,
                       TO_CHAR(TO_TIMESTAMP(l.time), 'D') $AS LogWeekDaynum,
                       TO_CHAR(TO_TIMESTAMP(l.time), 'YYYY') $AS LogYear,
                       TO_CHAR(TO_TIMESTAMP(l.time), 'MM') $AS LogMonth,
                       TO_CHAR(TO_TIMESTAMP(l.time), 'Month') $AS LogMonthName,
                       TO_CHAR(TO_TIMESTAMP(l.time), 'DD') $AS LogDay,
                       l.userid $AS userid, l.ip $AS ip, l.course $AS course, l.module $AS module,
                       l.cmid $AS cmid, l.action $AS action, l.url $AS url, l.info $AS info,
                       c.fullname $AS CourseName, c.shortname $AS CourseABBR,
                       u.firstname || ' ' || u.lastname $AS UserNameBy1st,
                       u.lastname || ',  ' || u.firstname $AS UserNameByLast,
                       u.username $AS username, ra.roleid $AS roleid, r.name $AS rolename
                FROM {$CFG->prefix}log l
                     JOIN {$CFG->prefix}course c ON l.course = c.id
                     JOIN {$CFG->prefix}user u ON l.userid = u.id
                LEFT JOIN {$CFG->prefix}role_assignments ra ON ra.contextid = l.course AND ra.userid = l.userid
                LEFT JOIN {$CFG->prefix}role r ON ra.roleid = r.id
                ORDER BY l.userid, l.time";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}moodleLog AS
                SELECT l.id $AS id, l.time $AS time,
                       DATE_FORMAT(FROM_UNIXTIME(l.time), _utf8'%a') $AS LogWeekDay,
                       DATE_FORMAT(FROM_UNIXTIME(l.time), _utf8'%w') $AS LogWeekDaynum,
                       DATE_FORMAT(FROM_UNIXTIME(l.time), _utf8'%Y') $AS LogYear,
                       DATE_FORMAT(FROM_UNIXTIME(l.time), _utf8'%m') $AS LogMonth,
                       DATE_FORMAT(FROM_UNIXTIME(l.time), _utf8'%M') $AS LogMonthName,
                       DATE_FORMAT(FROM_UNIXTIME(l.time), _utf8'%d') $AS LogDay,
                       l.userid $AS userid, l.ip $AS ip, l.course $AS course, l.module $AS module,
                       l.cmid $AS cmid, l.action $AS action, l.url $AS url, l.info $AS info,
                       c.fullname $AS CourseName, c.shortname $AS CourseABBR,
                       CONCAT((u.firstname + _utf8' ') + u.lastname) $AS UserNameBy1st,
                       CONCAT((u.lastname + _utf8',  ') + u.firstname) $AS UserNameByLast,
                       u.username $AS username, ra.roleid $AS roleid, r.name $AS rolename
                FROM {$CFG->prefix}log l
                     JOIN {$CFG->prefix}course c ON l.course = c.id
                     JOIN {$CFG->prefix}user u ON l.userid = u.id
                LEFT JOIN {$CFG->prefix}role_assignments ra ON ra.contextid = l.course AND ra.userid = l.userid
                LEFT JOIN {$CFG->prefix}role r ON ra.roleid = r.id
                ORDER BY l.userid, l.time";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}moodleLogwDate AS
                SELECT DATE (m.LogMonthName || ' ' || m.LogDay || ', ' || m.LogYear) $AS LogDate,
                       m.id $AS id, m.time $AS time, m.LogWeekDay $AS LogWeekDay, m.LogWeekDaynum $AS LogWeekDaynum,
                       m.LogYear $AS LogYear, m.LogMonth $AS LogMonth, m.LogMonthName $AS LogMonthName,
                       m.LogDay $AS LogDay, m.userid $AS userid, m.ip $AS ip, m.course $AS course,
                       m.module $AS module, m.cmid $AS cmid, m.action $AS action, m.url $AS url, m.info $AS info,
                       m.CourseName $AS CourseName, m.CourseABBR $AS CourseABBR, m.UserNameBy1st $AS UserNameBy1st,
                       m.UserNameByLast $AS UserNameByLast, m.username $AS username, m.roleid $AS roleid,
                       m.rolename $AS rolename
                FROM {$viewprefix}moodleLog m";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}moodleLogwDate AS
                SELECT STR_TO_DATE(CONCAT(m.LogMonth, '/', m.LogDay, '/', m.LogYear), _utf8'%m/%d/%Y') $AS LogDate,
                       m.id $AS id, m.time $AS time, m.LogWeekDay $AS LogWeekDay, m.LogWeekDaynum $AS LogWeekDaynum,
                       m.LogYear $AS LogYear, m.LogMonth $AS LogMonth, m.LogMonthName $AS LogMonthName,
                       m.LogDay $AS LogDay, m.userid $AS userid, m.ip $AS ip, m.course $AS course,
                       m.module $AS module, m.cmid $AS cmid, m.action $AS action, m.url $AS url, m.info $AS info,
                       m.CourseName $AS CourseName, m.CourseABBR $AS CourseABBR, m.UserNameBy1st $AS UserNameBy1st,
                       m.UserNameByLast $AS UserNameByLast, m.username $AS username, m.roleid $AS roleid,
                       m.rolename $AS rolename
                FROM {$viewprefix}moodleLog m";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LogwDateSummary AS
                SELECT COUNT(m.id) $AS CountClicks, m.LogWeekDay $AS LogWeekDay, m.LogYear $AS LogYear,
                       m.LogMonth $AS LogMonth, m.LogMonthName $AS LogMonthName, m.LogDay $AS LogDay,
                       m.LogDate $AS LogDate, TO_CHAR(m.LogDate, 'WW') $AS WeekNum, m.userid $AS userid,
                       m.course $AS course, m.CourseName $AS CourseName,m .UserNameBy1st $AS UserNameBy1st,
                       m.UserNameByLast $AS UserNameByLast, m.username $AS username, m.CourseABBR $AS CourseABBR,
                       m.LogWeekDaynum $AS LogWeekDaynum, m.roleid $AS roleid, m.rolename $AS rolename
                FROM {$viewprefix}moodleLogwDate m
                GROUP BY m.course, m.LogDate, m.LogWeekDay, m.LogYear, m.LogMonth, m.LogMonthName, m.LogDay,
                         m.userid, m.CourseName, m.UserNameBy1st, m.UserNameByLast, m.username, m.CourseABBR,
                         m.LogWeekDaynum, m.roleid, m.rolename
                ORDER BY m.course, m.LogDate";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LogwDateSummary AS
                SELECT COUNT(m.id) $AS CountClicks, m.LogWeekDay $AS LogWeekDay, m.LogYear $AS LogYear,
                       m.LogMonth $AS LogMonth, m.LogMonthName $AS LogMonthName, m.LogDay $AS LogDay,
                       m.LogDate $AS LogDate, WEEK(m.LogDate, 2) $AS WeekNum, m.userid $AS userid, m.course $AS course,
                       m.CourseName $AS CourseName,m .UserNameBy1st $AS UserNameBy1st,
                       m.UserNameByLast $AS UserNameByLast, m.username $AS username, m.CourseABBR $AS CourseABBR,
                       m.LogWeekDaynum $AS LogWeekDaynum, m.roleid $AS roleid, m.rolename $AS rolename
                FROM {$viewprefix}moodleLogwDate m
                GROUP BY m.course, m.LogDate, m.userid, m.roleid
                ORDER BY m.course, m.LogDate";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LogSummaryWithGroups AS
                SELECT SUM(CASE WHEN L.LogWeekDay = 'Mon' THEN L.CountClicks ELSE 0 END) $AS Mon,
                       SUM(CASE WHEN L.LogWeekDay = 'Tue' THEN L.CountClicks ELSE 0 END) $AS Tue,
                       SUM(CASE WHEN L.LogWeekDay = 'Wed' THEN L.CountClicks ELSE 0 END) $AS Wed,
                       SUM(CASE WHEN L.LogWeekDay = 'Thu' THEN L.CountClicks ELSE 0 END) $AS Thu,
                       SUM(CASE WHEN L.LogWeekDay = 'Fri' THEN L.CountClicks ELSE 0 END) $AS Fri,
                       SUM(CASE WHEN L.LogWeekDay = 'Sat' THEN L.CountClicks ELSE 0 END) $AS Sat,
                       SUM(CASE WHEN L.LogWeekDay = 'Sun' THEN L.CountClicks ELSE 0 END) $AS Sun,
                       SUM(L.CountClicks) $AS Total, L.LogWeekDay $AS LogWeekDay, L.LogYear $AS LogYear,
                       L.LogMonth $AS LogMonth, L.LogDay $AS LogDay, L.LogDate $AS LogDate,
                       L.LogDate - CAST(TO_CHAR(L.LogDate, 'D days') AS INTERVAL) $AS MyMonday, L.WeekNum $AS WeekNum,
                       L.userid $AS userid, L.course $AS course, L.CourseName $AS CourseName,
                       L.UserNameBy1st $AS UserNameBy1st, L.UserNameByLast $AS UserNameByLast,
                       L.username $AS username, L.CourseABBR $AS CourseABBR, G.groupname $AS groupname,
                       COALESCE(G.courseGroupExp, L.CourseABBR || ' <Not Grouped>') $AS courseGroupExp,
                       G.groupid $AS groupid
                FROM {$viewprefix}LogwDateSummary L
                LEFT JOIN {$viewprefix}GroupsNMembers G ON L.userid = G.userid AND G.courseid = L.course
                GROUP BY L.course, G.courseGroupExp, L.LogDate - CAST(TO_CHAR(L.LogDate, 'D days') AS INTERVAL),
                         L.LogWeekDay, L.LogYear, L.LogMonth, L.LogDay, L.LogDate, L.WeekNum, L.userid,
                         L.CourseName, L.UserNameBy1st, L.UserNameByLast, L.username, L.CourseABBR, G.groupname,
                         g.groupid
                ORDER BY L.course, COALESCE(G.courseGroupExp, L.CourseABBR || ' <Not Grouped>'), L.username,
                         L.LogDate - CAST(TO_CHAR(L.LogDate, 'D days') AS INTERVAL)";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LogSummaryWithGroups AS
                SELECT SUM(IF((L.LogWeekDay = _utf8'Mon'), L.CountClicks, 0)) $AS Mon,
                       SUM(IF((L.LogWeekDay = _utf8'Tue'), L.CountClicks, 0)) $AS Tue,
                       SUM(IF((L.LogWeekDay = _utf8'Wed'), L.CountClicks, 0)) $AS Wed,
                       SUM(IF((L.LogWeekDay = _utf8'Thu'), L.CountClicks, 0)) $AS Thu,
                       SUM(IF((L.LogWeekDay = _utf8'Fri'), L.CountClicks, 0)) $AS Fri,
                       SUM(IF((L.LogWeekDay = _utf8'Sat'), L.CountClicks, 0)) $AS Sat,
                       SUM(IF((L.LogWeekDay = _utf8'Sun'), L.CountClicks, 0)) $AS Sun,
                       SUM(L.CountClicks) $AS Total, L.LogWeekDay $AS LogWeekDay, L.LogYear $AS LogYear,
                       L.LogMonth $AS LogMonth, L.LogDay $AS LogDay, L.LogDate $AS LogDate,
                       (L.LogDate - INTERVAL WEEKDAY(L.LogDate) DAY) $AS MyMonday, L.WeekNum $AS WeekNum,
                       L.userid $AS userid, L.course $AS course, L.CourseName $AS CourseName,
                       L.UserNameBy1st $AS UserNameBy1st, L.UserNameByLast $AS UserNameByLast,
                       L.username $AS username, L.CourseABBR $AS CourseABBR, G.groupname $AS groupname,
                       IFNULL(G.courseGroupExp, CONCAT(L.CourseABBR, _utf8' <Not Grouped>')) $AS courseGroupExp,
                       G.groupid $AS groupid
                FROM {$viewprefix}LogwDateSummary L
                LEFT JOIN {$viewprefix}GroupsNMembers G ON L.userid = G.userid AND G.courseid = L.course
                GROUP BY L.course, G.courseGroupExp, L.username, (L.LogDate - INTERVAL WEEKDAY(L.LogDate) DAY)
                ORDER BY L.course, IFNULL(G.courseGroupExp, CONCAT(L.CourseABBR, _utf8' <Not Grouped>')), L.username,
                         (L.LogDate - INTERVAL WEEKDAY(L.LogDate) DAY)";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LoginDurationByUserNDate AS
                SELECT MAX(m.time) $AS maxSec, MIN(m.time) $AS MinSec,
                       (MAX(m.time) - MIN(m.time)) / 60 $AS DurationNMinutes,
                       ((MAX(m.time) - MIN(m.time)) / 60) / 60 $AS DurationNHrs,
                       m.LogDate $AS LogDate, m.LogWeekDay $AS LogWeekDay, m.LogYear $AS LogYear,
                       m.LogMonth $AS LogMonth, m.LogDay $AS LogDay, COUNT(DISTINCT m.userid) $AS CountUserid,
                       COUNT(m.id) $AS CountClicks, TO_CHAR(m.LogDate, 'WW') $AS WeekNum
                FROM {$viewprefix}moodleLogwDate m
                GROUP BY m.LogDate, m.LogWeekDay, m.LogYear, m.LogMonth, m.LogDay, m.userid
                ORDER BY m.userid, m.LogDate";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LoginDurationByUserNDate AS
                SELECT MAX(m.time) $AS maxSec, MIN(m.time) $AS MinSec,
                       ((MAX(m.time) - MIN(m.time)) / 60) $AS DurationNMinutes,
                       (((MAX(m.time) - MIN(m.time)) / 60) / 60) $AS DurationNHrs,
                       m.LogDate $AS LogDate, m.LogWeekDay $AS LogWeekDay, m.LogYear $AS LogYear,
                       m.LogMonth $AS LogMonth, m.LogDay $AS LogDay, COUNT(DISTINCT m.userid) $AS CountUserid,
                       COUNT(m.id) $AS CountClicks, WEEK(m.LogDate, 5) $AS WeekNum
                FROM {$viewprefix}moodleLogwDate m
                GROUP BY m.LogDate, m.LogWeekDay, m.LogYear, m.LogMonth, m.LogDay, m.userid
                ORDER BY m.userid, m.LogDate";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LoginDurationByUserNDateExt AS
                SELECT m.maxSec $AS maxSec, m.MinSec $AS MinSec, m.DurationNMinutes $AS DurationNMinutes,
                       m.DurationNHrs $AS DurationNHrs, m.LogDate $AS LogDate,
                       TO_CHAR(m.LogDate, 'D DD Month YYYY') $AS MyDateTxt, m.LogWeekDay $AS LogWeekDay,
                       m.LogYear $AS LogYear, m.LogMonth $AS LogMonth, m.LogDay $AS LogDay,
                       m.CountClicks $AS CountClicks, m.WeekNum $AS WeekNum,
                       m.CountClicks / m.DurationNHrs $AS ClicksPerHr, m.CountUserid $AS CountUserid
                FROM {$viewprefix}LoginDurationByUserNDate m";
    } else{
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LoginDurationByUserNDateExt AS
                SELECT m.maxSec $AS maxSec, m.MinSec $AS MinSec, m.DurationNMinutes $AS DurationNMinutes,
                       m.DurationNHrs $AS DurationNHrs, m.LogDate $AS LogDate,
                       DATE_FORMAT(m.LogDate, _utf8'%W %e %M %Y') $AS MyDateTxt, m.LogWeekDay $AS LogWeekDay,
                       m.LogYear $AS LogYear, m.LogMonth $AS LogMonth, m.LogDay $AS LogDay,
                       m.CountClicks $AS CountClicks, m.WeekNum $AS WeekNum,
                       (m.CountClicks / m.DurationNHrs) $AS ClicksPerHr, m.CountUserid $AS CountUserid
                FROM {$viewprefix}LoginDurationByUserNDate m";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}testweekdayCalc AS
                SELECT DISTINCT m.LogDate AS LogDate,
                       m.LogDate - CAST (TO_CHAR(m.LogDate, 'D days') AS INTERVAL) AS MyMonday,
                       m.WeekNum AS WeekNum, m.LogWeekDay AS LogWeekDay
                FROM {$viewprefix}LoginDurationByUserNDateExt m
                ORDER BY m.LogDate";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}testweekdayCalc AS
                SELECT DISTINCT m.LogDate $AS LogDate, (m.LogDate - INTERVAL WEEKDAY(m.LogDate) DAY) $AS MyMonday,
                       m.WeekNum $AS WeekNum, m.LogWeekDay $AS LogWeekDay
                FROM {$viewprefix}LoginDurationByUserNDateExt m
                ORDER BY m.LogDate";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LoginDurationbyUserNDate1 AS
                SELECT MAX(m.time) $AS maxSec, MIN(m.time) $AS MinSec,
                       (MAX(m.time) - MIN(m.time)) / 60 $AS DurationNMinutes,
                       ((MAX(m.time) - MIN(m.time)) / 60) / 60 $AS DurationNHrs, m.LogWeekDay $AS LogWeekDay,
                       m.LogYear $AS LogYear, m.LogMonth $AS LogMonth, m.LogDay $AS LogDay,
                       COUNT(DISTINCT m.userid) $AS CountUserid, COUNT(m.id) $AS CountClicks,
                       TO_CHAR(m.LogDate, 'WW') $AS WeekNum, t.MyMonday $AS MyMonday,
                       m.LogDate $AS LogDate
                FROM {$viewprefix}moodleLogwDate m
                JOIN {$viewprefix}testweekdayCalc t ON m.LogDate = t.LogDate
                GROUP BY m.LogDate,m.LogWeekDay, m.LogYear, m.LogMonth, m.LogDay, t.MyMonday,
                         m.userid
                ORDER BY m.userid, m.LogDate";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LoginDurationbyUserNDate1 AS
                SELECT MAX(m.time) $AS maxSec, MIN(m.time) $AS MinSec,
                       ((MAX(m.time) - MIN(m.time)) / 60) $AS DurationNMinutes,
                       (((MAX(m.time) - MIN(m.time)) / 60) / 60) $AS DurationNHrs, m.LogWeekDay $AS LogWeekDay,
                       m.LogYear $AS LogYear, m.LogMonth $AS LogMonth, m.LogDay $AS LogDay,
                       COUNT(DISTINCT m.userid) $AS CountUserid, COUNT(m.id) $AS CountClicks,
                       WEEK(m.LogDate,5) $AS WeekNum, t.MyMonday $AS MyMonday, m.LogDate $AS LogDate
                FROM {$viewprefix}moodleLogwDate m
                JOIN {$viewprefix}testweekdayCalc t ON m.LogDate = t.LogDate
                GROUP BY m.LogDate,m.LogWeekDay, m.LogYear, m.LogMonth, m.LogDay, m.userid
                ORDER BY m.userid, m.LogDate";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LoginDurationByUserNDate1Ext AS
                SELECT TO_CHAR(m.LogDate, 'D DD Month YYYY') $AS MyDateTxt,
                       m.CountClicks / m.DurationNHrs $AS ClicksPerHr, m.maxSec $AS maxSec, m.MinSec $AS MinSec,
                       m.DurationNMinutes $AS DurationNMinutes, m.DurationNHrs $AS DurationNHrs,
                       m.LogWeekDay $AS LogWeekDay, m.LogYear $AS LogYear, m.LogMonth $AS LogMonth,
                       m.LogDay $AS LogDay, m.CountUserid $AS CountUserid, m.CountClicks $AS CountClicks,
                       m.WeekNum $AS WeekNum, m.MyMonday $AS MyMonday, m.LogDate $AS LogDate
                FROM {$viewprefix}LoginDurationbyUserNDate1 m";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}LoginDurationByUserNDate1Ext AS
                SELECT DATE_FORMAT(m.LogDate, _utf8'%W %e %M %Y') $AS MyDateTxt,
                       (m.CountClicks / m.DurationNHrs) $AS ClicksPerHr, m.maxSec $AS maxSec, m.MinSec $AS MinSec,
                       m.DurationNMinutes $AS DurationNMinutes, m.DurationNHrs $AS DurationNHrs,
                       m.LogWeekDay $AS LogWeekDay, m.LogYear $AS LogYear, m.LogMonth $AS LogMonth,
                       m.LogDay $AS LogDay, m.CountUserid $AS CountUserid, m.CountClicks $AS CountClicks,
                       m.WeekNum $AS WeekNum, m.MyMonday $AS MyMonday, m.LogDate $AS LogDate
                FROM {$viewprefix}LoginDurationbyUserNDate1 m";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}SiteWideTimeStats AS
                SELECT SUM(CASE WHEN L.LogWeekDay = 'Mon' THEN L.CountClicks ELSE 0 END) $AS Mon,
                       SUM(CASE WHEN L.LogWeekDay = 'Tue' THEN L.CountClicks ELSE 0 END) $AS Tue,
                       SUM(CASE WHEN L.LogWeekDay = 'Wed' THEN L.CountClicks ELSE 0 END) $AS Wed,
                       SUM(CASE WHEN L.LogWeekDay = 'Thu' THEN L.CountClicks ELSE 0 END) $AS Thu,
                       SUM(CASE WHEN L.LogWeekDay = 'Fri' THEN L.CountClicks ELSE 0 END) $AS Fri,
                       SUM(CASE WHEN L.LogWeekDay = 'Sat' THEN L.CountClicks ELSE 0 END) $AS Sat,
                       SUM(CASE WHEN L.LogWeekDay = 'Sun' THEN L.CountClicks ELSE 0 END) $AS Sun,
                       SUM(L.CountClicks) $AS Total, SUM(L.ClicksPerHr) $AS TtlCPH, L.MyDateTxt $AS MyDateTxt,
                       L.ClicksPerHr $AS ClicksPerHr, L.DurationNHrs $AS DurationNHrs, L.CountUserid $AS CountUserid,
                       L.CountClicks $AS CountClicks, L.WeekNum $AS WeekNum, L.MyMonday $AS MyMonday
                FROM {$viewprefix}LoginDurationByUserNDate1Ext L
                GROUP BY L.WeekNum, L.MyDateTxt, L.ClicksPerHr, L.DurationNHrs, L.CountUserid, L.CountClicks,
                         L.MyMonday
                ORDER BY L.MyMonday DESC";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}SiteWideTimeStats AS
                SELECT SUM(IF((L.LogWeekDay = _utf8'Mon'), L.CountClicks, 0)) $AS Mon,
                       SUM(IF((L.LogWeekDay = _utf8'Tue'), L.CountClicks, 0)) $AS Tue,
                       SUM(IF((L.LogWeekDay = _utf8'Wed'), L.CountClicks, 0)) $AS Wed,
                       SUM(IF((L.LogWeekDay = _utf8'Thu'), L.CountClicks, 0)) $AS Thu,
                       SUM(IF((L.LogWeekDay = _utf8'Fri'), L.CountClicks, 0)) $AS Fri,
                       SUM(IF((L.LogWeekDay = _utf8'Sat'), L.CountClicks, 0)) $AS Sat,
                       SUM(IF((L.LogWeekDay = _utf8'Sun'), L.CountClicks, 0)) $AS Sun,
                       SUM(L.CountClicks) $AS Total, SUM(L.ClicksPerHr) $AS TtlCPH, L.MyDateTxt $AS MyDateTxt,
                       L.ClicksPerHr $AS ClicksPerHr, L.DurationNHrs $AS DurationNHrs, L.CountUserid $AS CountUserid,
                       L.CountClicks $AS CountClicks, L.WeekNum $AS WeekNum, L.MyMonday $AS MyMonday
                FROM {$viewprefix}LoginDurationByUserNDate1Ext L
                GROUP BY L.WeekNum, L.MyDateTxt
                ORDER BY L.MyMonday DESC";
    }

    $result = $result && execute_sql($sql);

    if ($db->databaseType == 'postgres7') {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}courseNforums AS
                SELECT f.id $AS forumid, c.shortname || ' | ' || f.name $AS courseNforumname
                FROM {$CFG->prefix}forum f
                JOIN {$CFG->prefix}course c ON c.id = f.course
                ORDER BY c.shortname, f.name";
    } else {
        $sql = "CREATE OR REPLACE VIEW {$viewprefix}courseNforums AS
                SELECT f.id $AS forumid, CONCAT(c.shortname, ' | ', f.name) $AS courseNforumname
                FROM {$CFG->prefix}forum f
                JOIN {$CFG->prefix}course c ON c.id = f.course
                ORDER BY c.shortname, f.name";
    }

    $result = $result && execute_sql($sql);

    return $result;
}

/**
 * Helper method for setting up a menu item based on a CM entity
 *
 * @param   string    $type                  The type of CM entity we are using
 * @param   object    $instance              CM entity instance
 * @param   string    $parent                Name of the parent element
 * @param   string    $css_class             CSS class used for styling this item
 * @param   int       $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int       $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   array     $params                Any page params that are needed
 * @param   boolean   $isLeaf                If true, this node is automatically a leaf
 * @param   string    $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem                         The appropriate menu item
 */
function block_curr_admin_get_menu_item($type, $instance, $parent, $css_class, $parent_cluster_id, $parent_curriculum_id, $params = array(), $isLeaf = false, $parent_path = '') {
    $display = '';

    //determine the display attribute from the entity type
    switch($type) {
        case 'cluster':
            $display = 'name';
            break;
        case 'curriculum':
            $display = 'name';
            break;
        case 'course':
            $display = 'coursename';
            break;
        case 'track':
            $display = 'name';
            break;
        case 'cmclass':
            $display = 'clsname';
            break;
        default:
            break;
    }

    //unique id for this menu item
    $item_id = "{$type}_{$instance->id}";

    //create appropriate page type with correct parameters
    $page = new menuitempage("{$type}page", '', $params);

    //create the menu item
    $result = new menuitem($item_id, $page, $parent, $instance->$display, $css_class, '', true, $parent_path);

    $current_path = '';
    if (in_array($type, array('cluster', 'curriculum', 'course', 'track', 'cmclass'))) {
        $current_path = $type . '-' . $instance->id;
        
        if (!empty($parent_path)) {
            $current_path = $parent_path . '/' . $current_path;
        }
    }
    
    //put key info into this id for later use
    $result->contentElId = "{$type}_{$instance->id}_{$parent_cluster_id}_{$parent_curriculum_id}_{$current_path}";

    //is this a leaf node?
    $result->isLeaf = $isLeaf;

    //convert to a leaf is appropriate
    block_curr_admin_truncate_leaf($type, $result, $parent_cluster_id, $parent_curriculum_id);

    return $result;
}

/**
 * Helper method for creating a summary item
 * @param   string    $type         The type of CM entity we are summarizing
 * @param   string    $css_class    CSS class used for styling this item
 * @param   int       $num_more     The number of items not being displayed
 * @param   array     $params       Any page params that are needed
 * @param   string    $classfile    The name of the PHP file containing the page class
 * @param   string    $parent_path  Path of parent curriculum elements in the tree
 * @return  menuitem                The appropriate menu item
 */
function block_curr_admin_get_menu_summary_item($type, $css_class, $num_more, $params = array(), $classfile='', $parent_path = '') {

    //the id of this element doesn't really matter, just make sure it's unique
    static $index = 1;
    //just to be extra sure of uniqueness
    $time = time();

    $page = new menuitempage($type . 'page', $classfile, $params);

    //display text
    if($num_more == 1) {
        $display = get_string('menu_summary_item_' . $type . '_singular', 'block_curr_admin', $num_more);
    } else {
        $display = get_string('menu_summary_item_' . $type, 'block_curr_admin', $num_more);
    }

    //create a new menuitem that is flagged as sensitive to JS inclusion
    $id = $type . '_summary_' . $index . '_' . $time;
    $result = new menuitem($id, $page, 'root', $display, $css_class, '', true, $parent_path);

    //summary items should never have children
    $result->isLeaf = true;

    $index++;

    return $result;
}

/**
 * Makes the specified node a leaf is the appropriate criteria is met
 *
 * @param  string    $type                  The type of entity the supplied node represents
 * @param  menuitem  $menuitem              The current menu item
 * @param  int       $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param  int       $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 */
function block_curr_admin_truncate_leaf($type, &$menuitem, $parent_cluster_id, $parent_curriculum_id) {
    //any cluster under a curriculum should be a leaf node
    if($type == 'cluster' && !empty($parent_curriculum_id)) {
        $menuitem->isLeaf = true;
    }

    //any class should also be a leaf node
    if($type == 'cmclass') {
        $menuitem->isLeaf = true;
    }
}

/**
 * Dynamically loads child menu items for a CM entity
 *
 * @param   string          $type                  The type of entity
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem array                         The appropriate child items
 */
function block_curr_admin_load_menu_children($type, $id, $parent_cluster_id, $parent_curriculum_id, $parent_path = '') {
    global $CURMAN;

    $function_name = "block_curr_admin_load_menu_children_{$type}";

    $result_items = array(new menuitem('root'));
    $extra_results = array();

    if(function_exists($function_name)) {
        $num_block_icons = isset($CURMAN->config->num_block_icons) ? $CURMAN->config->num_block_icons : 5;

        $extra_results = call_user_func($function_name, $id, $parent_cluster_id, $parent_curriculum_id, $num_block_icons, $parent_path);
    }

    return array_merge($result_items, $extra_results);
}

/**
 * Dynamically loads child menu items for a cluster entity
 *
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   int             $num_block_icons       Max number of entries to display
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem array                         The appropriate child items
 */
function block_curr_admin_load_menu_children_cluster($id, $parent_cluster_id, $parent_curriculum_id, $num_block_icons, $parent_path = '') {
    $result_items = array();

    /*****************************************
     * Cluster - Child Cluster Associations
     *****************************************/
    $cluster_css_class = block_curr_admin_get_item_css_class('cluster_instance');

    $listing = cluster_get_listing('priority, name', 'ASC', 0, $num_block_icons, '', '', array('parent' => $id));

    if(!empty($listing)) {
        foreach($listing as $item) {
            $params = array('id' => $item->id,
                            'action' => 'view');

            $cluster_count = cluster_count_records('', '', array('parent' => $item->id));
            $curriculum_count = clustercurriculum::count_curricula($item->id);

            $isLeaf = empty($cluster_count) &&
                      empty($curriculum_count);

            $result_items[] = block_curr_admin_get_menu_item('cluster', $item, 'root', $cluster_css_class, $item->id, $parent_curriculum_id, $params, $isLeaf, $parent_path);
        }
    }

    //summary item
    $num_records = cluster_count_records('', '', array('parent' => $id));
    if($num_block_icons < $num_records) {
        $params = array('id' => $parent_cluster_id);
        $result_items[] = block_curr_admin_get_menu_summary_item('cluster', $cluster_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    /*****************************************
     * Cluster - Curriculum
     *****************************************/
    $curriculum_css_class = block_curr_admin_get_item_css_class('curriculum_instance');

    $curricula = clustercurriculum::get_curricula($id, 0, $num_block_icons, 'cur.priority ASC, cur.name ASC');

    if(!empty($curricula)) {
        foreach($curricula as $curriculum) {
            $curriculum->id = $curriculum->curriculumid;
            $params = array('id' => $curriculum->id,
                            'action' => 'view');

            $course_count = curriculumcourse_count_records($curriculum->id);
            $track_count = track_count_records('', '', $curriculum->id, $parent_cluster_id);
            $cluster_count = clustercurriculum::count_clusters($curriculum->id, $parent_cluster_id);

            $isLeaf = empty($course_count) &&
                      empty($track_count) &&
                      empty($cluster_count);

            $result_items[] = block_curr_admin_get_menu_item('curriculum', $curriculum, 'root', $curriculum_css_class, $parent_cluster_id, $curriculum->id, $params, $isLeaf, $parent_path);
        }
    }

    //summary item
    $num_records = clustercurriculum::count_curricula($id);
    if($num_block_icons < $num_records) {
        $params = array('id' => $id);
        $result_items[] = block_curr_admin_get_menu_summary_item('clustercurriculum', $curriculum_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    return $result_items;
}

/**
 * Dynamically loads child menu items for a curriculum entity
 *
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   int             $num_block_icons       Max number of entries to display
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem array                         The appropriate child items
 */
function block_curr_admin_load_menu_children_curriculum($id, $parent_cluster_id, $parent_curriculum_id, $num_block_icons, $parent_path = '') {
    $result_items = array();

    /*****************************************
     * Curriculum - Course Associations
     *****************************************/
    $course_css_class = block_curr_admin_get_item_css_class('course_instance');

    $listing = curriculumcourse_get_listing($id, 'position', 'ASC', 0, $num_block_icons);

    if(!empty($listing)) {
        foreach($listing as $item) {
            $item->id = $item->courseid;
            $params = array('id'     => $item->id,
                            'action' => 'view');
                            
            $class_count = cmclass_count_records('', '', $item->id, false, null, $parent_cluster_id);

            $isLeaf = empty($class_count);

            $result_items[] = block_curr_admin_get_menu_item('course', $item, 'root', $course_css_class, $parent_cluster_id, $parent_curriculum_id, $params, $isLeaf, $parent_path);
        }
    }

    //summary item
    $num_records = curriculumcourse_count_records($id);
    if($num_block_icons < $num_records) {
        $params = array('id' => $id);
        $result_items[] = block_curr_admin_get_menu_summary_item('curriculumcourse', $course_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    /*****************************************
     * Curriculum - Track Associations
     *****************************************/
    $track_css_class = block_curr_admin_get_item_css_class('track_instance');

    if($track_records = track_get_listing('name', 'ASC', 0, $num_block_icons, '', '', $id, $parent_cluster_id)) {
        foreach($track_records as $track_record) {
            $params = array('id'     => $track_record->id,
                            'action' => 'view');

            $class_count = track_assignment_count_records($track_record->id);
            $cluster_count = clustertrack::count_clusters($track_record->id, $parent_cluster_id);

            $isLeaf = empty($class_count) &&
                      empty($cluster_count);
                                            
            $result_items[] = block_curr_admin_get_menu_item('track', $track_record, 'root', $track_css_class, $parent_cluster_id, $parent_curriculum_id, $params, $isLeaf, $parent_path);
        }
    }

    //summary item
    $num_records = track_count_records('', '', $id, $parent_cluster_id);
    if($num_block_icons < $num_records) {
        $params = array('id' => $id);

        //add extra param if appropriate
        if(!empty($parent_cluster_id)) {
            $params['parent_clusterid'] = $parent_cluster_id;
        }
        $result_items[] = block_curr_admin_get_menu_summary_item('track', $track_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    /*****************************************
     * Curriculum - Cluster Associations
     *****************************************/
    $cluster_css_class = block_curr_admin_get_item_css_class('cluster_instance');

    $clusters = clustercurriculum::get_clusters($id, $parent_cluster_id, 'priority, name', 'ASC', 0, $num_block_icons);

    if(!empty($clusters)) {
        foreach($clusters as $cluster) {
            $cluster->id = $cluster->clusterid;
            $params = array('id'     => $cluster->id,
                            'action' => 'view');
            $result_items[] = block_curr_admin_get_menu_item('cluster', $cluster, 'root', $cluster_css_class, $cluster->id, $parent_curriculum_id, $params, false, $parent_path);
        }
    }

    //summary item
    $num_records = clustercurriculum::count_clusters($id, $parent_cluster_id);
    if($num_block_icons < $num_records) {
        $params = array('id' => $id);

        //add extra param if appropriate
        if(!empty($parent_cluster_id)) {
            $params['parent_clusterid'] = $parent_cluster_id;
        }

        $result_items[] = block_curr_admin_get_menu_summary_item('curriculumcluster', $cluster_css_class, $num_records - $num_block_icons, $params, 'clustercurriculumpage.class.php', $parent_path);
    }

    return $result_items;
}

/**
 * Dynamically loads child menu items for a track entity
 *
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   int             $num_block_icons       Max number of entries to display
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem array                         The appropriate child items
 */
function block_curr_admin_load_menu_children_track($id, $parent_cluster_id, $parent_curriculum_id, $num_block_icons, $parent_path = '') {
    $result_items = array();

    /*****************************************
     * Track - Class Associations
     *****************************************/
    $class_css_class = block_curr_admin_get_item_css_class('class_instance');

    $listing = track_assignment_get_listing($id, 'cls.idnumber', 'ASC', 0, $num_block_icons);

    if(!empty($listing)) {
        foreach($listing as $item) {
            $item->id = $item->classid;
            $params = array('id'     => $item->id,
                            'action' => 'view');
            $result_items[] = block_curr_admin_get_menu_item('cmclass', $item, 'root', $class_css_class, $parent_cluster_id, $parent_curriculum_id, $params, false, $parent_path);
        }
    }

    //summary item
    $num_records = track_assignment_count_records($id);
    if($num_block_icons < $num_records) {
        $params = array('id' => $id);
        $result_items[] = block_curr_admin_get_menu_summary_item('trackassignment', $class_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    /*****************************************
     * Track - Cluster Associations
     *****************************************/
    $cluster_css_class = block_curr_admin_get_item_css_class('cluster_instance');

    $clusters = clustertrack::get_clusters($id, 0, 'priority, name', 'ASC', $num_block_icons, $parent_cluster_id);

    if(!empty($clusters)) {
        foreach($clusters as $cluster) {
            $cluster->id = $cluster->clusterid;
            $params = array('id'     => $cluster->id,
                            'action' => 'view');

            $result_items[] = block_curr_admin_get_menu_item('cluster', $cluster, 'root', $cluster_css_class, $cluster->id, $parent_curriculum_id, $params, false, $parent_path);
        }
    }

    //summary item
    $num_records = clustertrack::count_clusters($id, $parent_cluster_id);
    if($num_block_icons < $num_records) {
        $params = array('id' => $id);

        //add extra param if appropriate
        if(!empty($parent_cluster_id)) {
           $params['parent_clusterid'] = $parent_cluster_id;
        }

        $result_items[] = block_curr_admin_get_menu_summary_item('trackcluster', $cluster_css_class, $num_records - $num_block_icons, $params, 'clustertrackpage.class.php', $parent_path);
    }

    return $result_items;
}

/**
 * Dynamically loads child menu items for a course entity
 *
 * @param   int             $id                    The entity id
 * @param   int             $parent_cluster_id     The last cluster passed going down the curr_admin tree, or 0 if none
 * @param   int             $parent_curriculum_id  The last curriculum passed going down the curr_admin tree, or 0 if none
 * @param   int             $num_block_icons       Max number of entries to display
 * @param   string          $parent_path           Path of parent curriculum elements in the tree
 * @return  menuitem array                         The appropriate child items
 */
function block_curr_admin_load_menu_children_course($id, $parent_cluster_id, $parent_curriculum_id, $num_block_icons, $parent_path = '') {
    $result_items = array();

    /*****************************************
     * Course - Class Associations
     *****************************************/
    $class_css_class = block_curr_admin_get_item_css_class('class_instance');

    $listing = cmclass_get_listing('crsname', 'asc', 0, $num_block_icons, '', '', $id, false, null, $parent_cluster_id);

    if(!empty($listing)) {
        foreach($listing as $item) {
            $item->clsname = $item->idnumber;
            $params = array('id' => $item->id,
                            'action' => 'view');
            $result_items[] = block_curr_admin_get_menu_item('cmclass', $item, 'root', $class_css_class, $parent_cluster_id, $parent_curriculum_id, $params, false, $parent_path);
        }
    }

    //summary item
    $num_records = cmclass_count_records('', '', $id, false, null, $parent_cluster_id);
    if($num_block_icons < $num_records) {
        $params = array('action'           => 'default',
                        'id'               => $id);

        //add extra param if appropriate
        if(!empty($parent_cluster_id)) {
            $params['parent_clusterid'] = $parent_cluster_id;
        }

        $result_items[] = block_curr_admin_get_menu_summary_item('cmclass', $class_css_class, $num_records - $num_block_icons, $params, '', $parent_path);
    }

    return $result_items;
}

/**
 * Calculates the full list of css classes for a particular menu item
 *
 * @param   string   $class     Any classes we automatically want included
 * @param   boolean  $category  Whether the current node is a category / folder
 *
 * @return  string              The full CSS class attribute string
 */
function block_curr_admin_get_item_css_class($class, $category = false) {
    $category_css = empty($category) ? '' : ' category';

    //handle empty class
    $class = trim($class);
    if(empty($class)) {
        return "$category_css tree_icon";
    }

    //split up the class string
    $class_strings = explode(' ', $class);
    $valid_classes = array();

    //prefix each token
    foreach($class_strings as $class_string) {
        $trimmed = trim($class_string);

        if(!empty($trimmed)) {
            $valid_classes[] = 'curr_' . $trimmed;
        }
    }

    //add necessary classes
    return implode(' ', $valid_classes) . " $category_css tree_icon";

    return '';
}

/**
 * Functions specifically for adding PHP report links to the Curr Admin menu
 */

/**
 * Specifies the mapping of tree category shortnames to display names
 *
 * @return  array  Mapping of tree category shortnames to display names,
 *                 in the order they should appear
 */
function block_curr_admin_get_tree_category_mapping() {
    global $CFG;
    require_once($CFG->dirroot . '/blocks/php_report/php_report_base.php');

    //categories, in a pre-determined order
    return array(php_report::CATEGORY_CURRICULUM    => get_string('curriculum_reports',    'block_php_report'),
                 php_report::CATEGORY_COURSE        => get_string('course_reports',        'block_php_report'),
                 php_report::CATEGORY_CLASS         => get_string('class_reports',         'block_php_report'),
                 php_report::CATEGORY_CLUSTER       => get_string('cluster_reports',       'block_php_report'),
                 php_report::CATEGORY_PARTICIPATION => get_string('participation_reports', 'block_php_report'),
                 php_report::CATEGORY_USER          => get_string('user_reports',          'block_php_report'),
                 php_report::CATEGORY_ADMIN         => get_string('admin_reports',         'block_php_report'),
                 php_report::CATEGORY_OUTCOMES      => get_string('outcomes_reports',      'block_php_report'));
}

/**
 * Flattens a group of bucketed menu items into a one-dimensional
 * array suitable for use when building the curr admin tree
 *
 * @param   menuitem array  $buckets  Two-dimensional array of menu items
 *                                    indexed by report category, then report display name
 *
 * @return  menuitem array            One-dimensional array of menu items, sorted such that
 *                                    reports in the same category are ordered relative to one another
 *                                    by display name
 */
function block_curr_admin_get_report_bucket_items($buckets) {
    $pages = array();

    if (count($buckets) > 0) {
        //sort the elements in each bucket by report display name
        foreach ($buckets as $key => $bucket) {
            ksort($bucket);
            $buckets[$key] = $bucket;
        }

        //append all pages to the listing in an order that
        //guantees elements within a folder are sorted alphabetically
        foreach ($buckets as $bucket) {
            foreach ($bucket as $entry) {
                $pages[] = $entry;
            }
        }
    }

    return $pages;
}

/**
 * Specifies the entries needed in the menu corresponding to
 * report categories
 *
 * @return  menuitem array  Array of menuitems representing report categories,
 *                          not including the report items themselves
 */
function block_curr_admin_get_report_category_items() {
    $pages = array();

    //mapping of categories to displaynames
    $report_category_mapping = block_curr_admin_get_tree_category_mapping();

    //add all category entries to the tree under the reports entry
    foreach ($report_category_mapping as $key => $value) {
        //css clas for this menu item
        $css_class = block_curr_admin_get_item_css_class('reportcategory', TRUE);
        //construct the item itself
        $pages[] = new menuitem($key, NULL, 'rept', $value, $css_class, '', FALSE, 'rept');
    }

    return $pages;
}

/**
 * Specifies the tree entries used to represent links to PHP reports
 *
 * @return  menuitem array  List of menu items to add (including report categories
 *                          but excluding the top-level report entry)
 */
function block_curr_admin_get_report_tree_items() {
    global $CFG;

    //if the reports block is not installed, no entries will be displayed
    if (!record_exists('block', 'name', 'php_report')) {
        return array();
    }

    //get the category-level links
    $items = block_curr_admin_get_report_category_items();

    //path to library file for scheduling classes
    $schedulelib_path = $CFG->dirroot . '/blocks/php_report/lib/schedulelib.php';

    //check to make sure the required functionality is there
    //todo: remove this check when it's safe to do so
    if (file_exists($schedulelib_path)) {
        //reporting base class
        require_once($schedulelib_path);

        //schedule report entry
        $test_permissions_page = new scheduling_page();

        //make sure we can access the report listing
        if ($test_permissions_page->can_do('list')) {
            //create a direct url to the list page
            $schedule_reports_page = new menuitempage('url_page', 'lib/menuitem.class.php', $CFG->wwwroot . '/blocks/php_report/schedule.php?action=list');
            //convert to a menu item
            $css_class = block_curr_admin_get_item_css_class('schedulereports');
            $schedule_reports_item = new menuitem('schedule_reports', $schedule_reports_page, 'rept', get_string('schedule_reports', 'block_php_report'), $css_class, '', FALSE, 'rept');
            //merge in with the current result 
            $items = array_merge(array($schedule_reports_item), $items);
        }
    }

    //for storing the items bucketed by category
    $buckets = array();

    //look for all report instances
    if ($handle = opendir($CFG->dirroot . '/blocks/php_report/instances')) {
        while (FALSE !== ($report_shortname = readdir($handle))) {

            //grab a test instance of the report in question
            $default_instance = php_report::get_default_instance($report_shortname);

            //make sure the report shortname is valid
            if ($default_instance !== FALSE) {

                //make sure the current user can access this report
                if ($default_instance->is_available() &&
                    $default_instance->can_view_report()) {

                    //user-friendly report name
                    $displayname = $default_instance->get_display_name();

                    //add the item to the necessary bucket
                    $item_category = $default_instance->get_category();
                    if (!isset($buckets[$item_category])) {
                        $buckets[$item_category] = array();
                    }

                    //obtain the page specific to this report
                    $report_page_classpath = $CFG->dirroot . '/blocks/php_report/lib/reportpage.class.php';
                    $report_page_params = array('report' => $report_shortname);
                    $page = new generic_menuitempage('report_page', $report_page_classpath, $report_page_params);

                    //retrieve the actual menuitem
                    $page_css_class = block_curr_admin_get_item_css_class('reportinstance');
                    $category_path = 'rept/' . $item_category;
                    $buckets[$item_category][$displayname] = new menuitem($report_shortname, $page, $item_category,
                                                                          $displayname, $page_css_class, '', FALSE, $category_path);
                }
            }
        }
    }

    //retrieve the items representing the reports themselves from the bucketed listings
    $report_instance_items = block_curr_admin_get_report_bucket_items($buckets);

    //merge the flat listings of category items and report instance items
    $items = array_merge($items, $report_instance_items);

    //return the flat listing
    return $items;
}

?>
