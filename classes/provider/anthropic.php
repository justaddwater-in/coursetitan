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
 * Anthropic Claude provider (Messages API + output_config.format json_schema).
 *
 * The system prompt carries the source material and is marked for prompt caching,
 * so the per-module calls after the outline only pay full price for the short
 * module-specific message.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class anthropic extends base {
    /** @var string Default endpoint. */
    const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** @var string API version header value. */
    const API_VERSION = '2023-06-01';

    /**
     * Machine name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'anthropic';
    }

    /**
     * Build the request body (public for unit tests).
     *
     * @param string $system
     * @param array $messages
     * @param string $schemaname Unused by this vendor (kept for interface parity).
     * @param array $schema
     * @return array
     */
    public function build_request(string $system, array $messages, string $schemaname, array $schema): array {
        $chat = [];
        foreach ($messages as $m) {
            $chat[] = ['role' => $m['role'], 'content' => $m['content']];
        }
        return [
            'model' => $this->model,
            'max_tokens' => $this->maxtokens,
            'system' => [[
                'type' => 'text',
                'text' => $system,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => $chat,
            'output_config' => [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $schema,
                ],
            ],
        ];
    }

    /**
     * Send one request.
     *
     * @param string $system
     * @param array $messages
     * @param string $schemaname
     * @param array $schema
     * @return array
     */
    public function generate_json(string $system, array $messages, string $schemaname, array $schema): array {
        $response = $this->post_json(self::ENDPOINT, [
            'x-api-key: ' . $this->apikey,
            'anthropic-version: ' . self::API_VERSION,
        ], $this->build_request($system, $messages, $schemaname, $schema));
        return $this->parse_response($response);
    }

    /**
     * Extract JSON + usage from a Messages API response (public for unit tests).
     *
     * @param array $response
     * @return array
     * @throws provider_exception
     */
    public function parse_response(array $response): array {
        $text = '';
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $stop = $response['stop_reason'] ?? '';
        if ($stop === 'refusal') {
            throw new provider_exception('The model refused the request.');
        }
        if ($stop === 'max_tokens') {
            throw new provider_exception('Output was cut off at the token limit. Increase "Max output tokens" '
                . 'or use shorter source material.', 0, $text);
        }
        $usage = $response['usage'] ?? [];
        return [
            'data' => $this->decode_output($text),
            'usage' => [
                'in' => (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['cache_creation_input_tokens'] ?? 0),
                'out' => (int) ($usage['output_tokens'] ?? 0),
                'cache' => (int) ($usage['cache_read_input_tokens'] ?? 0),
            ],
        ];
    }
}
