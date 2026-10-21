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

namespace local_coursetitan\provider;

/**
 * Error raised by an LLM provider call.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_exception extends \moodle_exception {
    /** @var int HTTP status (0 when not applicable). */
    public int $httpstatus;

    /** @var string|null Raw model output when the failure was a parse error (used for repair prompts). */
    public ?string $rawoutput;

    /**
     * Constructor.
     *
     * @param string $message Plain-text description.
     * @param int $httpstatus HTTP status, if any.
     * @param string|null $rawoutput Raw model output, if any.
     */
    public function __construct(string $message, int $httpstatus = 0, ?string $rawoutput = null) {
        $this->httpstatus = $httpstatus;
        $this->rawoutput = $rawoutput;
        parent::__construct('providererror', 'local_coursetitan', '', $message);
    }
}
