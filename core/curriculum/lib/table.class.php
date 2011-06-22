<?php
/**
 * Common code to display a table.
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

require_once CURMAN_DIRLOCATION . '/lib/lib.php';
require_once $CFG->libdir . '/pear/HTML/AJAX/JSON.php';

class display_table {
    var $table = null;
    var $items;
    var $columns;
    var $pageurl;
    var $decorators;

    /**
     * Create a new table object.
     *
     * @param array $items array (or other iterable) of items to be displayed
     * in the table.  Each element in the array should be an object, with
     * fields matching the names in {@var $columns} containing the data.
     * @param array $columns mapping of column data names to user-readable
     * names
     * @param moodle_url $pageurl base url to page, for changing sort order
     * @param array $decorators array of objects, indexed by column name, to
     * decorate table cells ({@see table_decorator})
     */
    function __construct($items, $columns, $pageurl, $decorators=array()) {
        $this->items = $items;
        $this->columns = $columns;
        $this->pageurl = $pageurl;
        $this->decorators = $decorators;

        $this->table = new stdClass();
        $this->table->width = '95%';

        $this->add_table_header();
    }

    private function build_table() {
        if (!empty($this->built)) {
            return;
        }

        $this->table->data = new table_data_iterator($this);

        $this->built = true;
    }


    function get_default_sort_column() {
        return 'name';
    }

    function add_table_header() {
        $sort         = optional_param('sort', null, PARAM_ALPHA);
        $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
        $id           = optional_param('id', null, PARAM_INT);

        global $CFG;

        if ($sort === null) {
            $sort = $this->get_default_sort_column();
        }

        // Set HTML for table columns (URL, column name, sort icon)
        foreach ($this->columns as $column => $cdesc) {
            if ($this->is_sortable($column)) {
                if ($sort != $column) {
                    $columnicon = "";
                    $columndir  = "ASC";
                } else {
                    $columndir  = $dir == "ASC" ? "DESC":"ASC";
                    $columnicon = $dir == "ASC" ? "down":"up";
                    $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"\" />";
                }

                // Only include the id parameter if we received one
                $params = array('sort' => $column, 'dir' => $columndir);
                if($id != null) {
                    $params['id'] = $id;
                }

                $this->table->head[]  = '<a href="' . $this->pageurl->out(false, $params) . '">' . $cdesc . '</a>' . $columnicon;
            } else {
                $this->table->head[]  = $cdesc;
            }

            $this->table->align[] = $this->get_column_align($column);
            $this->table->wrap[]  = !$this->is_column_wrapped($column);;
        }
    }

    function print_table() {
        $this->build_table();
        print_table($this->table);
    }

    /**
     * Returns a JSON representation of the table data.
     * @return string
     */
    function get_json() {
        $arr = array();

        foreach($this->table->data as $row) {

            $i = 0;
            $arr_row = array();

            foreach(array_keys($this->columns) as $key) {
                $arr_row[$key] = $row[$i];
                $i += 1;
            }

            $arr[] = $arr_row;
        }

       return json_encode($arr);
    }

    /**
     * Returns the javascript for defining the columns of a YUI DataTable representation of the table data.
     * @return string
     */
    function get_yui_columns() {
        $s = '[';

        foreach($this->columns as $column_id=>$column_label) {
            $s .= "{key:\"$column_id\", sortable:true, label:\"$column_label\", resizeable:true";

            if(!empty($this->yui_formatters[$column_id])) {
                $s .= ', formatter:' . $this->yui_formatters[$column_id];
            }

            $s .= "},\n";
        }

        //  remove the last comma, so that IE doesn't barf on us
        $s = rtrim($s, ",\n");

        $s .= ']';

        return $s;
    }

    /**
     * Returns the columns that should be expected in the JSON data for the table.  Basically exists
     * to support YUI.
     * @return string
     */
    function get_json_schema() {
        $arr = array();

        foreach(array_keys($this->columns) as $key) {
            $field = array('key' => $key);

            if(!empty($this->yui_parsers[$key])) {
                $field['parser'] = $this->yui_parsers[$key];
            }

            if(!empty($this->yui_sorters[$key])) {
                $field['sortOptions'] = array('sortFunction' => $this->yui_sorters[$key]);
            }

            $arr[] = $field;
        }

        return json_encode(array('fields' => $arr));
    }

    /**
     * Prints the code for a YUI DataTable containing this table's data.
     * @return unknown_type
     */
    function print_yui_table($tablename) {
        $this->build_table();
?>

<script type="text/javascript">
YAHOO.util.Event.addListener(window, "load", function() {
    YAHOO.example.Basic = function() {
        var myColumnDefs = <?php echo $this->get_yui_columns(); ?>;

        var myData = <?php echo $this->get_json(); ?>;

        var myDataSource = new YAHOO.util.DataSource(myData);
        myDataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
        myDataSource.responseSchema = <?php echo $this->get_json_schema(); ?>;

        var myDataTable = new YAHOO.widget.DataTable("<?php echo $tablename; ?>",
                myColumnDefs, myDataSource);

        return {
            oDS: myDataSource,
            oDT: myDataTable
        };
    }();
});
</script>

<?php
    }

    private function call_column_function($func, $column) {
        $args = array_slice(func_get_args(),2);
        if (method_exists($this, $func . $column)) {
            return call_user_func_array(array($this, $func . $column), $args);
        } else {
            return call_user_func_array(array($this, $func . 'default'), $args);
        }
    }

    function get_column_align($column) {
        return $this->call_column_function('get_column_align_', $column);
    }

    function get_column_align_default() {
        return 'left';
    }


    function is_column_wrapped($column) {
        return $this->call_column_function('is_column_wrapped_', $column);
    }

    function is_column_wrapped_default() {
        return true;
    }


    function is_sortable($column) {
        return $this->call_column_function('is_sortable_', $column);
    }

    function is_sortable_default() {
        return true;
    }


    function get_item_display($column, $item) {
        $text = $this->call_column_function('get_item_display_', $column, $column, $item);
        if(isset($this->decorators[$column])) {
            $text = $this->decorators[$column]->decorate($text, $column, $item);
        }
        return $text;
    }

    function get_item_display_default($column, $item) {
        return htmlspecialchars($item->$column);
    }

    function get_yesno_item_display($column, $item) {
        if ($item->$column) {
            return get_string('yes');
        } else {
            return get_string('no');
        }
    }

    function get_date_item_display($column, $item) {
        if (empty($item->$column)) {
            return '-';
        } else {
            return cm_timestamp_to_date($item->$column);
        }
    }

    function get_country_item_display($column, $item) {
        static $countries;

        if (!isset($countries)) {
            $countries = cm_get_list_of_countries();
        }

        return isset($countries[$item->country]) ? $countries[$item->country] : '';
    }
}

/**
 * Decorate text in a table cell.
 */
abstract class table_decorator {
    /**
     * @param string $text the (unformatted) text from the table
     * @param string $column the column name
     * @param object $item data object for the row
     */
    abstract function decorate($text, $column, $item);
}

class table_data_iterator implements Iterator {
    public function __construct(display_table $table) {
        $this->table = $table;
    }

    public function current() {
        $row = array();
        $curr = is_object($this->table->items) ? $this->table->items->current() : current($this->table->items);
        foreach (array_keys($this->table->columns) as $column) {
            $row[] = $this->table->get_item_display($column, $curr);
        }
        return $row;
    }

    public function key() {
        return is_object($this->table->items) ? $this->table->items->key() : key($this->table->items);
    }

    public function next() {
        is_object($this->table->items) ? $this->table->items->next() : next($this->table->items);
    }

    public function rewind() {
        reset($this->table->items);
    }

    public function valid() {
        return is_object($this->table->items) ? $this->table->items->valid() : (current($this->table->items) !== false);
    }
}

?>
