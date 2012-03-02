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

require_once (CURMAN_DIRLOCATION . '/lib/page.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/taginstance.class.php');
require_once (CURMAN_DIRLOCATION . '/cmclasspage.class.php');
require_once (CURMAN_DIRLOCATION . '/coursepage.class.php');
require_once (CURMAN_DIRLOCATION . '/curriculumpage.class.php');
require_once (CURMAN_DIRLOCATION . '/tagpage.class.php');

class taginstancebasepage extends associationpage {
    var $data_class = 'taginstance';

    function action_savenew() {
        $instanceid = required_param('instanceid', PARAM_INT);
        $tagid = required_param('tagid', PARAM_INT);

        $obj = new taginstance();

        $obj->tagid = $tagid;
        $obj->instanceid = $instanceid;
        $obj->instancetype = $this->instance_type;

        $obj->data_insert_record();

        $this->action_default();
    }

    function action_default() {
        $id = required_param('id', PARAM_INT);

        $columns = array(
            'name'        => get_string('tag_name', 'block_curr_admin'),
            'description' => get_string('tag_description', 'block_curr_admin'),
            'manage' => '',
        );

        $items = taginstance::get_instance_tags($this->instance_type, $id);

        $formatters = $this->create_link_formatters(array('name'), 'tagpage', 'tagid');

        $this->print_list_view($items, $columns, $formatters, 'tags');

        $this->print_dropdown(tag_get_listing(), $items, 'instanceid', 'tagid');
    }
}

class curtaginstancepage extends taginstancebasepage {
    var $pagename = 'curtag';
    var $tab_page = 'curriculumpage';

    var $section = 'curr';

    var $instance_type = 'cur';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return curriculumpage::_has_capability('block/curr_admin:curriculum:edit', $id);
    }
}

class crstaginstancepage extends taginstancebasepage {
    var $pagename = 'crstag';
    var $tab_page = 'coursepage';

    var $section = 'curr';

    var $instance_type = 'crs';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        return coursepage::_has_capability('block/curr_admin:course:edit', $id);
    }
}

class clstaginstancepage extends taginstancebasepage {
    var $pagename = 'clstag';
    var $tab_page = 'cmclasspage';

    var $section = 'curr';

    var $instance_type = 'cls';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        $cmclasspage = new cmclasspage(array('id' => $id));
        return $cmclasspage->can_do('edit');
    }
}

?>
