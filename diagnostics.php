<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This file is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin page for testing configured AI providers individually.
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

$choices = factory::get_choices();
$active = get_config('local_coursetitan', 'provider') ?: 'openai';
$selectedprovider = optional_param('provider', '', PARAM_ALPHA);
$run = optional_param('run', 0, PARAM_BOOL);
$notification = null;
$testedprovider = null;
$testresult = null;

// Each row has its own POST form. Only the provider named by that form is called.
if ($run) {
    require_sesskey();

    if (!array_key_exists($selectedprovider, $choices)) {
        throw new moodle_exception('invalidprovider', 'local_coursetitan');
    }

    $provider = factory::create($selectedprovider);
    $testedprovider = $selectedprovider;

    if (!$provider->is_configured()) {
        $testresult = [
            'ok' => false,
            'message' => get_string('diag_nokey', 'local_coursetitan'),
            'ms' => 0,
        ];
    } else {
        $started = microtime(true);
        try {
            $result = $provider->generate_json(
                'Connectivity check. Set ok to true.',
                [['role' => 'user', 'content' => 'ping']],
                'ping',
                schema::ping()
            );

            $ok = !empty($result['data']['ok']);
            $message = $ok
                ? get_string('diag_ok', 'local_coursetitan')
                : get_string('diag_unexpected', 'local_coursetitan', json_encode($result['data']));
            $testresult = [
                'ok' => $ok,
                'message' => $message,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        } catch (\Throwable $e) {
            $message = $e instanceof \moodle_exception
                ? $e->getMessage()
                : get_string('diag_unexpected_error', 'local_coursetitan');
            $testresult = [
                'ok' => false,
                'message' => $message,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
    }

    $notification = [
        'type' => !empty($testresult['ok']) ? 'success' : 'error',
        'message' => !empty($testresult['ok'])
            ? get_string('diag_notification_success', 'local_coursetitan', $choices[$selectedprovider])
            : get_string('diag_notification_failure', 'local_coursetitan', $choices[$selectedprovider]),
    ];
}

$rows = [];
foreach ($choices as $name => $label) {
    $provider = factory::create($name);
    $configured = $provider->is_configured();
    $tested = $name === $testedprovider && $testresult !== null;

    $rows[] = [
        'providerid' => $name,
        'label' => $label,
        'model' => $provider->get_model(),
        'active' => $name === $active,
        'configured' => $configured,
        'configurationlabel' => get_string(
            $configured ? 'diag_configured' : 'diag_nokey',
            'local_coursetitan'
        ),
        'tested' => $tested,
        'ok' => $tested && !empty($testresult['ok']),
        'message' => $tested ? $testresult['message'] : '',
        'responsetime' => $tested
            ? get_string('diag_response_time', 'local_coursetitan', format_float($testresult['ms'] / 1000, 2))
            : '',
        'testlabel' => get_string('diag_test_provider', 'local_coursetitan', $label),
    ];
}

$PAGE->requires->js_call_amd('local_coursetitan/diagnostics', 'init');
echo $OUTPUT->header();

if ($notification !== null) {
    echo $OUTPUT->notification($notification['message'], $notification['type']);
}

echo $OUTPUT->render_from_template('local_coursetitan/diagnostics', [
    'rows' => $rows,
    'actionurl' => (new moodle_url('/local/coursetitan/diagnostics.php'))->out(false),
    'sesskey' => sesskey(),
]);
echo $OUTPUT->footer();
