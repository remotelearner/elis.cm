<?php
/**
 * Interface to initalize cmsearch class arrays
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

interface icmsearch {
    /** Return an array whose keys are the table
     *  columns and values are strings to be used
     *  in a query string 
     */
    public function returntablecolumns();
    
    /** Return an array whose keys are the table
     *  columns and values are the display names
     *  of the column
     */
    public function returntablecolumnnames();
}


/**
 * Class to create a block of HTML that contains a combo box of search
 * parameters such as 'contains', 'does not contian', 'is equal ot',
 * 'starts with' and 'ends with' and text fields for user imput.
 * 
 * An SQL where and a query string are some of the items returned from
 * this class, both can be used to either initiate a search or retain values
 * from a previous entry
 * 
 * Data fields from the table column(s) can be hidden by creating custom
 * query string arguments and mapping them to the data fields
 */
class cmsearch {
  
    // map table column names to a display name
    private $columnnamemap = array();
    // Maps column names to query arguments
    private $columndisplaymap = array();
    
    const CMCBTOKEN = '!sub_string';
    
    const CONTAINS = 'consta';
    const DCONTAIN = 'doesn';
    const ISEQUAL  = 'isequ';
    const STARTSWI = 'start';
    const ENDSWITH = 'endsw';
    
    const CMCOMBOBOX = '
        <select name="cmcb!sub_string">
          <option value="consta">contains</option>
          <option value="doesn">does not contain</option>
          <option value="isequ">is equal to</option>
          <option value="start">starts with</option>
          <option value="endsw">ends with</option>
        </select>';

    /**
     * Constructor
     * @param array columntoname Array whose keys are the table
     * columns and values are strings to be used in a query string
     * 
     * @param array columntodisplay Array whose keys arethe table
     * columns and values are the display names of the column
     */
    function cmsearch($columntoname = array(), $columntodisplay = array()) {
        if (is_array($columntoname) and !empty($columntoname)) {
            $this->columnnamemap = $columntoname; 
        }
        
        if (is_array($columntodisplay) and !empty($columntodisplay)) {
            $this->columndisplaymap = $columntodisplay; 
        }
    }

    /**
     * Function to make one of the combo options selected
     * by default
     * 
     * @param string default One of the pre-defined option values
     * 
     * @return string HTML for combo box with a default value
     * selected 
     */
    private function comboboxselected($default = '') {
        $output = '';
        if (!empty($default)) {
            $output = str_replace("$default\"",
                                  "$default\" selected=\"selected\"",
                                  self::CMCOMBOBOX);
        } else {
            $output = self::CMCOMBOBOX;
        }
        
        return $output;
    }

    /**
     * Adds a map between URL query arguments
     * and columns in your table
     * 
     * @param array itemmap An array whose key is
     * the query argument and value is the table
     * column name
     * 
     * @return nothing
     */
    public function additemmap($itemmap = array()) {
        $this->columnnamemap = array_merge(
                                $this->columnnamemap, 
                                $itemmap);
    }
    
    /**
     * Removes a single map
     * 
     * @param string itemkey The key value of the item map
     * @return nothing
     */
    public function removeitemmap($itemkey) {
        if (array_key_exists($itemkey, $this->columnnamemap)) {
            unset($this->columnnamemap[$itemkey]);
        }
    }
    

    /**
     * Returns a where caluse basec upon the values passed as
     * parameters
     * 
     * @param array txtfieldmap Array whose keys are query
     * arguments and values are values are the values the
     * user typed in
     * 
     * @param array combomap Array whose keys are query
     * arguments and values are valuse the user selected
     * 
     * @return string where caluse to be use in an SQL query
     */
    public function getwhereclause($txtfieldmap = array(), $combomap = array()) {
        $output = '';
        $mapkey = '';
        $offset = -4;
        $length = 3;
        $value = '';
        
        if (!empty($txtfieldmap) and !empty($combomap)) {
            foreach($txtfieldmap as $key => $data) {
                $mapkey = array_search($key, $this->columnnamemap);
                if (false !== $mapkey and !empty($data)) {
                    $value = addslashes($data);
                    switch($combomap['cmcb'.$key]){
                        case self::CONTAINS:
                            $output .= " $mapkey LIKE '%$value%' AND ";
                            break;
                        case self::DCONTAIN:
                            $output .= " NOT $mapkey LIKE '%$value%' AND ";
                            break;
                        case self::ISEQUAL:
                            $output .= " $mapkey = '%$value%' AND ";
                            break;
                        case self::STARTSWI:
                            $output .= " $mapkey LIKE '$value%' AND ";
                            break;
                        case self::ENDSWITH:
                            $output .= " $mapkey LIKE '%$value' AND ";
                            break;
                    }
                }
            }
            $output = substr_replace($output, '', $offset, $length);
        }
        
        return $output;
    }
    
    /**
     * Creates html combo boxes and text fields used
     * in the search
     * 
     * @param array mapping Array whose keys are query
     * arguments and values are values are the values the
     * user typed in
     * 
     * @param array combomap Array whose keys are query
     * arguments and values are valuse the user selected
     * 
     * @return string HTML for the combo boxes and text fields
     */
    public function searchform($mapping = array(), $combomap = array()) {
        $output   = '';
        $i        = 1;
        $combobox = '';
        
        if (!empty($this->columnnamemap)) {

            foreach ($this->columnnamemap as $key => $data) {

                    $value = !empty($mapping) ? $mapping[$data] : '';
                    $output .= "<div class=\"cmhtmlblock\">\n";

                    // Print label
                    $output .= "<span class=\"cmlabel\">\n";
                    $output .= $this->columndisplaymap[$key];
                    $output .= "</span>\n";

                    // Print combo box
                    $output .= "<span class=\"cmcmbbox\">\n";
                    if (!empty($combomap)) {
                        $combobox = $this->comboboxselected($combomap['cmcb'.$data]);
                    } else {
                        $combobox = $this->comboboxselected();
                    }
                    $output .= str_replace(self::CMCBTOKEN, $data, $combobox);
                    $output .= "</span>\n";

                    // Print text box
                    $output .= "<span class=\"cminputbox\">\n";
                    $output .= "<input id=\"$i\" type=\"text\" value=\"$value\" size=\"15\" name=\"$data\"/>";
                    $output .= "</span></div><br />\n";

            }
            
            $output .= empty($output) ? $output : "<input id=\"cmsubmit\" type=\"submit\" value=\"" . get_string('filter', 'block_curr_admin'). "\" /><br />\n";
        }

        return $output;
    }
    
    /**
     * Return a query string based on the parameters
     * passed
     * 
     * @param array mapping Array whose keys are query
     * arguments and values are values are the values the
     * user typed in
     * 
     * @param array combomap Array whose keys are query
     * arguments and values are valuse the user selected
     */
    public function getquerystring($mapping, $combomap) {
      $output = '';
      
      foreach($combomap as $key => $data) {
          if (!empty($data)) {
              $output .= "$key=$data&amp;";
          }
      }
      
      foreach($mapping as $key => $data) {
          if (!empty($data)) {
              $output .= "$key=$data&amp;";
          }
      }
      
      $output = rtrim($output,"&amp;");
      
      return $output;
    }
}
?>