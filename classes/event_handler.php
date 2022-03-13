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

require_once($CFG->dirroot."/group/lib.php");
require_once($CFG->dirroot."/cache/lib.php");

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
	private static $_cfg = null;

	private static function create_missing_groups($userid,$groupname){
		global $DB;
		//ensure $_cfg is initialized, maybe there is a better way...
		self::$_cfg?null:self::$_cfg = get_config('local_groupbyprofilefields');
		if(!(isset(self::$_cfg->enrols) && !empty(self::$_cfg->enrols))){
			return;
		}
		list($sqlin,$params) = $DB->get_in_or_equal(explode(",",self::$_cfg->enrols), SQL_PARAMS_NAMED);

		$sql = "INSERT INTO {groups} (courseid, name, idnumber, timecreated, timemodified)
				SELECT DISTINCT c.id AS courseid, :groupname1, :groupname_suffixed, unix_timestamp(), unix_timestamp() 
					FROM mdl_course c
					JOIN {groups} g ON g.courseid = c.id
					JOIN {groups_members} gm ON g.id = gm.groupid
					JOIN {enrol} e 
						ON e.courseid = c.id"
						.($sqlin?" AND e.enrol ":"").$sqlin.
					"JOIN {user_enrolments} ue ON ue.enrolid = e.id
					WHERE NOT EXISTS (
						SELECT * 
						FROM {groups} g2
						WHERE g2.name = :groupname2
						AND g.courseid = g2.courseid)
					AND ue.userid = :userid";
		$params["groupname1"] = $params["groupname2"] = $groupname;
		$params["groupname_suffixed"] = $groupname." local_groupbyprofilefields";
		$params["userid"] = $userid;
		$DB->execute($sql, $params);
	}

	private static function create_missing_enrolments($userid,$groupname){
		global $DB;
		//ensure $_cfg is initialized, maybe there is a better way...
		self::$_cfg?null:self::$_cfg = get_config('local_groupbyprofilefields');
		if(!(isset(self::$_cfg->enrols) && !empty(self::$_cfg->enrols))){
			return;
		}
		list($sqlin,$params) = $DB->get_in_or_equal(explode(",",self::$_cfg->enrols), SQL_PARAMS_NAMED);

		$sql = "INSERT INTO {groups_members} (groupid,component, timeadded, userid) 
				SELECT DISTINCT g.id AS groupid, 'local_groupbyprofilefields', unix_timestamp(), :userid1
					FROM {groups} g
					JOIN {course} c ON c.id = g.courseid
					JOIN {enrol} e 
						ON e.courseid = c.id"
						.($sqlin?" AND e.enrol ":"").$sqlin.
					"JOIN {user_enrolments} ue ON ue.enrolid = e.id
					WHERE NOT EXISTS (
						SELECT * 
						FROM {groups_members} gm
						WHERE gm.groupid = g.id
						AND gm.userid = :userid2)
					AND g.name = :groupname
					AND ue.userid = :userid3";
		$params["groupname"] = $groupname;
		$params["userid1"] = $params["userid2"] = $params["userid3"] = $userid;

		$DB->execute($sql, $params);
	}
	private static function remove_from_groups($userid,$groupnames){
		global $DB;
		list($sqlin,$params) = $DB->get_in_or_equal($groupnames, SQL_PARAMS_NAMED, 'param', false);
		$sql = "DELETE 
				FROM {groups_members} gm
				WHERE gm.component = 'local_groupbyprofilefields' AND gm.userid = :userid
				AND EXISTS (
					SELECT * 
					FROM {groups} g 
					WHERE g.id = gm.groupid 
					AND g.name ".$sqlin.")";
		$params["userid"] = $userid;
		$DB->execute($sql, $params);
	}
	private static function remove_selfgenerated_empty_groups(){
		global $DB;
		$sql = "DELETE 
				FROM {groups} g
				WHERE g.idnumber LIKE '%local_groupbyprofilefields'
				AND NOT EXISTS(
					SELECT * 
					FROM mdl_groups_members gm 
					WHERE gm.groupid = g.id 
					AND gm.component = 'local_groupbyprofilefields')";

		$DB->execute($sql, []);
	}

	private static function get_linked_profilefield_values($userid){
		//ensure $_cfg is initialized, maybe there is a better way...
		self::$_cfg?null:self::$_cfg = get_config('local_groupbyprofilefields');
		if(!(isset(self::$_cfg->linkedfields) && !empty(self::$_cfg->linkedfields))){
			return;
		}
		$linkedfield_ids = explode(',',self::$_cfg->linkedfields);

		$profilefields = profile_get_user_fields_with_data($userid);

		//get groupnames groupname
		$profilefield_values = [];
		foreach($profilefields as $profilefield)
		{
			if(in_array($profilefield->fieldid, $linkedfield_ids)){
				$value = json_decode($profilefield->data);
				if(json_last_error() !== JSON_ERROR_NONE){
					$value = explode("\n", $profilefield->data);
				}
				if(!empty($value = array_filter($value))){
					natsort($value);
					$profilefield_values[] = $value;
				}
			}
		}
		return $profilefield_values;
	}

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
		$courseid = (int) $event->courseid;
		$userid = (int) $event->relateduserid;

		$profilefield_values = self::get_linked_profilefield_values($userid);
		
		if(!empty($profilefield_values)){
			//create all combinations (cartesian product)
			//if (array_is_list($profilefield_values))	//PHP 8.1
			//natsort($profilefield_values);
			$groupnames = self::array_cartesian_product($profilefield_values);

			foreach($groupnames as $groupname){
				$groupid = \groups_get_group_by_name($courseid, $groupname);
				if (!$groupid)
				{
					$data = new \stdClass();
					$data->courseid = $courseid;
					$data->name = $groupname;
					$data->idnumber = $groupname;
					$groupid = \groups_create_group($data);
				}
				if($groupid){
					\groups_add_member($groupid, $userid, 'local_groupbyprofilefields');
				}
			}
		}
		\cache_helper::invalidate_by_definition('core', 'user_group_groupings', array(), array($userid));
	}

	/**
	 * @param event\user_updated $event
	 * @return mixed
	 */
	public static function user_updated(event\user_updated $event)
	{
		$userid = (int) $event->relateduserid;
		$profilefield_values = self::get_linked_profilefield_values($userid);

		$groups_profilefields = self::array_cartesian_product($profilefield_values);

		foreach($groups_profilefields as $groupname){
			self::create_missing_groups($userid, $groupname);
			self::create_missing_enrolments($userid, $groupname);
		}
		self::remove_from_groups($userid,$groups_profilefields);
		//remove unused groups?
		//I cannot find a way to determine for sure, which groups were created by us
		//right now identified by idnumber, which ist editable by users...
		self::remove_selfgenerated_empty_groups();

		\cache_helper::invalidate_by_definition('core', 'user_group_groupings', array(), array($userid));
		\cache_helper::purge_by_definition('core', 'groupdata');
	}

	public static function user_created(event\user_created $event)
	{
		//cohort_add_cohort(array('name' => 'MGG_abc'));
		//role_assign();
	}
}
