<?php

defined('MOODLE_INTERNAL') || die();

// Custom field help
$string['elis_userset_theme'] = 'Theme';
$string['elis_userset_theme_help'] = '<p>This setting selects a theme to link to this particular User Set.</p>
<p>The selected theme will override the default theme seen on this site for users enrolled in this User Set if it has the highest prioritization out of all themes assigned to a user\'s User Sets.</p>';

$string['elis_userset_themepriority'] = 'Theme Priority';
$string['elis_userset_themepriority_help'] = '<p>This setting determines the priority of this User Set\'s associated themes in determining which to use for a particular user.</p>
<p>For any particular user, the themes on all assigned User Sets will be ranked based on this value, and the highest-priority theme will be used. Themes with higher priority should be assigned larger values than those with lower priority.</p>
<p>This setting should be used in conjunction with associating a theme to this User Set.</p>
<p>Please use distinct values whenever possible to avoid arbitrary prioritization.</p>';

$string['pluginname'] = 'User Set Display Themes';
$string['userset_theme'] = 'Theme';
$string['userset_theme_category'] = 'User Set Theme';
$string['userset_theme_priority'] = 'Theme Priority';

