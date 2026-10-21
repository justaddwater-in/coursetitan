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
 * OpenAI provider (Chat Completions + strict json_schema response format).
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openai extends base {
    /** @var string Default endpoint. */
    const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Machine name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'openai';
    }

    /**
     * Build the request body (public for unit tests).
     *
     * @param string $system
     * @param array $messages
     * @param string $schemaname
     * @param array $schema
     * @return array
     */
    public function build_request(string $system, array $messages, string $schemaname, array $schema): array {
        $chat = [['role' => 'system', 'content' => $system]];
        foreach ($messages as $m) {
            $chat[] = ['role' => $m['role'], 'content' => $m['content']];
        }
        return [
            'model' => $this->model,
            'messages' => $chat,
            // Works for both classic and reasoning models (max_tokens is rejected by the latter).
            'max_completion_tokens' => $this->maxtokens,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schemaname,
                    'strict' => true,
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
        $response = $this->post_json(self::ENDPOINT, ['Authorization: Bearer ' . $this->apikey],
            $this->build_request($system, $messages, $schemaname, $schema));
        return $this->parse_response($response);
    }

    /**
     * Extract JSON + usage from a Chat Completions response (public for unit tests).
     *
     * @param array $response
     * @return array
     * @throws provider_exception
     */
    public function parse_response(array $response): array {
        $choice = $response['choices'][0] ?? null;
        if (!$choice) {
            throw new provider_exception('OpenAI response contained no choices.');
        }
        $message = $choice['message'] ?? [];
        if (!empty($message['refusal'])) {
            throw new provider_exception('The model refused the request: ' . $message['refusal']);
        }
        $content = (string) ($message['content'] ?? '');
        if (($choice['finish_reason'] ?? '') === 'length') {
            throw new provider_exception('Output was cut off at the token limit. Increase "Max output tokens" '
                . 'or use shorter source material.', 0, $content);
        }
        $usage = $response['usage'] ?? [];
        return [
            'data' => $this->decode_output($content),
            'usage' => [
                'in' => (int) ($usage['prompt_tokens'] ?? 0),
                'out' => (int) ($usage['completion_tokens'] ?? 0),
                'cache' => (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0),
            ],
        ];
    }
}
