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

define ('DATEDELTA_DELIMITER', ',');
define ('DATEDELTA_HOUR' , 0);
define ('DATEDELTA_DAY'  , 1);
define ('DATEDELTA_WEEK' , 2);
define ('DATEDELTA_MONTH', 3);
define ('DATEDELTA_YEAR' , 4);

define ('DATEDELTA_HOUR_STRING' , 'h');
define ('DATEDELTA_DAY_STRING'  , 'd');
define ('DATEDELTA_WEEK_STRING' , 'w');
define ('DATEDELTA_MONTH_STRING', 'm');
define ('DATEDELTA_YEAR_STRING' , 'y');

define ('DATEDELTA_LABEL_HOUR' , 'Hour');
define ('DATEDELTA_LABEL_DAY'  , 'Day');
define ('DATEDELTA_LABEL_WEEK' , 'Week');
define ('DATEDELTA_LABEL_MONTH', 'Month');
define ('DATEDELTA_LABEL_YEAR' , 'Year');

define ('DATEDELTA_DEFAULT_FORMAT', '0h, 0d, 0w, 0m, 0y');

class datedelta {

    var $_hour;
    var $_day;
    var $_week;
    var $_month;
    var $_year;

    function datedelta($date = 0) {
        if (!empty($date)) {
          $this->formatDate($date);
        }
    }

    /**
     * Validate time period has correct format: *h, *d, *w, *m, *y
     * where '*' above is an integer number
     *
     * @param  string $period  Time period to validate
     * @return bool            true if period in proper format, false otherwise.
     */
    public static function validate($period) {
       $legal_chars = array('h', 'd', 'w', 'm', 'y');
       foreach (count_chars($period, 1) as $key => $cnt) {
           $char = chr($key);
           $do_check = ($char != ',' && $char != ' ' && !ctype_digit($char));
           if ($do_check && ($cnt > 1 || !in_array(strtolower($char), $legal_chars))) {
               return false;
           }
       }
       return true;
    }

    /**
     * Check if current time period is zero
     *
     * @return bool    true if current time period is zero, false otherwise.
     */
    public function is_zero() {
        return(!$this->_hour && !$this->_day && !$this->_week && !$this->_month && !$this->_year);
    }

    public function formatDate($date) {
        $parsedDate = $this->parseDate($date);
        $this->getDateParts($parsedDate);
    }

    private function parseDate($date) {
        $parsedDate = explode(DATEDELTA_DELIMITER, $date);
        return $parsedDate;
    }

    private function getDateParts($date) {
        $parts = array();

        foreach($date as $part) {
            switch (strtolower(substr($part, -1, 1))) {
                case DATEDELTA_HOUR_STRING:
                    $this->_hour = intval(trim(substr($part, 0, -1)));
                    break;
                case DATEDELTA_DAY_STRING:
                    $this->_day = intval(trim(substr($part, 0, -1)));
                    break;
                case DATEDELTA_WEEK_STRING:
                    $this->_week = intval(trim(substr($part, 0, -1)));
                    break;
                case DATEDELTA_MONTH_STRING:
                    $this->_month = intval(trim(substr($part, 0, -1)));
                    break;
                case DATEDELTA_YEAR_STRING:
                    $this->_year = intval(trim(substr($part, 0, -1)));
                    break;
            }
        }
    }

    public function getHour() {
        return empty($this->_hour) ? 0 : $this->_hour;
    }

    public function getDay() {
        return empty($this->_day) ? 0 : $this->_day;
    }

    public function getWeek() {
        return empty($this->_week) ? 0 : $this->_week;
    }

    public function getMonth() {
        return empty($this->_month) ? 0 : $this->_month;
    }

    public function getYear() {
        return empty($this->_year) ? 0 : $this->_year;
    }

    public function gettimestamp() {
        $minutes_insecs = 60;
        $hours_insecs   = 60 * $minutes_insecs;
        $days_insecs    = 24 * $hours_insecs;
        $weeks_insecs   = 7 * $days_insecs;
        $months_insecs  = 31 * $days_insecs;
        $years_insecs   = 365 * $days_insecs;

        return ($this->getHour() * $hours_insecs) + ($this->getDay() * $days_insecs) + ($this->getWeek() * $weeks_insecs) +
               ($this->getMonth() * $months_insecs) + ($this->getYear() * $years_insecs);
    }

    public function getDateString($label = false) {
        $dateString = '';

        if (!empty($this->_hour)) {
            if ($label) {
                $dateString .= $this->_hour . DATEDELTA_LABEL_HOUR . '(s) ';
            } else {
                $dateString .= $this->_hour . DATEDELTA_HOUR_STRING . DATEDELTA_DELIMITER;
            }
        }
        if (!empty($this->_day)) {
            if ($label) {
                $dateString .= $this->_day . DATEDELTA_LABEL_DAY . '(s) ';
            } else {
                $dateString .= $this->_day . DATEDELTA_DAY_STRING . DATEDELTA_DELIMITER;
            }
        }
        if (!empty($this->_week)) {
            if ($label) {
                $dateString .= $this->_week . DATEDELTA_LABEL_WEEK . '(s) ';
            } else {
                $dateString .= $this->_week . DATEDELTA_WEEK_STRING . DATEDELTA_DELIMITER;
            }
        }
        if (!empty($this->_month)) {
            if ($label) {
                $dateString .= $this->_month . DATEDELTA_LABEL_MONTH . '(s) ';
            } else {
                $dateString .= $this->_month . DATEDELTA_MONTH_STRING . DATEDELTA_DELIMITER;
            }
        }
        if (!empty($this->_year)) {
            if ($label) {
                $dateString .= $this->_year . DATEDELTA_LABEL_YEAR . '(s) ';
            } else {
                $dateString .= $this->_year . DATEDELTA_YEAR_STRING . DATEDELTA_DELIMITER;
            }
        }

        return rtrim($dateString, DATEDELTA_DELIMITER);
    }

}

/**
  $date = new datedelta('55h,55y,55d');
  echo var_dump(datedelta::validate('55h,55y,55d'));
  echo $date->getDateString(false);
*/

