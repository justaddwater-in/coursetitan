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

namespace local_coursetitan\local;

/**
 * Minimal Server-Sent Events writer for long-running generation requests.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_stream {
    /** @var bool Whether headers have been sent. */
    private bool $open = false;

    /**
     * Start the stream: unlock the session, drop output buffers, send headers.
     */
    public function open(): void {
        if ($this->open) {
            return;
        }
        // The session lock would otherwise block the user's other tabs for minutes.
        \core\session\manager::write_close();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
        header('Content-Encoding: identity');
        $this->open = true;
        // Some servers only start forwarding after the first few bytes.
        echo ':' . str_repeat(' ', 2048) . "\n\n";
        flush();
    }

    /**
     * Send one JSON event.
     *
     * @param array $event Must contain an 'event' key.
     */
    public function send(array $event): void {
        $this->open();
        echo 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        flush();
    }

    /**
     * Send a comment line (ignored by clients) to keep proxies from timing out.
     */
    public function keepalive(): void {
        $this->open();
        echo ": keepalive\n\n";
        flush();
    }

    /**
     * Send an error event.
     *
     * @param string $message
     */
    public function error(string $message): void {
        $this->send(['event' => 'error', 'message' => $message]);
    }
}
