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

namespace local_groupbyprofilefields;

use \core\event;
//use \local_autogroup\usecase;

/**
 * Class event_handler
 *
 * Functions which are triggered by Moodles events and carry out
 * the necessary logic to maintain membership.
 *
 * @package local_groupbyprofilefields
 */
class event_handler
{
	private static function array_cartesian_product(array $arr, string $seperator = ' ')
	{
		if (empty($arr))
			return $arr;
		if (sizeof($arr) == 1)
			return $arr[0];

		$arr_first = array_shift($arr);
		$arr_rest = self::array_cartesian_product($arr, $seperator);
		$result = [];

		foreach ($arr_first as $first)
		{
			foreach($arr_rest as $rest)
			{
				array_push($result, $first . $seperator . $rest);
			}
		}

		return $result;
	}

	/**
	 * @param event\user_enrolment_created $event
	 * @return mixed
	 */
	public static function user_enrolment_created(event\user_enrolment_created $event)
	{
		$profilefield_names = ['profile_field_schoolname', 'profile_field_Massnahme', 'profile_field_Beruf'];



		$courseid = (int) $event->courseid;
		$userid = (int) $event->relateduserid;

		$profilefields = profile_get_user_fields_with_data($userid);


		//get groupnames groupname
		$profilefield_values = [];
		foreach($profilefields as $profilefield)
		{
			$key = array_search($profilefield->inputname, $profilefield_names);
			if (is_int($key))
			{
				$profilefield_values[$key] = explode("\n", $profilefield->data);
			}
		}

		//create all combinations (cartesian product)
		//if (array_is_list($profilefield_values))	//PHP 8.1
		ksort($profilefield_values);
		$groupnames = self::array_cartesian_product($profilefield_values);

		$groupid = groups_get_group_by_name($courseid, $groupnames[0]);

		if (!$groupid)
		{
			//groups_create_group();
		}

		//groups_add_member($groupid, $userid);
	}

	/**
	 * @param event\user_updated $event
	 * @return mixed
	 */
	public static function user_updated(event\user_updated $event)
	{
		$pluginconfig = get_config('local_autogroup');
	}

	public static function user_created(event\user_created $event)
	{
		//cohort_add_cohort(array('name' => 'MGG_abc'));
		//role_assign();
	}
}
