<?php //$Id$

require_once($CFG->dirroot.'/curriculum/lib/filtering/text.php');

/**
 * Generic filter for text fields.
 */
class generalized_filter_clustertext extends generalized_filter_text {

    static $OPERATOR_CONTAINS = 0;
    static $OPERATOR_DOES_NOT_CONTAIN = 1;
    static $OPERATOR_IS_EQUAL_TO = 2;
    static $OPERATOR_STARTS_WITH = 3;
    static $OPERATOR_ENDS_WIDTH = 4;
    static $OPERATOR_IS_EMPTY = 5;

    /**
     * Constructor
     * @param string $alias aliacs for the table being filtered on
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function generalized_filter_clustertext($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_text($uniqueid, $alias, $name, $label, $advanced, $field, $options);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $CFG;

        $full_fieldname = $this->get_full_fieldname();

        $value_expression = '';

        $value = addslashes($data['value']);

        switch($data['operator']) {
            case generalized_filter_clustertext::$OPERATOR_CONTAINS:
                //contains
                $value_expression = " LIKE '%{$value}%'";
                break;
            case generalized_filter_clustertext::$OPERATOR_DOES_NOT_CONTAIN:
                //does not contain
                $value_expression = " NOT LIKE '%{$value}%'";
                break;
            case generalized_filter_clustertext::$OPERATOR_IS_EQUAL_TO:
                //equals
                $value_expression = " = '{$value}'";
                break;
            case generalized_filter_clustertext::$OPERATOR_STARTS_WITH:
                //starts with
                $value_expression = " LIKE '{$value}%'";
                break;
            case generalized_filter_clustertext::$OPERATOR_ENDS_WITH:
                //ends with
                $value_expression = " LIKE '%{$value}'";
                break;
            case generalized_filter_clustertext::$OPERATOR_IS_EMPTY:
                $value_expression = " = ''";
                break;
            default:
                //error call
                print_error('invalidoperator', 'block_php_report');
        }

        $sql = "{$full_fieldname} IN
                (SELECT inner_usercluster.userid
                 FROM {$CFG->prefix}crlm_usercluster inner_usercluster
                 JOIN {$CFG->prefix}crlm_cluster inner_cluster
                 ON inner_usercluster.clusterid = inner_cluster.id
                 AND inner_cluster.name {$value_expression}
                )";

        return $sql;

    }

}
