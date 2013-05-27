<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

/**
 * A date filter.
 */
class deepsight_filter_date extends deepsight_filter_standard {
    const TYPE = 'date';

    /**
     * Returns options for the javascript object (Currently only language strings).
     *
     * @return array An array of options.
     */
    public function get_js_opts() {
        $opts = parent::get_js_opts();
        $opts['lang_any'] = get_string('any', 'moodle');
        $opts['lang_days'] = array(
            0 => get_string('sun', 'calendar'),
            1 => get_string('mon', 'calendar'),
            2 => get_string('tue', 'calendar'),
            3 => get_string('wed', 'calendar'),
            4 => get_string('thu', 'calendar'),
            5 => get_string('fri', 'calendar'),
            6 => get_string('sat', 'calendar')
        );
        foreach ($opts['lang_days'] as $i => $str) {
            $opts['lang_days'][$i] = $opts['lang_days'][$i]{0};
        }

        $opts['lang_months'] = array(
            0 => get_string('month_jan', 'elis_program'),
            1 => get_string('month_feb', 'elis_program'),
            2 => get_string('month_mar', 'elis_program'),
            3 => get_string('month_apr', 'elis_program'),
            4 => get_string('month_may', 'elis_program'),
            5 => get_string('month_jun', 'elis_program'),
            6 => get_string('month_jul', 'elis_program'),
            7 => get_string('month_aug', 'elis_program'),
            8 => get_string('month_sep', 'elis_program'),
            9 => get_string('month_oct', 'elis_program'),
            10 => get_string('month_nov', 'elis_program'),
            11 => get_string('month_dec', 'elis_program')
        );
        $opts['lang_clear'] = get_string('clear', 'elis_program');
        return $opts;
    }

    /**
     * Get filter SQL based on incoming data.
     *
     * @param mixed $data Incoming data, send from javascript.
     * @return array An array consisting of filter sql as index 0, and an array of parameters as index 1
     */
    public function get_filter_sql($data) {
        if (!empty($data) && isset($data[0])) {
            // Validate inputs.
            $month = (isset($data[0]['month']) && is_numeric($data[0]['month']) && $data[0]['month'] >= 0
                      && $data[0]['month'] <= 11)
                ? (int)$data[0]['month']+1 // Add 1 because javascript starts months at 0.
                : null;
            $date = (isset($data[0]['date']) && is_numeric($data[0]['date']) && $data[0]['date'] >= 1 && $data[0]['date'] <= 31)
                ? (int)$data[0]['date']
                : null;
            $year = (isset($data[0]['year']) && is_numeric($data[0]['year'])
                     && strlen($data[0]['year']) === 4 && $data[0]['year'] >= 1970 && $data[0]['year'] <= (date('Y')+20))
                ? (int)$data[0]['year']
                : null;

            if ($month === null || $date === null || $year === null) {
                return array('', array());
            }

            // Make timestamps.
            $starttimestamp = pm_timestamp(0, 0, 0, $month, $date, $year);
            $endtimestamp = pm_timestamp(23, 59, 59, $month, $date, $year);

            // Assemble sql.
            reset($this->fields);
            $field = key($this->fields);
            $sql = $field.' >= '.$starttimestamp.' AND '.$field.' <= '.$endtimestamp;
            return array($sql, array());
        } else {
            return array('', array());
        }
    }
}