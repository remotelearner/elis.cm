<?php

defined('MOODLE_INTERNAL') || die();

$string['autoenrol_groupings'] = 'Autoenrol users in groupings';
$string['autoenrol_groupings_help'] = '<p>Enabling this setting allows groupings to be automatically created for groups that are created automatically based on User Sets.</p>
<p>For this functionality to work, User Set grouping auto-assignment must be enabled globally for the site level and groupings must also be enabled on this site. This functionality does not currently extend to groups in courses.</p>';

$string['userset_group'] = 'Enable Corresponding Group';
$string['userset_group_category'] = 'Associated Group';
$string['userset_group_help'] = '<p>Enabling this setting allows groups to be automatically created for user population based on User Set enrolment.</p>

<p>For this functionality to work, User Set group auto-assignment must be enabled globally for either the site or course level.</p>';

$string['userset_groupings'] = 'Allow front page grouping creation from User Set-based groups';
$string['userset_groups'] = 'Allow course-level group population from User Sets';
$string['userset_grp_settings'] = 'User Set Group Settings';
$string['fp_grp_userset_help'] = 'Enabling this setting allows the Program Management system to automatically add groups to groupings in the front-page. Groupings will be created as needed.

For this to work, the associated User Set setting must be turned on for each appropriate User Set as well.

Also, be cautious when enabling this setting, as it will cause the Program Management system to immediately search for all appropriate groups for all necessary groupings, which may take a long time.';
$string['fp_grp_userset_setting'] = 'Allow front page grouping creation from User-Set-based groups';
$string['fp_pop_userset_help'] = 'Enabling this setting allows the Program Management system to automatically add users to groups in the front-page based on User Set membership. Groups will be created as needed.

For this to work, the associated User Set setting must be turned on for each appropriate User Set as well.

Also, be cautious when enabling this setting, as it will cause the Program Management system to immediately search for all appropriate users across all necessary User Sets, which may take a long time.';
$string['fp_pop_userset_setting'] = 'Allow front page group population from User Sets';
$string['frontpagegroupings'] = 'Front page groupings';
$string['frontpagegroups'] = 'Front page groups';
$string['grp_pop_userset_help'] = 'Enabling this setting allows the Program Management system to automatically add users to groups in Moodle courses based on User Set membership. Groups will be created as needed.

For this to work, the associated User Set setting must be turned on for each appropriate User Set as well.

Also, be cautious when enabling this setting, as it will cause the Program Management system to immediately search for all appropriate users across all necessary User Sets, which may take a long time.';
$string['grp_pop_userset_setting'] = 'Allow course-level group population from User Sets';
$string['pluginname'] = 'User Set Groups';
$string['site_course_userset_groups'] = 'Allow front page group population from User Sets';
