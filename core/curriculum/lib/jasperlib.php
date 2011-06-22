<?php
/**
 * Routines to interact with JasperServer.
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

class _JasperCache {
    static $peer = Null;
    static $auth = Null;

    public static function mnet_peer() {
        global $CFG;
        if (self::$peer == Null) {
            require_once $CFG->dirroot . '/mnet/peer.php';
            self::$peer = new mnet_peer();
            $elisauthconfig = get_config('auth/elis');
            // figure out the wwwroot for the Jasper Server
            // default to the Remote Learner server if not set
            $wwwroot = empty($elisauthconfig->jasper_wwwroot) ? 'http://reporting.remote-learner.net:8085/jasperserver-pro' : $elisauthconfig->jasper_wwwroot;
            self::$peer->set_wwwroot($wwwroot);
        }
        return self::$peer;
    }

    public static function auth_elis() {
        global $CFG;
        if (self::$auth == Null) {
            require_once $CFG->dirroot . '/auth/elis/auth.php';
            self::$auth = new auth_plugin_elis;
        }
        return self::$auth;
    }
}

// = Convenience functions =
/**
 * Retrieve the MNet host ID for JasperServer.  The function is hard-coded to
 * retrieve the ID for the Remote-Learner reporting server.
 *
 * @return int MNet host ID
 */
function jasper_mnet_hostid() {
    return _JasperCache::mnet_peer()->id;
}

/**
 * Return a MNet SSO link that will bring the user to the specified
 * JasperServer page.
 */
function jasper_mnet_link($link) {
    global $CFG;
    return $CFG->wwwroot.'/auth/mnet/jump.php?hostid='.jasper_mnet_hostid().
        '&wantsurl=' . rawurlencode($link);
}

function jasper_shortname() {
    return _JasperCache::auth_elis()->config->jasper_shortname;
}

function jasper_common_reports_folder() {
    return "/public/elis/reports";
}

function jasper_custom_reports_folder() {
    return "/elis/client/" . jasper_shortname() . "/reports";
}

// = HTTP API =
// see Section 8.2ff in JasperServer Ultimate Guide
/**
 * Generates a direct link to a report on a JasperServer.  The link generated
 * will be relative to the JasperServer base URL, and includes an initial '/'.
 *
 * @param string $uri the URI of the report within the JasperServer repository
 * @param array $parameters report parameters.
 * <br>Special parameters:
 * - output: the output format (pdf, xls, rtf, csv, swf, html)
 * - reportLocale: the locale to use for the report
 * - j_username: username to use to authenticate in JasperServer
 * - j_password: password to use to authenticate in JasperServer
 * The parameters can be strings (or something that can be turned into a
 * string) or an array.  If it is an array, it will be treated as a parameter
 * for a multi-value input control.
 * @return string a relative link to the report
 */
function jasper_report_link($uri, $parameters=array()) {
    $rv = '/mnet/report.jsp?reportUnit=' . rawurlencode($uri);
    foreach ($parameters as $key => $parameter) {
        // special handling for multi-value controls
        if (is_array($parameter)) {
            if (empty($parameter)) {
                // special marker parameter to indicate an empty parameter
                $rv .= '&_' . rawurlencode($key) . '=';
            } else {
                foreach ($parameter as $value) {
                    $rv .= '&' . rawurlencode($key) . '=' . rawurlencode($value);
                }
            }
        } else {
            $rv .= '&' . rawurlencode($key) . '=' . rawurlencode($parameter);
        }
    }
    return $rv;
}

/**
 * Generates a direct link to generated content on JasperServer (PDF, HTML,
 * Excel, or RTF).  The link generated will be relative to the JasperServer
 * base URL, and includes an initial '/'.
 *
 * @param string $uri the URI of the content within the JasperServer repository
 * @return string a relative link to the content
 */
function jasper_content_link($uri) {
    return '/fileview/fileview' . $uri;
}

/**
 * Generates a direct link to a folder listing on JasperServer.  The link
 * generated will be relative to the JasperServer base URL, and includes an
 * initial '/'.
 *
 * @param string $uri the URI of the folder within the JasperServer repository
 * @return string a relative link to the folder
 */
function jasper_folder_resource_link($uri) {
    return '/flow.html?_flowId=repositoryFlow&folder=' . rawurlencode($uri);
}

/**
 * Generates a direct link to a listing of the reports in JasperServer.  The
 * link generated will be relative to the JasperServer base URL, and includes
 * an initial '/'.
 *
 * @return string a relative link to the reports list
 */
function jasper_list_reports_link() {
    return '/flow.html?_flowId=listReportsFlow';
}

// = SOAP API =
// see JasperServer Web Services Guide
/**
 * Class to access a JasperServer repository.  Currently only supports listing
 * and running a report.
 */
class JasperClient extends SoapClient {
    private $attachments = array();

    function __construct($host, $user, $pass) {
        $options = array();
        $options['login'] = $user;
        $options['password'] = $pass;
        $options['exceptions'] = false;
        $options['trace'] = true;
        parent::__construct($host . '/services/repository?wsdl', $options);
    }

    function __doRequest($request, $location, $action, $version, $one_way) {
        // FIXME: allow sending attachments
        $this->attachments = array();
        $response = parent::__doRequest($request, $location, $action, $version, $one_way);
        $headers = parent::__getLastResponseHeaders();
        // if it is a multipart response, split the parts, return the main xml
        // part, and add the attachments
        if (strpos($headers, 'Content-Type: multipart/related')) {
            preg_match('/start="([^"]*)";.*boundary="([^"]*)"/', $headers, $matches);
            $start = $matches[1];
            $boundary = $matches[2];
            $parts = explode('--' . $boundary, $response);
            foreach ($parts as $part) {
                $pieces = explode("\r\n\r\n", $part, 2);
                if (count($pieces) != 2) {
                    // skip "empty" parts
                    continue;
                }
                $header = $pieces[0]; $body = $pieces[1];
                preg_match('/Content-Id: (.*)$/m', $header, $matches);
                if ($matches[1] == $start) {
                    $response = $body;
                } else {
                    $this->attachments[] = $pieces;
                }
            }
        }
        return $response;
    }

    function listFolder($uri, $wsType=JasperResourceDescriptor::TYPE_FOLDER) {
        $descr = new JasperResourceDescriptor('', $wsType, $uri);
        $req = new JasperRequest('list');
        $req->descriptor = $descr;
        $response = new JasperResponse($this->__soapCall('list', array($req)));
        return $response;
    }

    function runReport($uri, $parameters=array(), $arguments=array()) {
        $descr = new JasperResourceDescriptor('', '', $uri);
        $descr->parameters = $parameters;
        $req = new JasperRequest('runReport');
        $req->descriptor = $descr;
        $response = new JasperResponse(parent::runReport($req));
        $response->attachments = $this->attachments;
        return $response;
    }
}

class JasperRequest {
    public $operation;
    public $locale;
    public $arguments = array();
    public $descriptor = null;

    function __construct($operation, $locale=null) {
        $this->operation = $operation;
        $this->locale = $locale;
    }

    function __toString() {
        $rv = '<request operationName="' . htmlspecialchars($this->operation) . '"' . (empty($this->locale) ? '' : (' locale="' . htmlspecialchars($this->locale) . '"')) . '>';
        foreach ($this->arguments as $name => $argument) {
            if ($argument instanceof JasperArgument) {
                $rv .= $argument;
            } else {
                $rv .= '<argument name="' . htmlspecialchars($name) . '">' . htmlspecialchars($argument) . '</argument>';
            }
        }
        if (!empty($this->descriptor)) {
            $rv .= $this->descriptor;
        }
        $rv .= '</request>';
        return $rv;
    }
}

class JasperArgument {
    public $name;
    public $value;

    function __construct($name, $value) {
        $this->name = $name;
        $this->value = $value;
    }

    function __toString() {
        return '<argument name="' . htmlspecialchars($this->name) . '">' . htmlspecialchars($this->value) . '</argument>';
    }
}

class JasperResourceDescriptor {
    public $name = '';
    public $wsType = '';
    public $uri = '';
    public $isNew = false;
    public $label = '';
    public $description = '';
    public $properties = array();
    public $children = array();
    public $parameters = array();

    // Resource wsTypes
    const TYPE_FOLDER = 'folder';
    const TYPE_REPORTUNIT = 'reportUnit';
    const TYPE_DATASOURCE = 'datasource';
    const TYPE_DATASOURCE_JDBC = 'jdbc';
    const TYPE_DATASOURCE_JNDI = 'jndi';
    const TYPE_DATASOURCE_BEAN = 'bean';
    const TYPE_DATASOURCE_CUSTOM = 'custom';
    const TYPE_IMAGE = 'img';
    const TYPE_FONT = 'font';
    const TYPE_JRXML = 'jrxml';
    const TYPE_CLASS_JAR = 'jar';
    const TYPE_RESOURCE_BUNDLE = 'prop';
    const TYPE_REFERENCE = 'reference';
    const TYPE_INPUT_CONTROL = 'inputControl';
    const TYPE_DATA_TYPE = 'dataType';
    const TYPE_OLAP_MONDRIAN_CONNECTION = 'olapMondrianCon';
    const TYPE_OLAP_XMLA_CONNECTION = 'olapXmlaCon';
    const TYPE_MONDRIAN_SCHEMA = 'olapMondrianSchema';
    const TYPE_ACCESS_GRANT_SCHEMA = 'accessGrantSchema';
    const TYPE_UNKNOW = 'unknow';
    const TYPE_LOV = 'lov';
    const TYPE_QUERY = 'query';
    const TYPE_CONTENT_RESOURCE = 'contentResource';
    const TYPE_STYLE_TEMPLATE = 'jrtx';

    function __construct($name='', $wsType='', $uri='') {
        $this->name = $name;
        $this->wsType = $wsType;
        $this->uri = $uri;
    }

    function __toString() {
        $rv = '<resourceDescriptor name="' . htmlspecialchars($this->name) . '" wsType="' . htmlspecialchars($this->wsType) . '" uriString="' . htmlspecialchars($this->uri) . '" isNew="' . ($this->isNew ? 'true' : 'false') . '"><label>' . htmlspecialchars($this->label) . '</label>';
        if (!empty($this->description)) {
            $rv .= '<description>' . htmlspecialchars($this->description) . '</description>';
        }
        foreach ($this->properties as $key => $property) {
            if ($argument instanceof JasperResourceProperty) {
                $rv .= $property;
            } else {
                $rv .= '<resourceProperty name="' . htmlspecialchars($key) . '"><value>' . htmlspecialchars($this->value) . '</value></resourceProperty>';
            }
        }
        foreach ($this->parameters as $key => $parameter) {
            if ($argument instanceof JasperResourceProperty) {
                $rv .= $parameter;
            } else if (is_array($parameter)) {
                foreach ($parameter as $value) {
                    $rv .= '<parameter name="' . htmlspecialchars($key) . '" isListItem="true">' . htmlspecialchars($value) . '</parameter>';
                }
            } else {
                $rv = '<parameter name="' . htmlspecialchars($key) . '">' . htmlspecialchars($parameter) . '</parameter>';
            }
        }
        $rv .= '</resourceDescriptor>';
        return $rv;
    }

    function start_element($parser, $name, $attrs, $parent=null) {
        if (!is_null($parent)) {
            $this->parent_parser = $parent;
            $this->name = $attrs['name'];
            $this->wsType = $attrs['wsType'];
            $this->uri = $attrs['uriString'];
            if (isset($attrs['isNew'])) {
                $this->isNew = ($attrs['isNew'] == 'true');
            } else {
                $this->isNew = false;
            }
            xml_set_character_data_handler($parser, 'discard_data');
            return true;
        }
        switch ($name) {
        case 'label':
            xml_set_character_data_handler($parser, 'parse_label');
            return true;
        case 'description':
            xml_set_character_data_handler($parser, 'parse_description');
            return true;
        case 'resourceProperty':
            $prop = new JasperResourceProperty();
            xml_set_object($parser, $prop);
            $prop->start_element($parser, $name, $attrs, $this);
            $this->properties[$attrs['name']] = $prop;
            return true;
        case 'resourceDescriptor':
            $desc = new JasperResourceDescriptor();
            xml_set_object($parser, $desc);
            $desc->start_element($parser, $name, $attrs, $this);
            $this->children[] = $desc;
            return true;
        case 'parameter':
            if (isset($this->parameters[$attrs['name']])) {
                $param = $this->parameters[$attrs['name']];;
            } else {
                $param = new JasperResourceParameter();
                $this->parameters[$attrs['name']] = $param;
            }
            xml_set_object($parser, $param);
            $param->start_element($parser, $name, $attrs, $this);
            return true;
        }
        xml_set_character_data_handler($parser, 'discard_data');
    }

    function end_element($parser, $name) {
        switch ($name) {
        case 'resourceDescriptor':
            if (isset($this->parent_parser)) {
                xml_set_object($parser, $this->parent_parser);
                xml_set_character_data_handler($parser, 'discard_data');
                unset($this->parent_parser);
                return true;
            }
        }
        xml_set_character_data_handler($parser, 'discard_data');
        return true;
    }

    function discard_data($parser, $data) {
        return true;
    }

    function parse_label($parser, $data) {
        $this->label .= $data;
    }

    function parse_description($parser, $data) {
        $this->description .= $data;
    }
}

class JasperResourceProperty {
    public $name = '';
    public $value = '';
    public $properties = array();

    function __construct($name='', $value='') {
        $this->name = $name;
        $this->value = $value;
    }

    function __toString() {
        $rv = '<resourceProperty name="' . htmlspecialchars($this->name) . '">';
        if (!empty($this->value)) {
            $rv .= '<value>' . htmlspecialchars($this->value) . '</value>';
        }
        foreach ($this->properties as $property) {
            if ($argument instanceof JasperResourceProperty) {
                $rv .= $property;
            } else {
                $rv .= '<resourceProperty name="' . htmlspecialchars($key) . '"><value>' . htmlspecialchars($this->value) . '</value></resourceProperty>';
            }
        }
        $rv .= '</resourceProperty>';
    }

    function start_element($parser, $name, $attrs, $parent=null) {
        if (!is_null($parent)) {
            $this->parent_parser = $parent;
            $this->name = $attrs['name'];
            xml_set_character_data_handler($parser, 'discard_data');
            return true;
        }
        switch ($name) {
        case 'value':
            xml_set_character_data_handler($parser, 'parse_value');
            return true;
        case 'resourceProperty':
            $prop = new JasperResourceProperty();
            xml_set_object($parser, $prop);
            $prop->start_element($parser, $name, $attrs, $this);
            $this->properties[] = $prop;
            return true;
        }
        xml_set_character_data_handler($parser, 'discard_data');
    }

    function end_element($parser, $name) {
        switch ($name) {
        case 'resourceProperty':
            if (isset($this->parent_parser)) {
                xml_set_object($parser, $this->parent_parser);
                xml_set_character_data_handler($parser, 'discard_data');
                unset($this->parent_parser);
                return true;
            }
        }
        xml_set_character_data_handler($parser, 'discard_data');
        return true;
    }

    function discard_data($parser, $data) {
        return true;
    }

    function parse_value($parser, $data) {
        $this->value .= $data;
    }
}

class JasperParameter {
    public $name = '';
    public $value = '';

    function __construct($name='', $value='') {
        $this->name = $name;
        $this->value = $value;
    }

    function __toString() {
        if (is_array($value)) {
            $rv = '';
            foreach ($this->value as $value) {
                $rv .= '<parameter name="' . htmlspecialchars($this->name) . '" isListItem="true">' . htmlspecialchars($value) . '</parameter>';
            }
        } else {
            $rv = '<parameter name="' . htmlspecialchars($this->name) . '">' . htmlspecialchars($this->value) . '</parameter>';
        }
        return $rv;
    }

    function start_element($parser, $name, $attrs, $parent=null) {
        if (!is_null($parent)) {
            $this->parent_parser = $parent;
            $this->name = $attrs['name'];
            if (isset($attrs['isListItem']) && $attrs['isListItem'] == 'true')
            {
                if (!is_array($this->value)) {
                    $this->value = array();
                }
                $this->value[] = '';
            }
            xml_set_character_data_handler($parser, 'parse_value');
            return true;
        }
        xml_set_character_data_handler($parser, 'discard_data');
    }

    function end_element($parser, $name) {
        switch ($name) {
        case 'parameter':
            if (isset($this->parent_parser)) {
                xml_set_object($parser, $this->parent_parser);
                xml_set_character_data_handler($parser, 'discard_data');
                unset($this->parent_parser);
                return true;
            }
        }
        xml_set_character_data_handler($parser, 'discard_data');
        return true;
    }

    function discard_data($parser, $data) {
        return true;
    }

    function parse_value($parser, $data) {
        if (is_array($this->value)) {
            $this->value[count($this->value)-1] .= $data;
        } else {
            $this->value .= $data;
        }
    }
}

/**
 * Parses a response from JasperServer.
 */
class JasperResponse {
    public $returnCode = '';
    public $message = '';
    public $descriptors = array();
    public $attachments = array();

    function __construct($response) {
        $parser = xml_parser_create();
        xml_set_object($parser, $this);
        xml_set_element_handler($parser, 'start_element', 'end_element');
        xml_set_character_data_handler($parser, 'discard_data');
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);

        if (xml_parse($parser, $response) == 0) {
            $errcode = xml_get_error_code($parser);
            $errstring = xml_error_string($errcode);
            $lineno = xml_get_current_line_number($parser);
            // FIXME: do something useful
        }
    }

    function start_element($parser, $name, $attrs) {
        switch ($name) {
        case 'returnCode':
            xml_set_character_data_handler($parser, 'parse_code');
            return true;
        case 'message':
            xml_set_character_data_handler($parser, 'parse_code');
            return true;
        case 'resourceDescriptor':
            $desc = new JasperResourceDescriptor();
            xml_set_object($parser, $desc);
            $desc->start_element($parser, $name, $attrs, $this);
            $this->descriptors[] = $desc;
            return true;
        }
        xml_set_character_data_handler($parser, 'discard_data');
        return true;
    }

    function end_element($parser, $name) {
        xml_set_character_data_handler($parser, 'discard_data');
        return true;
    }

    function discard_data($parser, $data) {
        return true;
    }

    function parse_code($parser, $data) {
        $this->returnCode .= $data;
    }

    function parse_message($parser, $data) {
        $this->message .= $data;
    }
}

?>