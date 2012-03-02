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


class linkpage extends newpage {
    var $pagename = 'link';
    var $section = 'admn';

    function can_do_default() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);
        $cmuserid = cm_get_crlmuserid($USER->id);
        if($cmuserid != 0 && ($cmuserid == $id)) {
            return true;
        }
        return usermanagementpage::_has_capability('block/php_report:view');
    }

    function get_url() {
        global $CFG;
        $params_array = array();

        // Grab the main link parameter
        $link_url = !empty($this->params['linkurl']) ? $this->params['linkurl'] : ''; // the url portion for the link
        $link_params = !empty($this->params['linkparams']) ? $this->params['linkparams'] : ''; // the comma separated list of parameters we will look for

        // Check to see if URI needs to be prepended
        if (substr($link_url,0,7) != 'http://' && substr($link_url,0,8) != 'https://') {
            $link_url = $CFG->wwwroot . '/' . $link_url;
        }

        $link_params_array = (strlen($link_params) > 0) ? explode(',', $link_params) : array();

        // Append specified params to the link URL
        if (!empty($link_params_array)) {
            foreach ($link_params_array as $link_param) {
                $param_value = $this->params[$link_param];
                if (substr($param_value,0,1) == '=') {
                    list($junk,$rep_var) = explode('=', $param_value);
                    $param_value = (isset($this->params[$rep_var])) ? $this->params[$rep_var] : $param_value;
                }
                $params_array[] = $link_param . '=' . urlencode($param_value);
            }
        }

        if (!empty($params_array)) {
            $link_url .= '?' . implode('&', $params_array);
        }

        return $link_url;
    }

}

?>
