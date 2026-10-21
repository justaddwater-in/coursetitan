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
 * Builds the provider selected in the plugin settings.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class factory {
    /** @var string Default OpenAI model (admins can change it in settings). */
    const DEFAULT_OPENAI_MODEL = 'gpt-5-mini';

    /** @var string Default Claude model (admins can change it in settings). */
    const DEFAULT_ANTHROPIC_MODEL = 'claude-sonnet-5-5';

    /**
     * Provider choices for the settings drop-down.
     *
     * @return array
     */
    public static function get_choices(): array {
        return [
            'openai' => get_string('provider_openai', 'local_coursetitan'),
            'anthropic' => get_string('provider_anthropic', 'local_coursetitan'),
        ];
    }

    /**
     * Instantiate the active provider (or a named one).
     *
     * @param string|null $name 'openai' | 'anthropic'; null = the configured active provider.
     * @return base
     */
    public static function create(?string $name = null): base {
        $cfg = get_config('local_coursetitan');
        $name = $name ?? ($cfg->provider ?? 'openai');
        $timeout = (int) ($cfg->timeout ?? 300) ?: 300;
        $maxtokens = (int) ($cfg->maxtokens ?? 16000) ?: 16000;

        switch ($name) {
            case 'anthropic':
                return new anthropic((string) ($cfg->anthropickey ?? ''),
                    (string) (($cfg->anthropicmodel ?? '') ?: self::DEFAULT_ANTHROPIC_MODEL), $timeout, $maxtokens);
            case 'openai':
            default:
                return new openai((string) ($cfg->openaikey ?? ''),
                    (string) (($cfg->openaimodel ?? '') ?: self::DEFAULT_OPENAI_MODEL), $timeout, $maxtokens);
        }
    }
}
