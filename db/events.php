<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * autogroup local plugin
 *
 * This plugin automatically assigns users to a group within any course
 * upon which they may be enrolled and which has auto-grouping
 * configured.
 *
 * @package    local
 * @subpackage groupbyprofilefields
 * @author     Fabian Bech (f.bech@koppelsberg.de)
 * @copyright  Fabian Bech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(
	array(
		'eventname' => '\core\event\user_enrolment_created',
		'callback' => '\local_groupbyprofilefields\event_handler::user_enrolment_created',
		'includefile' => 'local/groupbyprofilefields/classes/event_handler.php',
	),

	array(
		'eventname' => '\core\event\user_updated',
		'callback' => '\local_groupbyprofilefields\event_handler::user_updated',
		'includefile' => 'local/groupbyprofilefields/classes/event_handler.php',
	),

	array(
		'eventname' => '\core\event\user_created',
		'callback' => '\local_groupbyprofilefields\event_handler::user_created',
		'includefile' => 'local/groupbyprofilefields/classes/event_handler.php',
	)
);
