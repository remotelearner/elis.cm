<?php
/**
 * Display a search box.
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

class cmsearchbox {
    function __construct($page) {
        $this->page = $page;
    }

    function display() {
        $search = trim(optional_param('search', '', PARAM_TEXT));
        $alpha = optional_param('alpha', '', PARAM_ALPHA);

        // TODO: with a little more work, we could keep the previously selected sort here
        $params = $_GET;
        unset($params['page']); // We want to go back to the first page
        unset($params['search']); // And clear the search

        $target = $this->page->get_new_page($params);

        echo "<table class=\"searchbox\" style=\"margin-left:auto;margin-right:auto\" cellpadding=\"10\"><tr><td>";
        echo "<form action=\"" . $target->get_url() . "\" method=\"post\">";
        echo "<fieldset class=\"invisiblefieldset\">";
        echo "<input type=\"text\" name=\"search\" value=\"" . s($search, true) . "\" size=\"20\" />";
        echo '<input type="submit" value="'.get_string('search').'" />';

        if ($search) {
            echo "<input type=\"button\" onclick=\"document.location='" . $target->get_url() . "';\" " .
                 "value=\"Show all items\" />";
        }

        echo "</fieldset></form>";
        echo "</td></tr></table>";
    }
}
