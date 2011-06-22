<?php
/**
 * General class for displaying pages in the curriculum management system.
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

require_once (CURMAN_DIRLOCATION.'/lib/managementpage.class.php');
require_once (CURMAN_DIRLOCATION.'/lib/tag.class.php');
require_once (CURMAN_DIRLOCATION.'/form/informationElementsForm.class.php');

class tagpage extends managementpage {
    var $data_class = 'tag';
    var $form_class = 'ieform';
    var $pagename = 'tag';
    var $section = 'info';

    var $view_columns = array('name', 'description');

    public function __construct($params=false) {
        $this->tabs = array(
        array('tab_id' => 'view', 'page' => get_class($this), 'params' => array('action' => 'view'), 'name' => 'Detail', 'showtab' => true),
        array('tab_id' => 'edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => 'Edit', 'showtab' => true, 'showbutton' => true, 'image' => 'edit.gif'),
        array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => 'Delete', 'showbutton' => true, 'image' => 'delete.gif'),
        );

        parent::__construct($params);
    }

    function can_do_add() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:tag:create', $context);
    }

    function can_do_edit() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:tag:edit', $context);
    }

    function can_do_delete() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:tag:delete', $context);
    }

    function can_do_confirm() {
        return $this->can_do_delete();
    }

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:tag:view', $context);
    }

    function action_default() {
        // Get parameters
        $sort         = optional_param('sort', 'name', PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);

        $page         = optional_param('page', 0, PARAM_INT);
        $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page

        $namesearch   = trim(optional_param('search', '', PARAM_TEXT));
        $alpha        = optional_param('alpha', '', PARAM_ALPHA);

        // Define columns
        $columns = array(
            'name'        => get_string('tag_name', 'block_curr_admin'),
            'description' => get_string('tag_description', 'block_curr_admin')
        );

        $items    = tag_get_listing($sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha);
        $numitems = tag_count_records($namesearch, $alpha);

        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);
    }
}
?>
