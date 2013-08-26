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

require_once(elispm::lib('data/userset.class.php'));

/**
 * A datatable implementation for lists of programs.
 */
class deepsight_datatable_usersetsubuserset_base extends deepsight_datatable_userset {

    /**
     * @var int The ID of the userset we're managing.
     */
    protected $usersetid;

    /**
     * Sets the current userset ID
     * @param int $usersetid The ID of the userset we're managing.
     */
    public function set_usersetid($usersetid) {
        $this->usersetid = (int)$usersetid;
    }

    /**
     * Gets an array of javascript files needed for operation.
     * @return array An array of required javascript files.
     */
    public function get_js_dependencies() {
        $deps = parent::get_js_dependencies();
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_confirm.js';
        $deps[] = '/elis/program/lib/deepsight/js/actions/deepsight_action_link.js';
        return $deps;
    }

    /**
     * Get an array of options to pass to the deepsight_datatable javascript object. Enables drag and drop, and multiselect.
     * @return array An array of options, ready to be passed to $this->get_init_js()
     */
    public function get_table_js_opts() {
        $opts = parent::get_table_js_opts();
        $opts['dragdrop'] = true;
        $opts['multiselect'] = true;
        return $opts;
    }
}

/**
 * A datatable for listing subsets.
 */
class deepsight_datatable_usersetsubuserset_assigned extends deepsight_datatable_usersetsubuserset_base {

    /**
     * Gets the edit and unassignment actions.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();

        $langedit = get_string('edit', 'elis_program');
        $actions[] = new deepsight_action_usersetsubuserset_editlink($this->DB, 'usersetsubuserset_editlink', $langedit);

        $langtracks = get_string('tracks', 'elis_program');
        $actions[] = new deepsight_action_usersetsubuserset_trackslink($this->DB, 'usersetsubuserset_trackslink', $langtracks);

        $langusers = get_string('users', 'elis_program');
        $actions[] = new deepsight_action_usersetsubuserset_userslink($this->DB, 'usersetsubuserset_userslink', $langusers);

        $langpgms = get_string('curricula', 'elis_program');
        $actions[] = new deepsight_action_usersetsubuserset_programslink($this->DB, 'usersetsubuserset_programslink', $langpgms);

        $langdelete = get_string('delete', 'elis_program');
        $actions[] = new deepsight_action_usersetsubuserset_deletelink($this->DB, 'usersetsubuserset_deletelink', $langdelete);

        return $actions;
    }

    /**
     * Restrict assigned list to usersets the user has elis/program:userset_view permissions on.
     * @return array An array consisting of additional WHERE conditions, and parameters.
     */
    protected function get_filter_sql_permissions() {
        global $USER;
        $ctxlevel = 'cluster';
        $perm = 'elis/program:userset_view';
        $additionalfilters = array();
        $additionalparams = array();
        $associatectxs = pm_context_set::for_user_with_capability($ctxlevel, $perm, $USER->id);
        $associatectxsfilerobject = $associatectxs->get_filter('id', $ctxlevel);
        $associatefilter = $associatectxsfilerobject->get_sql(false, 'element', SQL_PARAMS_QM);
        if (isset($associatefilter['where'])) {
            $additionalfilters[] = $associatefilter['where'];
            $additionalparams = array_merge($additionalparams, $associatefilter['where_parameters']);
        }
        return array($additionalfilters, $additionalparams);
    }

    /**
     * Adds filters to ensure assigned list shows correct subsets.
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        global $USER;

        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        $additionalfilters = array();

        // Remove assigned users.
        $additionalfilters[] = 'element.parent = ?';
        $filterparams[] = $this->usersetid;

        // Permissions.
        list($permadditionalfilters, $permadditionalparams) = $this->get_filter_sql_permissions();
        $additionalfilters = array_merge($additionalfilters, $permadditionalfilters);
        $filterparams = array_merge($filterparams, $permadditionalparams);

        // Add our additional filters.
        if (!empty($additionalfilters)) {
            $filtersql = (!empty($filtersql))
                    ? $filtersql.' AND '.implode(' AND ', $additionalfilters)
                    : 'WHERE '.implode(' AND ', $additionalfilters);
        }

        return array($filtersql, $filterparams);
    }
}

/**
 * A datatable for listing usersets that could be assigned as subsets.
 */
class deepsight_datatable_usersetsubuserset_available extends deepsight_datatable_usersetsubuserset_base {

    /**
     * Gets action to move a userset to subset of current userset.
     * @return array An array of deepsight_action objects that will be available for each element.
     */
    public function get_actions() {
        $actions = parent::get_actions();

        // Make subset action.
        $makesubset = new deepsight_action_usersetsubuserset_makesubset($this->DB, 'usersetsubuserset_makesubset');
        $makesubset->endpoint = (strpos($this->endpoint, '?') !== false)
                ? $this->endpoint.'&m=action' : $this->endpoint.'?m=action';
        $actions[] = $makesubset;
        return $actions;
    }

    /**
     * Adds JOINs to the main query.
     * @param array $filters An array of active filters to use to determne join sql.
     * @return array An array of JOIN sql fragments.
     */
    protected function get_join_sql(array $filters=array()) {
        $joinsql = parent::get_join_sql($filters);

        // Check if we've already joined in the context table for the current element (from custom fields). If not, add it.
        $ctxjoin = 'JOIN {context} ctx ON ctx.instanceid = element.id AND ctx.contextlevel='.CONTEXT_ELIS_USERSET;
        if (!in_array($ctxjoin, $joinsql)) {
            $joinsql[] = $ctxjoin;
        }
        return $joinsql;
    }

    /**
     * Restrict assigned list to usersets the user has elis/program:userset_view permissions on.
     * @return array An array consisting of additional WHERE conditions, and parameters.
     */
    protected function get_filter_sql_permissions() {
        global $USER;
        $ctxlevel = 'cluster';
        $perm = 'elis/program:userset_edit';
        $additionalfilters = array();
        $additionalparams = array();
        $associatectxs = pm_context_set::for_user_with_capability($ctxlevel, $perm, $USER->id);
        $associatectxsfilerobject = $associatectxs->get_filter('id', $ctxlevel);
        $associatefilter = $associatectxsfilerobject->get_sql(false, 'element', SQL_PARAMS_QM);
        if (isset($associatefilter['where'])) {
            $additionalfilters[] = $associatefilter['where'];
            $additionalparams = array_merge($additionalparams, $associatefilter['where_parameters']);
        }
        return array($additionalfilters, $additionalparams);
    }

    /**
     * Adds filters to ensure assigned list shows correct subsets.
     * @param array $filters An array of requested filter data. Formatted like [filtername]=>[data].
     * @return array An array consisting of the SQL WHERE clause, and the parameters for the SQL.
     */
    protected function get_filter_sql(array $filters) {
        global $USER, $DB;

        list($filtersql, $filterparams) = parent::get_filter_sql($filters);

        $additionalfilters = array();

        // Prevent the current userset from appearing.
        $additionalfilters[] = 'element.id != ?';
        $filterparams[] = $this->usersetid;

        // Prevent all direct children from appearing.
        $additionalfilters[] = 'element.parent != ?';
        $filterparams[] = $this->usersetid;

        // Prevent all ancestor usersets from appearing.
        $usersetctx = context_elis_userset::instance($this->usersetid);
        $parentctxs = explode('/', substr($usersetctx->path, 1));
        list($parentctxsfilter, $parentctxfilterparams) = $DB->get_in_or_equal($parentctxs, SQL_PARAMS_QM, '', false);
        if (!empty($parentctxsfilter)) {
            $additionalfilters[] = 'ctx.id '.$parentctxsfilter;
            $filterparams = array_merge($filterparams, $parentctxfilterparams);
        }

        // Permissions.
        list($permadditionalfilters, $permadditionalparams) = $this->get_filter_sql_permissions();
        $additionalfilters = array_merge($additionalfilters, $permadditionalfilters);
        $filterparams = array_merge($filterparams, $permadditionalparams);

        // Add our additional filters.
        if (!empty($additionalfilters)) {
            $filtersql = (!empty($filtersql))
                    ? $filtersql.' AND '.implode(' AND ', $additionalfilters)
                    : 'WHERE '.implode(' AND ', $additionalfilters);
        }

        return array($filtersql, $filterparams);
    }
}