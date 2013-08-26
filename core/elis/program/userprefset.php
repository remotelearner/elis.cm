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

/**
 * This script called via ajax from pages requiring to set user preferences without an explicit form submit button
 * (see curriculum/js/dashboard.js for example usage)
 * Returns OK or ERROR
 */

require_once('../../config.php');

$param = required_param('param', PARAM_CLEAN);
$value = optional_param('value', null, PARAM_CLEAN);

if (!isloggedin() || isguestuser()) {
    mtrace('ERROR');
} else {
    if (set_user_preference($param,$value)) {
        mtrace('OK');
    } else {
        mtrace('ERROR');
    }
}

