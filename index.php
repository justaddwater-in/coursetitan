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
 * CourseTitan generator page.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_coursetitan\provider\factory;
use local_coursetitan\source_extractor;

require_login(null, false);

// Categories where this user may both use CourseTitan and create courses.
$categories = core_course_category::make_categories_list(['local/coursetitan:generate', 'moodle/course:create']);
if (!$categories) {
    throw new required_capability_exception(context_system::instance(), 'local/coursetitan:generate', 'nopermissions', '');
}
$default = optional_param('category', (int) get_config('local_coursetitan', 'category'), PARAM_INT);
if (!isset($categories[$default])) {
    $default = array_key_first($categories);
}

$url = new moodle_url('/local/coursetitan/index.php');
$PAGE->set_url($url);
$PAGE->set_context(context_coursecat::instance($default));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('generatecourse', 'local_coursetitan'));
$PAGE->set_heading(get_string('generatecourse', 'local_coursetitan'));

$provider = factory::create();
$categoryoptions = [];
foreach ($categories as $id => $name) {
    $categoryoptions[] = ['id' => $id, 'name' => $name, 'selected' => $id == $default];
}

$PAGE->requires->js_call_amd('local_coursetitan/generator', 'init', [[
    'streamUrl' => (new moodle_url('/local/coursetitan/stream.php'))->out(false),
    'sesskey' => sesskey(),
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_coursetitan/generator', [
    'configured' => $provider->is_configured(),
    'issiteadmin' => is_siteadmin(),
    'settingsurl' => (new moodle_url('/admin/category.php', ['category' => 'local_coursetitan']))->out(false),
    'categories' => $categoryoptions,
    'accept' => implode(',', array_map(fn($e) => '.' . $e, source_extractor::EXTENSIONS)),
    'extensions' => implode(', ', source_extractor::EXTENSIONS),
    'model' => $provider->get_model(),
    'visible' => (bool) get_config('local_coursetitan', 'coursevisible'),
]);
echo $OUTPUT->footer();
