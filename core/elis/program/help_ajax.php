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
 *
 */

define('NO_MOODLE_COOKIES', true);

require_once(dirname(__FILE__).'/../../config.php');

$helphead = required_param('heading', PARAM_TEXT);
$helptext = required_param('helptext', PARAM_TEXT);
$ajax     = optional_param('ajax', 1, PARAM_BOOL);

$PAGE->set_url('/elis/program/help_ajax.php');
$PAGE->set_pagelayout('popup');
$PAGE->set_context(context_system::instance());

if ($ajax) {
    @header('Content-Type: text/plain; charset=utf-8');
} else {
    echo $OUTPUT->header();
}

$options = new stdClass();
$options->trusted = false;
$options->noclean = false;
$options->smiley = false;
$options->filter = false;
$options->para = true;
$options->newlines = false;
$options->overflowdiv = !$ajax;

if (!$ajax) {
    echo $OUTPUT->heading($helphead, 1, 'helpheading');
    echo format_text($helptext, FORMAT_MARKDOWN, $options);
    echo $OUTPUT->footer();
} else {
    echo json_encode((object)array(
        'heading' => $helphead,
        'text'    => format_text($helptext, FORMAT_MARKDOWN, $options)
    ));
}
