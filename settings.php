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
 * CourseTitan admin tree: settings page, diagnostics page and the generator link.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_coursetitan\provider\factory;

// Generator page, listed under Site administration > Courses.
$ADMIN->add('courses', new admin_externalpage(
    'local_coursetitan_generate',
    get_string('generatecourse', 'local_coursetitan'),
    new moodle_url('/local/coursetitan/index.php'),
    'local/coursetitan:generate'
));

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_coursetitan', get_string('pluginname', 'local_coursetitan')));

    $settings = new admin_settingpage('local_coursetitan_settings', get_string('settings', 'local_coursetitan'));
    $p = 'local_coursetitan/';
    $s = fn(string $key, $a = null) => get_string($key, 'local_coursetitan', $a);

    // Provider.
    $settings->add(new admin_setting_heading($p . 'h_provider', $s('heading_provider'),
        $s('heading_provider_desc', (new moodle_url('/local/coursetitan/diagnostics.php'))->out())));
    $settings->add(new admin_setting_configselect($p . 'provider', $s('provider'), $s('provider_desc'),
        'openai', factory::get_choices()));
    $settings->add(new admin_setting_configpasswordunmask($p . 'openaikey', $s('openaikey'), $s('openaikey_desc'), ''));
    $settings->add(new admin_setting_configtext($p . 'openaimodel', $s('openaimodel'), $s('openaimodel_desc'),
        factory::DEFAULT_OPENAI_MODEL, PARAM_NOTAGS));
    $settings->add(new admin_setting_configpasswordunmask($p . 'anthropickey', $s('anthropickey'), $s('anthropickey_desc'), ''));
    $settings->add(new admin_setting_configtext($p . 'anthropicmodel', $s('anthropicmodel'), $s('anthropicmodel_desc'),
        factory::DEFAULT_ANTHROPIC_MODEL, PARAM_NOTAGS));

    // Course defaults.
    $settings->add(new admin_setting_heading($p . 'h_course', $s('heading_course'), ''));
    $settings->add(new admin_settings_coursecat_select($p . 'category', $s('category'), $s('category_desc'), 1));
    $settings->add(new admin_setting_configcheckbox($p . 'coursevisible', $s('coursevisible'), $s('coursevisible_desc'), 0));
    $settings->add(new admin_setting_configtext($p . 'maxsections', $s('maxsections'), $s('maxsections_desc'), 6, PARAM_INT, 4));
    $settings->add(new admin_setting_configtext($p . 'maxactivities', $s('maxactivities'), $s('maxactivities_desc'),
        4, PARAM_INT, 4));
    $settings->add(new admin_setting_configtext($p . 'quizquestions', $s('quizquestions'), $s('quizquestions_desc'),
        5, PARAM_INT, 4));
    $settings->add(new admin_setting_configtextarea($p . 'instructions', $s('instructions'), $s('instructions_desc'), ''));

    // Advanced.
    $settings->add(new admin_setting_heading($p . 'h_advanced', $s('heading_advanced'), ''));
    $settings->add(new admin_setting_configtext($p . 'maxtokens', $s('maxtokens'), $s('maxtokens_desc'), 16000, PARAM_INT, 8));
    $settings->add(new admin_setting_configtext($p . 'timeout', $s('timeout'), $s('timeout_desc'), 300, PARAM_INT, 8));

    $ADMIN->add('local_coursetitan', $settings);
    $ADMIN->add('local_coursetitan', new admin_externalpage('local_coursetitan_diagnostics', $s('diagnostics'),
        new moodle_url('/local/coursetitan/diagnostics.php'), 'moodle/site:config'));

    // Moodle expects $settings to be the plugin's settings page or null when a category is used.
    $settings = null;
}
