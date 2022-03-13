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

//require_once($CFG->dirroot."/local/groupbyprofilefields/locallib.php");

if ($hassiteconfig){
    $pluginname = get_string("pluginname","local_groupbyprofilefields");
    $options = $DB->get_records_menu('user_info_field',null,'',"id, CONCAT(shortname,' (',name,')')");

    $ADMIN->add('localplugins',new admin_category('localgroupbyprofilefields',$pluginname));
    $settings = new admin_settingpage('local_groupbyprofilefields_settings', get_string('settings'));
    $settings->add(new admin_setting_heading('local_groupbyprofilefields/settings',get_string('settings'),''));
    $settings->add(new admin_setting_configmultiselect('local_groupbyprofilefields/linkedfields', get_string('settings_groupbyprofilefields_linkedfields','local_groupbyprofilefields'),'',[],$options));

    $ADMIN->add('localgroupbyprofilefields', $settings);
}