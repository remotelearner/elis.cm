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

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('data/data_object_with_custom_fields.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/user.class.php'));

class clustercurriculum extends elis_data_object {

    const TABLE = 'crlm_cluster_curriculum';

    /**
     * User ID-number
     * @var    char
     * @length 255
     */
    protected $_dbfield_id;
    protected $_dbfield_clusterid;
    protected $_dbfield_curriculumid;
    protected $_dbfield_autoenrol;

    private $location;
    private $templateclass;

    static $associations = array(
        'userset' => array(
            'class' => 'userset',
            'idfield' => 'clusterid'
        ),
        'curriculum' => array(
            'class' => 'curriculum',
            'idfield' => 'curriculumid'
        )
    );

    public static function delete_for_curriculum($id) {
        global $DB;

        return $DB->delete_records(self::TABLE, array('curriculumid'=> $id));
    }

    static $validation_rules = array(
        'validate_clusterid_not_empty',
        'validate_curriculumid_not_empty',
        array('validation_helper', 'is_unique_clusterid_curriculumid')
    );

    function validate_clusterid_not_empty() {
        return validate_not_empty($this, 'clusterid');
    }

    function validate_curriculumid_not_empty() {
        return validate_not_empty($this, 'curriculumid');
    }

    /**
     * Associates a cluster with a curriculum.
     */
    public static function associate($cluster, $curriculum, $autoenrol=true) {
        global $DB;

        // make sure we don't double-associate
        if ($DB->record_exists(self::TABLE, array('clusterid'    => $cluster,
                                                   'curriculumid' => $curriculum)))
        {
            return;
        }

        // ELIS-7582
        @set_time_limit(0);

        $record = new clustercurriculum();
        $record->clusterid = $cluster;
        $record->autoenrol = !empty($autoenrol) ? 1 : 0;
        $record->curriculumid = $curriculum;
        $record->save();

        /**
         * Assign all users in the cluster with curriculum.  Don't assign users
         * if already assigned
         */
        /**
         * @todo we may need to change this if associating a user with a
         * curriculum does anything more complicated
         */

        // assign cluster members as students in the newly-associated curriculum if the
        // association has autoenrol set and the users are not already in the curriculum
        if (!empty($autoenrol)) {
            $timenow = time();
            $sql = 'INSERT INTO {' . curriculumstudent::TABLE . '} '
                . '(userid, curriculumid, timecreated, timemodified) '
                . 'SELECT DISTINCT u.id, ' . $curriculum . ', ' . $timenow . ', ' . $timenow. ' '
                . 'FROM {' . clusterassignment::TABLE . '} clu '
                . 'INNER JOIN {' . user::TABLE . '} u ON u.id = clu.userid '
                . 'LEFT OUTER JOIN {' . curriculumstudent::TABLE . '} ca ON ca.userid = u.id AND ca.curriculumid = \'' . $curriculum . '\' '
                . 'WHERE clu.clusterid = ? AND ca.curriculumid IS NULL';
            $params = array($cluster);
            $DB->execute($sql,$params);
        }

//        events_trigger('pm_userset_program_associated', $record);
    }

    /// collection fetching functions. (These may be able to replaced by a generic container/listing class)

    /**
     * Get a list of the clusters assigned to this curriculum.
     *
     * @uses           $CURMAN
     * @param   int    $curriculumid     The cluster id
     * @param   int    $parentclusterid  If non-zero, a required direct-parent cluster
     * @param   int    $startrecord      The index of the record to start with
     * @param   int    $perpage          The number of records to include
     * @param   array  $extrafilters     Additional filters to apply to the listing
     * @return  array                    The appropriate cluster records
     */
    public static function get_clusters($curriculumid = 0, $parentclusterid = 0, $sort = 'name', $dir = 'ASC', $startrec = 0,
                                        $perpage = 0, $extrafilters = array()) {
        global $DB;

        if (empty($DB)) {
            return NULL;
        }

        //require plugin code if enabled
        $plugins = get_plugin_list('pmplugins');
        $display_priority_enabled = isset($plugins['userset_display_priority']);
        if($display_priority_enabled) {
            require_once(elis::plugin_file('pmplugins_userset_display_priority', 'lib.php'));
        }

        $select  = 'SELECT clstcur.id, clstcur.clusterid, clst.name, clst.display, clstcur.autoenrol ';
        $tables  = 'FROM {' . self::TABLE . '} clstcur ';
        $join    = 'LEFT JOIN {' . userset::TABLE . '} clst '.
                   'ON clst.id = clstcur.clusterid ';

        //handle empty sort case
        if(empty($sort)) {
            $sort = 'name';
            $dir = 'ASC';
        }

        //get the fields we are sorting
        $sort_fields = explode(',', $sort);

        //convert the fields into clauses
        $sort_clauses = array();
        foreach($sort_fields as $key => $value) {
            $new_value = trim($value);
            if($display_priority_enabled && $new_value == 'priority') {
                $sort_clauses[$key] = $new_value . ' DESC';
            } else {
                $sort_clauses[$key] = $new_value . ' ' . $dir;
            }
        }

        //determine if we are handling the priority field for ordering
        if($display_priority_enabled && in_array('priority', $sort_fields)) {
            userset_display_priority_append_sort_data('clst.id', $select, $join);
        }

        $where   = 'WHERE clstcur.curriculumid = :curriculumid ';
        $params = array('curriculumid' => $curriculumid);

        //apply the parent-cluster condition if applicable
        if(!empty($parentclusterid)) {
            $where .= " AND clst.parent = :parentclusterid ";
            $params['parentclusterid'] = $parentclusterid;
        }

        if (!empty($extrafilters['contexts'])) {
            //apply a filter related to filtering on particular PM cluster contexts
     	    $filter_object = $extrafilters['contexts']->get_filter('id', 'cluster');
	        $filter_sql = $filter_object->get_sql(false, 'clst', SQL_PARAMS_NAMED);

    	    if (!empty($filter_sql)) {
                //user does not have access at the system context
        	    $where .= 'AND ('.$filter_sql['where'] . ") ";
            	$params = array_merge($params, $filter_sql['where_parameters']);
	        }
    	}

        $group   = 'GROUP BY clstcur.id ';

        $sort_clause = 'ORDER BY ' . implode($sort_clauses, ', ') . ' ';

        $sql = $select.$tables.$join.$where.$group.$sort_clause;

        return $DB->get_records_sql($sql, $params, $startrec, $perpage);
    }

    /**
     * Determine the number of clusters assigned to this curriculum
     *
     * @uses           $CURMAN
     * @param   int    $curriculumid     The cluster id
     * @param   int    $parentclusterid  If non-zero, a required direct-parent cluster
     * @param   array  $extrafilters     Additional filters to apply to the count
     * @return  int                      The number of appropriate records
     */
    static function count_clusters($curriculumid = 0, $parentclusterid = 0, $extrafilters = array()) {
        global $DB;

        $select  = 'SELECT COUNT(*) ';
        $tables  = 'FROM {' . self::TABLE . '} clstcur ';
        $join    = 'LEFT JOIN {' . userset::TABLE . '} clst '.
                   'ON clst.id = clstcur.clusterid ';
        $where   = 'WHERE clstcur.curriculumid = :curriculumid ';
        $params = array('curriculumid'=> $curriculumid);

        if(!empty($parentclusterid)) {
            $where .= " AND clst.parent = :parentclusterid ";
            $params['parentclusterid'] = $parentclusterid;
        }

        if (!empty($extrafilters['contexts'])) {
            //apply a filter related to filtering on particular PM cluster contexts
     	    $filter_object = $extrafilters['contexts']->get_filter('id', 'cluster');
	        $filter_sql = $filter_object->get_sql(false, 'clst', SQL_PARAMS_NAMED);

    	    if (!empty($filter_sql)) {
                //user does not have access at the system context
        	    $where .= 'AND ('.$filter_sql['where'] . ") ";
            	$params = array_merge($params, $filter_sql['where_parameters']);
	        }
    	}

        $sort    = 'ORDER BY clst.name ASC ';

        $sql = $select.$tables.$join.$where.$sort;

        return $DB->count_records_sql($sql, $params);

    }


    /**
     * Get a list of the curricula assigned to this cluster.
     *
     * @uses             $CURMAN
     * @param   int      $clusterid     The cluster id.
     * @param   array    $extrafilters  Additional filters to apply to the listing
     * @return  array                   The associated curriculum records
     */
    public static function get_curricula($clusterid = 0, $startrec = 0, $perpage = 0, $sort = 'cur.name ASC', $extrafilters = array()) {
        global $DB;

        if (empty($DB)) {
            return NULL;
        }

        $select  = 'SELECT clstcur.id, clstcur.curriculumid, cur.idnumber, cur.name, cur.description, cur.reqcredits, COUNT(curcrs.id) as numcourses, clstcur.autoenrol ';
        $tables  = 'FROM {' . clustercurriculum::TABLE . '} clstcur ';
        $join    = 'LEFT JOIN {' . curriculum::TABLE . '} cur '.
                   'ON cur.id = clstcur.curriculumid ';
        $join   .= 'LEFT JOIN {' . curriculumcourse::TABLE . '} curcrs '.
                   'ON curcrs.curriculumid = cur.id ';
        $where   = 'WHERE clstcur.clusterid = ? ';
        $params = array($clusterid);
        $group   = 'GROUP BY clstcur.id ';
        $sort    = "ORDER BY $sort ";

        if (!empty($extrafilters['contexts'])) {
            //apply a filter related to filtering on particular PM curriculum contexts
        	$filter_object = $extrafilters['contexts']->get_filter('id', 'curriculum');
            $filter_sql = $filter_object->get_sql(false, 'cur');

            if (!empty($filter_sql)) {
                //user does not have access at the system context
                $where .= 'AND '.$filter_sql['where'].' ';
                $params = array_merge($params, $filter_sql['where_parameters']);
            }
        }

        $sql = $select.$tables.$join.$where.$group.$sort;

        return $DB->get_records_sql($sql, $params, $startrec, $perpage);
    }

    /**
     * Determines the number of curricula assigned to the provided cluster
     *
     * @uses           $CURMAN
     * @param   int    $clusterid     The id of the cluster to check associations from
     * @param   array  $extrafilters  Additional filters to apply to the count
     * @return  int                   The number of associated curricula
     */
    public static function count_curricula($clusterid = 0, $extrafilters = array()) {
        global $DB;

        if (empty($DB)) {
            return 0;
        }

        $select  = 'SELECT COUNT(cur.id) ';
        $tables  = 'FROM {' . curriculum::TABLE . '} cur ';
        $join    = '';
        $where   = '';
        $params  = array();

        if ($clusterid != 0) {
            //looking by cluster, so join the association table and filter
            $join     = 'JOIN {' . clustercurriculum::TABLE . '} clstcur '.
                        'ON cur.id = clstcur.curriculumid ';
            $where    = 'WHERE clstcur.clusterid = ? ';
            $params[] = $clusterid;
        }

        if (!empty($extrafilters['contexts'])) {
            //apply a filter related to filtering on particular PM curriculum contexts
        	$filter_object = $extrafilters['contexts']->get_filter('id', 'curriculum');
            $filter_sql = $filter_object->get_sql(false, 'cur');

            if (!empty($filter_sql)) {
                //user does not have access at the system context
                $where .= 'AND '.$filter_sql['where'];
                $params = array_merge($params, $filter_sql['where_parameters']);
            }
        }

        $groupby = '';

        $sql = $select . $tables . $join . $where . $groupby;

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Updates the autoenrol flag for a particular cluster-curriculum association
     *
     * @param   int     $association_id  The id of the appropriate association record
     * @param   int     $autoenrol       The new autoenrol value
     *
     * @return  object                   The updated record
     */
    public static function update_autoenrol($association_id, $autoenrol) {
        global $DB;

        // ELIS-7582
        @set_time_limit(0);

        $old_autoenrol = $DB->get_field(self::TABLE, 'autoenrol', array('id'=> $association_id));

        // update the flag on the association record
        $update_record = new stdClass;
        $update_record->id = $association_id;
        $update_record->autoenrol = $autoenrol;
        $result = $DB->update_record(self::TABLE, $update_record);

        // assign cluster members as students in the relevant curriculum if the
        // association has autoenrol set and the users are not already in the curriculum
        if (!empty($autoenrol) && empty($old_autoenrol) && ($curriculum = $DB->get_field(self::TABLE, 'curriculumid',
            array('id' => $association_id))) && ($cluster = $DB->get_field(self::TABLE, 'clusterid', array('id' => $association_id)))) {
            $timenow = time();
            $sql = 'INSERT INTO {'.curriculumstudent::TABLE.'} (userid, curriculumid, timecreated, timemodified) SELECT DISTINCT u.id, '
                .$curriculum.', '.$timenow.', '.$timenow.' FROM {'.clusterassignment::TABLE.'} clu INNER JOIN {'.user::TABLE
                .'} u ON u.id = clu.userid LEFT OUTER JOIN {'.curriculumstudent::TABLE.'} ca ON ca.userid = u.id AND ca.curriculumid = \''
                .$curriculum.'\' WHERE clu.clusterid = ? AND ca.curriculumid IS NULL';
            $params = array($cluster);
            $DB->execute($sql,$params);
        }

        return $result;
    }
}
