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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once (CURMAN_DIRLOCATION . '/lib/newpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/report.class.php');


/// The main management page.
class reportpage extends newpage {
    var $pagename = 'rep';
    var $section = 'rept';

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:viewreports', $context);
    }

    function action_default() {
        global $CFG;

        $type         = cm_get_param('type', '');
        $sort         = cm_get_param('sort', '');
        $dir          = cm_get_param('dir', 'ASC');
        $page         = cm_get_param('page', 0);
        $perpage      = cm_get_param('perpage', 30);
        $download     = cm_get_param('download', '');
        $frompage     = cm_get_param('frompage', '');


        if (!empty($type) && file_exists(CURMAN_DIRLOCATION . '/lib/' . $type . 'report.class.php')) {
            require_once CURMAN_DIRLOCATION . '/lib/' . $type . 'report.class.php';

            $repclass = $type . 'report';
            $report   = new $repclass($repclass);

            $report->set_baseurl('index.php?s=rep&section=rept&type=' . $type);
            $report->main($sort, $dir, $page, $perpage, $download, $frompage);

            return;
        }

        $bc = '<span class="breadcrumb">' . get_string('reports', 'block_curr_admin') . '</span>';

        echo cm_print_heading_block($bc, '', true);
        echo '<br clear="all" />' . "\n";
        echo get_string('choose_report_from_menu', 'block_curr_admin');
    }
}

?>
