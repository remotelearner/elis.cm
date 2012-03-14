<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once elis::lib('data/customfield.class.php');
require_once elispm::lib('page.class.php');
require_once elis::lib('table.class.php');

class customfieldpage extends pm_page {
    var $pagename = 'field';
    var $section = 'admn';

    var $params = array();

    function __construct(array $params=null) {
        $this->params = $this->_get_page_params();
        parent::__construct($params);
    }

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:config', $context) || has_capability('elis/program:manage', $context);
    }

    function display_default() {
        global $CFG, $DB, $OUTPUT;
        $level = $this->required_param('level', PARAM_ACTION);
        $ctxlvl = context_level_base::get_custom_context_level($level, 'elis_program');
        if (!$ctxlvl) {
            print_error('invalid_context_level', 'elis_program');
        }

        $tmppage = new moodle_url($this->url);
        $tabs = array();
        require $CFG->dirroot.'/elis/program/db/access.php';
        foreach($contextlevels as $contextlevel => $val) {
            $tmppage->param('level', $contextlevel);
            $tabs[] = new tabobject($contextlevel, $tmppage->out(), get_string($contextlevel, 'elis_program'));
        }
        print_tabs(array($tabs), $level);

        $fields = field::get_for_context_level($ctxlvl);
        $fields = $fields ? $fields : array();

        $categories = field_category::get_for_context_level($ctxlvl);
        $categories = $categories ? $categories : array();

        // divide the fields into categories
        $category_names = array();
        $fieldsbycategory = array();
        foreach ($categories as $category) {
            $category_names[$category->id] = $category->name;
            $fieldsbycategory[$category->id] = array();
        }
        foreach ($fields as $field) {
            $fieldsbycategory[$field->categoryid][] = $field;
        }

        $deletetxt = get_string('delete');
        $edittxt = get_string('edit');
        $syncerr = false;
        if (empty($category_names)) {
            echo $OUTPUT->heading(get_string('field_no_categories_defined', 'elis_program'));
        }
        foreach ($fieldsbycategory as $categoryid => $fields) {
            $categorypage = new moodle_url($this->url);
            $categorypage->params(array('action' => 'deletecategory',
                                        'id' => $categoryid,
                                        'level' => $level)
                                 );
            $deletelink = $categorypage->out();

            $categorypage->param('action', 'editcategory');
            $editlink = $categorypage->out();

            if (isset($category_names[$categoryid])) {
                echo "<h2>{$category_names[$categoryid]} <a href=\"$editlink\">";
                echo "<img src=\"".$OUTPUT->pix_url('edit','elis_program')."\" alt=\"$edittxt\" title=\"$edittxt\" /></a>";
                echo "<a href=\"$deletelink\"><img src=\"".$OUTPUT->pix_url('delete','elis_program')."\" alt=\"$deletetxt\" title=\"$deletetxt\" /></a>";
                echo "</h2>\n";
            }

            if (empty($fields)) {
                print_string('field_no_fields_defined', 'elis_program');
            } else {
                if ($level == 'user') {
                    require_once(elis::plugin_file('elisfields_moodle_profile', 'custom_fields.php'));
                    $table = new customuserfieldtable($fields, array('name' => array('header' => get_string('name')),
                                                                     'datatype' => array('header' => get_string('field_datatype', 'elis_program')),
                                                                     'syncwithmoodle' => array('header' => get_string('field_syncwithmoodle', 'elis_program')),
                                                                     'buttons' => array('header' => '')), $this->url);
                } else {
                    $table = new customfieldtable($fields, array('name' => array('header' => get_string('name')),
                                                                 'datatype' => array('header' => 'Data type'),
                                                                 'buttons' => array('header' => '')), $this->url);
                }
                echo $table->get_html();
                $syncerr = $syncerr || !empty($table->syncerr);
            }
        }
        if ($syncerr) {
            print_string('moodle_field_sync_warning', 'elis_program');
        }

        // button for new category
        $options = array('s' => 'field',
                         'action'=>'editcategory',
                         'level' => $level);
        $button = new single_button(new moodle_url('index.php', $options), get_string('field_create_category', 'elis_program'), 'get', array('disabled'=>false, 'title'=>get_string('field_create_category', 'elis_program'), 'id'=>''));
        echo $OUTPUT->render($button);

        if (!empty($category_names)) {
            if ($level == 'user') {
                // create new field from Moodle field
                $select = 'shortname NOT IN (SELECT shortname FROM {'.field::TABLE.'})';
                $moodlefields = $DB->get_records_select('user_info_field', $select, array('sortorder'=>'id,name'));
                $moodlefields = $moodlefields ? $moodlefields : array();
                $tmppage->param('action', 'editfield');
                $tmppage->param('from', 'moodle');
                $tmppage->param('level', 'user');
                echo '<div>';
                //popup_form("{$tmppage->url}&amp;id=",
                //           array_map(create_function('$x', 'return $x->name;'), $moodlefields),
                //           'frommoodleform', '', 'choose', '', '', false, 'self', get_string('field_from_moodle', 'elis_program'));
                $actionurl = new moodle_url($tmppage->out());
                $single_select = new single_select($actionurl, 'id', array_map(create_function('$x', 'return $x->name;'), $moodlefields), null, array(''=>get_string('field_from_moodle', 'elis_program')));
                echo $OUTPUT->render($single_select);
                echo '</div>';

                $options = array('s' => 'field',
                                 'action' => 'forceresync');
                $button = new single_button(new moodle_url('index.php', $options), get_string('field_force_resync', 'elis_program'), 'get', array('disabled'=>false, 'title'=>get_string('field_force_resync', 'elis_program'), 'id'=>''));
                echo $OUTPUT->render($button);
            } else {
                // create new field from scratch
                $options = array('s' => 'field',
                                 'action'=>'editfield',
                                 'level' => $level);
                $button = new single_button(new moodle_url('index.php', $options), get_string('field_create_new', 'elis_program'), 'get', array('disabled'=>false, 'title'=>get_string('field_create_new', 'elis_program'), 'id'=>''));
                echo $OUTPUT->render($button);
            }
        }
    }

    function display_forceresync() {
        global $CFG, $OUTPUT;

        $confirm = $this->optional_param('confirm', 0, PARAM_INT);
        if (!$confirm) {
            $optionsyes = array('s' => 'field',
                                'action' => 'forceresync',
                                'confirm' => 1
                               );
            $optionsno = array('s' => 'field',
                               'level' => 'user',
                              );

            $buttoncontinue = new single_button(new moodle_url('index.php', $optionsyes), get_string('yes'), 'POST');
            $buttoncancel   = new single_button(new moodle_url('index.php', $optionsno), get_string('no'), 'GET');

            echo $OUTPUT->confirm(get_string('field_confirm_force_resync', 'elis_program'),
                                  $buttoncontinue, $buttoncancel);
        } else {
            print_string('field_resyncing', 'elis_program');
            $ctxlvl = context_level_base::get_custom_context_level('user', 'elis_program');
            if (!$ctxlvl) {
                print_error('invalid_context_level', 'elis_program');
            }
            $fields = field::get_for_context_level($ctxlvl);
            $fields = $fields ? $fields : array();
            require_once(elis::plugin_file('elisfields_moodle_profile', 'custom_fields.php'));
            foreach ($fields as $field) {
                $fieldobj = new field($field);
                sync_profile_field_with_moodle($fieldobj);
            }
            $tmppage = new customfieldpage(array('level' => 'user'));
            redirect($tmppage->url, get_string('continue'));
        }
    }

    function display_editcategory() {
        require_once elispm::file('form/fieldcategoryform.class.php');

        $level = $this->required_param('level', PARAM_ACTION);
        $ctxlvl = context_level_base::get_custom_context_level($level, 'elis_program');
        if (!$ctxlvl) {
            print_error('invalid_context_level', 'elis_program');
        }
        $id = $this->optional_param('id', 0, PARAM_INT);
        $tmppage = new customfieldpage(array('level' => $level, 'id' => $id, 'action' => 'editcategory', 'level' => $level));
        $form = new fieldcategoryform($tmppage->url);
        if ($form->is_cancelled()) {
            $tmppage = new customfieldpage(array('level' => $level));
            redirect($tmppage->url, get_string('edit_cancelled', 'elis_program'));
        } else if ($data = $form->get_data()) {
            $data->id = $id;
            $category = new field_category($data);
            if ($category->id) {
                $category->save();
            } else {
                $category->save();
                // assume each category only belongs to one context level (for now)
                $categorycontext = new field_category_contextlevel();
                $categorycontext->categoryid = $category->id;
                $categorycontext->contextlevel = $ctxlvl;
                $categorycontext->save();
            }
            $tmppage = new customfieldpage(array('level' => $level));
            redirect($tmppage->url, get_string('field_category_saved', 'elis_program', $category->name));
        } else {
            if ($id) {
                $category = new field_category($id);
                $form->set_data($category);
            }
            $form->display();
        }
    }

    function display_deletecategory() {
        global $OUTPUT;

        $id = $this->required_param('id', PARAM_INT);
        $level = $this->required_param('level', PARAM_ACTION);

        $category = new field_category($id);

        if (!$category->id) {
            print_error('invalid_category_id', 'elis_program');
        }

        $confirm = $this->optional_param('confirm', 0, PARAM_INT);
        if ($confirm) {
            //load the fields into memory since the record is about to be deleted
            $category->load();

            $category->delete();
            $tmppage = new customfieldpage(array('level' => $level));
            redirect($tmppage->url, get_string('field_category_deleted', 'elis_program', $category->name));
        } else {
            $optionsyes = array('s' => $this->pagename,
                                'action' => 'deletecategory',
                                'id' => $id,
                                'confirm' => 1,
                                'level' => $level
                               );
            $optionsno = array('s' => $this->pagename,
                               'level' => $level,
                              );

            $buttoncontinue = new single_button(new moodle_url('index.php', $optionsyes), get_string('yes'), 'POST');
            $buttoncancel   = new single_button(new moodle_url('index.php', $optionsno), get_string('no'), 'GET');

            echo $OUTPUT->confirm(get_string('confirm_delete_category', 'elis_program', $category->name),
                                  $buttoncontinue, $buttoncancel);
        }
    }

    function display_movecategory() {
        // FIXME:
    }

    function display_editfield() {
        global $CFG, $DB;

        $level = $this->required_param('level', PARAM_ACTION);
        $ctxlvl = context_level_base::get_custom_context_level($level, 'elis_program');
        if (!$ctxlvl) {
            print_error('invalid_context_level', 'elis_program');
        }
        $id = $this->optional_param('id', NULL, PARAM_INT);

        require_once elispm::file('form/customfieldform.class.php');
        $tmppage = new customfieldpage(array('level' => $level, 'action' => 'editfield'), $this);
        $form = new customfieldform($tmppage->url, $this);
        if ($form->is_cancelled()) {
            $tmppage = new customfieldpage(array('level' => $level));
            redirect($tmppage->url, get_string('edit_cancelled', 'elis_program'));
        } else if ($data = $form->get_data()) {
            $field = new field($data);
            if ($id) {
                $field->id = $id;
                $field->save();
            } else {
                $field->save();
                // assume each field only belongs to one context level (for now)
                $fieldcontext = new field_contextlevel();
                $fieldcontext->fieldid = $field->id;
                $fieldcontext->contextlevel = $ctxlvl;
                $fieldcontext->save();
            }

            //don't use !empty here because we might be storing a 0 or similar value
            if ($data->defaultdata != '') {
                // save the default value
                $defaultdata = $data->defaultdata;
                if ($field->multivalued) {
                    // parse as a CSV string
                    // until we can use str_getcsv from PHP 5.3...
                    $temp=fopen("php://memory", "rw");
                    fwrite($temp, $defaultdata);
                    rewind($temp);
                    $defaultdata=fgetcsv($temp);
                    fclose($temp);
                }
                field_data::set_for_context_and_field(NULL, $field, $defaultdata);
            } else {
                if ($field->multivalued) {
                    field_data::set_for_context_and_field(NULL, $field, array());
                } else {
                    field_data::set_for_context_and_field(NULL, $field, NULL);
                }
            }

            $plugins = get_list_of_plugins('elis/core/fields');
            foreach ($plugins as $plugin) {
                if (is_readable($CFG->dirroot . '/elis/core/fields/' . $plugin . '/custom_fields.php')) {
                    require_once(elis::plugin_file('elisfields_'.$plugin, 'custom_fields.php'));
                    if (function_exists("{$plugin}_field_save_form_data")) {
                        call_user_func("{$plugin}_field_save_form_data", $form, $field, $data);
                    }
                }
            }

            $tmppage = new customfieldpage(array('level' => $level));
            redirect($tmppage->url, get_string('field_saved', 'elis_program', $field));
        } else {
            if (!empty($id)) {
                if ($this->optional_param('from', NULL, PARAM_CLEAN) == 'moodle' && $level == 'user') {
                    $moodlefield = $DB->get_record('user_info_field', array('id'=>$id));
                    if (!$moodlefield) {
                        print_error('invalid_field_id', 'elis_program');
                    }
                    unset($moodlefield->id);
                    $data_array = (array)$moodlefield;
                    $data_array['datatype'] = 'text';
                    $data_array['manual_field_control'] = $moodlefield->datatype;
                    switch ($moodlefield->datatype) {
                    case field::CHECKBOX:
                        $data_array['datatype'] = 'bool';
                        break;
                    case field::MENU:
                        $data_array['datatype'] = 'char';
                        $data_array['manual_field_options'] = $moodlefield->param1;
                        break;
                    case field::TEXTAREA:
                        $data_array['manual_field_columns'] = $moodlefield->param1;
                        $data_array['manual_field_rows'] = $moodlefield->param2;
                        break;
                    case field::TEXT:
                        if ($moodlefield->param3) {
                            $data_array['manual_field_control'] = 'password';
                        }
                        $data_array['manual_field_columns'] = $moodlefield->param1;
                        $data_array['manual_field_maxlength'] = $moodlefield->param2;
                        break;
                    }
                } else {
                    $data = new field($id);
                    $data_array = $data->to_array();

                    $field_record = $DB->get_record(field::TABLE, array('id'=>$id));
                    if (!empty($field_record)) {
                        foreach ($field_record as $field_item=>$field_value) {
                            $data_array[$field_item] = $field_value;
                        }
                    }

                    $defaultdata = field_data::get_for_context_and_field(NULL, $data);
                    if (!empty($defaultdata)) {
                        if ($data->multivalued) {
                            $values = array();
                            // extract the data
                            foreach ($defaultdata as $data) {
                                $values[] = $data->data;
                            }
                            // represent as a CSV string
                            $fh=fopen("php://memory", "rw");
                            fputcsv($fh, $values);
                            rewind($fh);
                            $defaultdata=fgets($fh);
                            fclose($fh);
                        } else {
                            foreach ($defaultdata as $defdata) {
                                $defaultdata = $defdata->data;
                                break;
                            }
                        }
                    }

                    $field = new field();

                    // Format decimal numbers
                    if(strcmp($data_array['datatype'],'num') == 0) {
                        $defaultdata = $field->format_number($defaultdata);
                    }

                    if (!is_object($defaultdata)) {
                        $data_array['defaultdata'] = $defaultdata;
                    }

                    $plugins = get_list_of_plugins('elis/core/fields');
                    foreach ($plugins as $plugin) {
                        if (is_readable($CFG->dirroot . '/elis/core/fields/' . $plugin . '/custom_fields.php')) {
                            include_once($CFG->dirroot . '/elis/core/fields/' . $plugin . '/custom_fields.php');
                            if (function_exists("{$plugin}_field_get_form_data")) {
                                $data_array += call_user_func("{$plugin}_field_get_form_data", $form, $data);
                            }
                        }
                    }
                }

                $form->set_data($data_array);
            }
            $form->display();
        }
    }

    function display_deletefield() {
        global $OUTPUT;

        $level = $this->required_param('level', PARAM_ACTION);
        $id = $this->required_param('id', PARAM_INT);

        $field = new field($id);

        if (!$field->id) {
            print_error('invalid_field_id', 'elis_program');
        }

        $confirm = $this->optional_param('confirm', 0, PARAM_INT);
        if ($confirm) {
            $field->delete();
            $tmppage = new customfieldpage(array('level' => $level));
            redirect($tmppage->url, get_string('field_deleted', 'elis_program', $field));
        } else {
            $optionsyes = array('s' => $this->pagename,
                                'action' => 'deletefield',
                                'id' => $id,
                                'confirm' => 1,
                                'level' => $level
                               );
            $optionsno = array('s' => $this->pagename,
                               'level' => $level,
                              );

            $buttoncontinue = new single_button(new moodle_url('index.php', $optionsyes), get_string('yes'), 'POST');
            $buttoncancel   = new single_button(new moodle_url('index.php', $optionsno), get_string('no'), 'GET');

            echo $OUTPUT->confirm(get_string('confirm_delete_field', 'elis_program',
                                             array('datatype'=>$field->datatype, 'name'=>$field->name)
                                            ),
                                  $buttoncontinue, $buttoncancel);
        }
    }

    function display_movefield() {
        // FIXME:
    }

    public function build_navbar_default() {
        parent::build_navbar_default();

        $url = $this->get_new_page(array('level'=>'user'), true)->url;
        $this->navbar->add(get_string("manage_custom_fields", 'elis_program'), $url);
    }
}

class customfieldtable extends display_table {
    function is_sortable_default() {
        return false;
    }

    function get_item_display_datatype($column, $item) {
        return get_string("field_datatype_{$item->datatype}", 'elis_program');
    }

    function get_item_display_buttons($column, $item) {
        global $CFG, $OUTPUT;

        $cfpage = new customfieldpage();
        $tmppage = new moodle_url($cfpage->url);
        $tmppage->params(array('action' => 'deletefield',
                               'level' => optional_param('level','',PARAM_CLEAN),
                               'id' => $item->id)
                        );
        $deletelink = $tmppage->out();
        $tmppage->param('action', 'editfield');
        $editlink = $tmppage->out();
        $deletetxt = get_string('delete');
        $edittxt = get_string('edit');
        return "<a href=\"{$editlink}\"><img src=\"".$OUTPUT->pix_url('edit','elis_program')."\" alt=\"{$edittxt}\" title=\"{$edittxt}\" /></a> " .
               "<a href=\"{$deletelink}\"><img src=\"".$OUTPUT->pix_url('delete','elis_program')."\" alt=\"{$deletetxt}\" title=\"{$deletetxt}\" /></a>";
    }
}

class customuserfieldtable extends customfieldtable {
    var $syncerr = false;

    function get_item_display_syncwithmoodle($column, $item) {
        if ($item->syncwithmoodle === NULL) {
            return get_string('field_no_sync', 'elisfields_moodle_profile');
        } elseif ($item->syncwithmoodle == pm_moodle_profile::sync_from_moodle) {
            $result = get_string('field_sync_from_moodle', 'elisfields_moodle_profile');
        } else {
            $result = get_string('field_sync_to_moodle', 'elisfields_moodle_profile');
        }
        if (empty($item->mfieldid)) {
            $this->syncerr = true;
            return "$result *";
        }
        return $result;
    }
}
