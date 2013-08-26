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
define('CERT_SETTINGS_TABLE', 'crlm_certificate_settings');

/**
 * Data object for certificate settings.
 */
class certificatesettings extends elis_data_object {

    /**
     * The database table used for this data object.
     */
    const TABLE = CERT_SETTINGS_TABLE;

    /**
     * The language file used within this class.
     */
    const LANG_FILE = 'elis_program';

    /**
     * @var int The database id for this instance.
     */
    protected $_dbfield_id;

    /**
     * @var int The entity this instance belongs to.
     */
    protected $_dbfield_entity_id;

    /**
     * @var string The type of entity this instance belongs to.
     */
    protected $_dbfield_entity_type;

    /**
     * @var string The border to use.
     */
    protected $_dbfield_cert_border;

    /**
     * @var string The seal to use.
     */
    protected $_dbfield_cert_seal;

    /**
     * @var string The template to use.
     */
    protected $_dbfield_cert_template;

    /**
     * @var bool Whether this is disabled or not.
     */
    protected $_dbfield_disable;

    /**
     * @var int The timestamp this certificate setting was created.
     */
    protected $_dbfield_timecreated;

    /**
     * @var int The timestamp this certificate setting was modified.
     */
    protected $_dbfield_timemodified;

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
     * @param object $data Object of properties and values that exists in the crlm_certificate_settings table as columns/values.
     */
    public function set_from_data($data) {
        $this->_load_data_from_record($data, true);
    }

    /**
     * This function loads a record from the entity information passed as parameters. and loads the record into the instance data.
     * @param int $entityid ID of the entity
     * @param string $entitytype @see certificatepage.class.php for a list of valid entity types
     */
    public function get_data_by_entity($entityid, $entitytype) {
        $conditions = array('entity_id' => $entityid, 'entity_type' => $entitytype);
        $record = $this->_db->get_record(static::TABLE, $conditions, '*', IGNORE_MISSING);
        $this->_load_data_from_record($record, true);
    }
}
