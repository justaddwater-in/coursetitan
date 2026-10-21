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
 * Admin page that sends a tiny schema-constrained request to each configured provider.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_coursetitan\provider\factory;
use local_coursetitan\schema;

admin_externalpage_setup('local_coursetitan_diagnostics');
$PAGE->set_heading(get_string('diagnostics', 'local_coursetitan'));

$run = optional_param('run', 0, PARAM_BOOL) && confirm_sesskey();
$active = get_config('local_coursetitan', 'provider') ?: 'openai';

$rows = [];
foreach (factory::get_choices() as $name => $label) {
    $provider = factory::create($name);
    $row = [
        'label' => $label,
        'model' => $provider->get_model(),
        'active' => $name === $active,
        'configured' => $provider->is_configured(),
        'tested' => false,
    ];
    if ($run && $provider->is_configured()) {
        $started = microtime(true);
        try {
            $result = $provider->generate_json('Connectivity check. Set ok to true.',
                [['role' => 'user', 'content' => 'ping']], 'ping', schema::ping());
            $row['ok'] = !empty($result['data']['ok']);
            $row['message'] = $row['ok'] ? get_string('diag_ok', 'local_coursetitan')
                : get_string('diag_unexpected', 'local_coursetitan', json_encode($result['data']));
        } catch (\Throwable $e) {
            $row['ok'] = false;
            $row['message'] = $e->getMessage();
        }
        $row['tested'] = true;
        $row['ms'] = (int) round((microtime(true) - $started) * 1000);
    }
    $rows[] = $row;
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_coursetitan/diagnostics', [
    'rows' => $rows,
    'actionurl' => (new moodle_url('/local/coursetitan/diagnostics.php'))->out(false),
    'sesskey' => sesskey(),
]);
echo $OUTPUT->footer();
