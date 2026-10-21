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
 * CourseTitan navigation callbacks.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "Generate course with AI" to a course category's More menu.
 *
 * This is how category-level course creators (who never see Site administration) find the tool.
 *
 * @param navigation_node $navigation
 * @param context_coursecat $context
 */
function local_coursetitan_extend_navigation_category_settings(navigation_node $navigation, context_coursecat $context): void {
    if (has_all_capabilities(['local/coursetitan:generate', 'moodle/course:create'], $context)) {
        $navigation->add(
            get_string('generatecourse', 'local_coursetitan'),
            new moodle_url('/local/coursetitan/index.php', ['category' => $context->instanceid]),
            navigation_node::TYPE_SETTING,
            null,
            'local_coursetitan_generate',
            new pix_icon('i/course', '')
        );
    }
}
