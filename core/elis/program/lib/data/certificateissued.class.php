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
 * @package    elis
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__).'/../../../../config.php');
require_once(elis::lib('data/data_object.class.php'));
require_once(elis::lib('table.class.php'));
define('CERT_ISSUED_TABLE', 'crlm_certificate_issued');

/**
 * Data object for issued certificates.
 */
class certificateissued extends elis_data_object {
    /**
     * The table this data object uses.
     */
    const TABLE = CERT_ISSUED_TABLE;

    /**
     * The language file this class uses.
     */
    const LANG_FILE = 'elis_program';

    /**
     * @var int The database id for this instance.
     */
    protected $_dbfield_id;

    /**
     * @var int The CM user ID the certificate was assigned to.
     */
    protected $_dbfield_cm_userid;

    /**
     * @var int The database settings for this certificate.
     */
    protected $_dbfield_cert_setting_id;

    /**
     * @var string The certificate code.
     */
    protected $_dbfield_cert_code;

    /**
     * @var int The timestamp the certificate was issued.
     */
    protected $_dbfield_timeissued;

    /**
     * @var int The timestamp the certificate was created.
     */
    protected $_dbfield_timecreated;

    /**
     * Perform parent add
     */
    public function save() {
        parent::save();
    }

    /**
     * Perform parent delete
     */
    public function delete() {
        parent::delete();
    }

    /**
     * This function loads a record from an object passed as a parameter.
     * @param object $data Object of properties and values that exists in the crlm_certificate_issued table as columns/values.
     */
    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }
}

// General functions

/**
 * This function retrieves all certificates the user has obtained
 * @param int $cm_userid CM user id
 * @return recordset
 */
function get_user_certificates($cmuserid) {
    global $DB;
    $sql = 'SELECT ci.id, ci.cm_userid, ci.cert_code, ci.timeissued, cs.entity_id, cs.entity_type, cs.id AS csid,
                   cs.cert_border, cs.cert_seal, cs.cert_template
              FROM {crlm_certificate_issued} ci
        INNER JOIN {crlm_certificate_settings} cs ON ci.cert_setting_id = cs.id
             WHERE ci.cm_userid = :cm_userid AND cs.disable = 0
          ORDER BY cs.entity_type ASC, cs.entity_id ASC, ci.timeissued ASC';
    $param  = array('cm_userid' => $cmuserid);
    return $DB->get_recordset_sql($sql, $param);
}

/**
 * Determines whether the certificate code already exists
 *
 * @uses $DB
 * @param string $code The code to look for
 * @return bool True if the code already exists, false otherwise.
 */
function entity_certificate_code_exists($code) {
    global $DB;

    if (empty($code)) {
        return true;
    }

    return $DB->record_exists('crlm_certificate_issued', array('cert_code' => $code));
}