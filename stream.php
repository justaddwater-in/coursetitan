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
 * Runs a generation and reports progress to the browser as Server-Sent Events.
 *
 * POST: sesskey, category, text, sources[] (files).
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_coursetitan\course_builder;
use local_coursetitan\generator;
use local_coursetitan\local\event_stream;
use local_coursetitan\provider\factory;
use local_coursetitan\source_extractor;

/** Maximum plain-text input (bytes) sent to the AI. */
const LOCAL_COURSETITAN_MAX_INPUT = 524288;
/** Maximum uploaded files per request. */
const LOCAL_COURSETITAN_MAX_FILES = 10;
/** Maximum combined upload size (bytes), before extraction. */
const LOCAL_COURSETITAN_MAX_UPLOAD_BYTES = 26214400;
/** Maximum individual upload size (bytes). */
const LOCAL_COURSETITAN_MAX_FILE_BYTES = 10485760;

require_login(null, false);
require_sesskey();

$categoryid = required_param('category', PARAM_INT);
$requestedprovider = optional_param('provider', '', PARAM_ALPHA);
$text = trim(optional_param('text', '', PARAM_RAW));
$catcontext = context_coursecat::instance($categoryid);
require_capability('local/coursetitan:generate', $catcontext);
require_capability('moodle/course:create', $catcontext);

$stream = new event_stream();

// Build the source material: typed text first, then each uploaded file under its own heading.
$parts = $text !== '' ? [$text] : [];
$files = $_FILES['sources'] ?? null;
if ($files && is_array($files['tmp_name'])) {
    $filecount = count($files['tmp_name']);
    $totalsize = array_sum(array_map('intval', $files['size'] ?? []));
    if ($filecount > LOCAL_COURSETITAN_MAX_FILES || $totalsize > LOCAL_COURSETITAN_MAX_UPLOAD_BYTES) {
        $stream->error(get_string('sourceuploadlimits', 'local_coursetitan'));
        die;
    }
    foreach ($files['tmp_name'] as $i => $tmp) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            continue;
        }
        $filesize = (int) ($files['size'][$i] ?? 0);
        if ($filesize <= 0 || $filesize > LOCAL_COURSETITAN_MAX_FILE_BYTES) {
            $stream->error(get_string('sourcefilelimits', 'local_coursetitan'));
            die;
        }
        $name = clean_param($files['name'][$i], PARAM_FILE);
        $content = source_extractor::extract($tmp, $name);
        if ($content !== '') {
            $parts[] = "## Source file: {$name}\n\n{$content}";
        }
    }
}
$source = implode("\n\n", $parts);
if ($requestedprovider !== '') {
    if (!array_key_exists($requestedprovider, factory::get_choices())) {
        $stream->error(get_string('invalidprovider', 'local_coursetitan'));
        die;
    }
    $provider = factory::create($requestedprovider);
} else {
    // Backward compatibility for requests from older cached pages.
    $provider = factory::create();
}


if (!$provider->is_configured()) {
    $stream->error(get_string('providerunavailable', 'local_coursetitan'));
    die;
}
if ($source === '') {
    $stream->error(get_string('nosource', 'local_coursetitan'));
    die;
}
if (strlen($source) > LOCAL_COURSETITAN_MAX_INPUT) {
    $stream->error(get_string('sourcetoolarge', 'local_coursetitan', display_size(LOCAL_COURSETITAN_MAX_INPUT)));
    die;
}

// Finish the job even if the teacher closes the tab; the course appears either way.
ignore_user_abort(true);
core_php_time_limit::raise(3600);
raise_memory_limit(MEMORY_EXTRA);

$stream->send(['event' => 'source', 'chars' => core_text::strlen($source)]);
$provider->set_heartbeat([$stream, 'keepalive']);

try {
    $spec = (new generator($provider, [$stream, 'send']))->run($source);

    $stream->send(['event' => 'stage_start', 'stage' => 'build']);
    $started = microtime(true);
    $course = (new course_builder())->build($spec, $categoryid, (bool) get_config('local_coursetitan', 'coursevisible'));
    $activities = array_sum(array_map(fn($s) => count($s['modules']), $spec['sections']));
    $stream->send([
        'event' => 'stage_done',
        'stage' => 'build',
        'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
        'detail' => get_string('builtsummary', 'local_coursetitan',
            ['sections' => count($spec['sections']), 'activities' => $activities]),
    ]);

    $stream->send([
        'event' => 'done',
        'title' => format_string($course->fullname),
        'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'model' => $provider->get_model(),
        'usage' => $spec['usage'],
        'warnings' => $spec['warnings'],
    ]);
} catch (\Throwable $e) {
    debugging('local_coursetitan: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
    $message = $e instanceof \moodle_exception
        ? $e->getMessage()
        : get_string('generation_unexpected_error', 'local_coursetitan');
    $stream->error($message);
}
