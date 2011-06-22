<?php

    require_once($CFG->libdir.'/blocklib.php');
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->dirroot.'/my/pagelib.php');


    /// My Moodle arguments:
    $edit        = optional_param('edit', -1, PARAM_BOOL);
    $blockaction = optional_param('blockaction', '', PARAM_ALPHA);

    if (!isloggedin()) {
        // Can only register if not logged in...
        /// Add curriculum stylesheets...
        if (file_exists($CFG->dirroot.'/curriculum/styles.css')) {
            $CFG->stylesheets[] = $CFG->wwwroot.'/curriculum/styles.css';
        }
        $newaccount = get_string('userregister', 'block_curr_admin');
        $navlinks = array();
        $navlinks[] = array('name' => $newaccount, 'link' => null, 'type' => 'misc');
        $navigation = build_navigation($navlinks);
        print_header($newaccount, $newaccount, $navigation);
        return;
    }
    require_login();

    $mymoodlestr = get_string('mymoodle','my');

    if (isguest()) {
        $wwwroot = $CFG->wwwroot.'/login/index.php';
        if (!empty($CFG->loginhttps)) {
            $wwwroot = str_replace('http:','https:', $wwwroot);
        }

        print_header($mymoodlestr);
        notice_yesno(get_string('noguest', 'my').'<br /><br />'.get_string('liketologin'),
                     $wwwroot, $CFG->wwwroot);
        print_footer();
        die();
    }

    /// Add curriculum stylesheets... (do this after 'require_login' as it reinitialized the themes)
    if (file_exists($CFG->dirroot.'/curriculum/styles.css')) {
        $CFG->stylesheets[] = $CFG->wwwroot.'/curriculum/styles.css';
    }

    /// Fool the page library into thinking we're in My Moodle.
    $CFG->pagepath = $CFG->wwwroot.'/my/index.php';
    $PAGE = page_create_instance($USER->id);

    if ($section = optional_param('section', '', PARAM_ALPHAEXT)) {
        $PAGE->section = $section;
    }
   
    $pageblocks = blocks_setup($PAGE,BLOCKS_PINNED_BOTH);


/// Make sure that the curriculum block is actually on this user's My Moodle page instance.
    if ($cablockid = get_field('block', 'id', 'name', 'curr_admin')) {
//        if (!record_exists('block_instance', 'blockid', $cablockid, 'pageid', $USER->id,
//                           'pagetype', 'my-index')) {
        if (!record_exists('block_pinned', 'blockid', $cablockid, 'pagetype', 'my-index')) {
//print_object('cablockid: ' . $cablockid);
            blocks_execute_action($PAGE, $pageblocks, 'add', (int)$cablockid, true, false);
        }
    }


    if (($edit != -1) and $PAGE->user_allowed_editing()) {
        $USER->editing = $edit;
    }

    $PAGE->print_header($mymoodlestr);

    echo '<table border="0" cellpadding="3" cellspacing="0" width="100%" id="layout-table">';
    echo '<tr valign="top">';


    $blocks_preferred_width = bounded_number(180, blocks_preferred_width($pageblocks[BLOCK_POS_LEFT]), 210);

    if(blocks_have_content($pageblocks, BLOCK_POS_LEFT) || $PAGE->user_is_editing()) {
        echo '<td style="vertical-align: top; width: '.$blocks_preferred_width.'px;" id="left-column">';
        blocks_print_group($PAGE, $pageblocks, BLOCK_POS_LEFT);
        echo '</td>';
    }

    echo '<td valign="top" id="middle-column">';

    if (blocks_have_content($pageblocks, BLOCK_POS_CENTRE) || $PAGE->user_is_editing()) {
        blocks_print_group($PAGE, $pageblocks, BLOCK_POS_CENTRE);
    }
?>
