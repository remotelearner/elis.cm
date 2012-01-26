<?php
/**
 * Display a first-letter filtering box.
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

class cmalphabox {
    function __construct($page) {
        $this->page = $page;
    }

    function display() {
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        $alphabet = explode(',', get_string('alphabet'));
        $strall = get_string('all');

        $params = $_GET;

        $params['id'] = isset($params['id']) ? $params['id'] : $this->page->optional_param('id', 0, PARAM_INT); // Grab from _POST if _GET value is missing

        unset($params['page']); // We want to go back to the first page

        echo '<p style="text-align:center">';

        if ($alpha) {
            $newparams = $params;
            unset($newparams['alpha']);
            $target = $this->page->get_new_page($newparams);
            echo '<a href="' . $target->get_url() . '">' . $strall . '</a>';
        } else {
            echo '<b>' . $strall . '</b>';
        }

        foreach ($alphabet as $letter) {
            if ($letter == $alpha) {
                echo " <b>$letter</b> ";
            } else {
                $target = $this->page->get_new_page(array_merge($params, array('alpha' => $letter)));
                echo ' <a href="' . $target->get_url() . '">' . $letter . '</a>';
            }
        }
        echo "</p>";
    }
}
