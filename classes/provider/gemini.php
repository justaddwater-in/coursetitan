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
 * Google Gemini provider using generateContent and schema-constrained JSON output.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini extends base {
    /** @var string Default Gemini API endpoint. */
    const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** @var string Default model suitable for structured output. */
    const DEFAULT_MODEL = 'gemini-3.8-flash';

    /**
     * Machine name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'gemini';
    }

    /**
     * Build the generateContent request body (public for unit tests).
     *
     * @param string $system
     * @param array $messages
     * @param string $schemaname Unused by Gemini; retained for provider interface parity.
     * @param array $schema
     * @return array
     */
    public function build_request(string $system, array $messages, string $schemaname, array $schema): array {
        $contents = [];
        foreach ($messages as $message) {
            $contents[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ];
        }

        return [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $this->maxtokens,
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->normalise_schema($schema),
            ],
        ];
    }

    /**
     * Gemini supports the common schema keywords used by CourseTitan. Keep this
     * conversion explicit so future schema additions do not silently leak unsupported
     * JSON Schema keywords into Google's API.
     *
     * @param array $schema
     * @return array
     */
    private function normalise_schema(array $schema): array {
        $supported = ['type', 'format', 'description', 'nullable', 'enum', 'items', 'properties',
            'required', 'minItems', 'maxItems', 'minimum', 'maximum', 'propertyOrdering'];
        $result = [];
        foreach ($schema as $key => $value) {
            if ($key === 'additionalProperties') {
                // Gemini structured output schemas disallow additional properties by default.
                continue;
            }
            if (!in_array($key, $supported, true)) {
                continue;
            }
            if ($key === 'properties' && is_array($value)) {
                $result[$key] = [];
                foreach ($value as $name => $property) {
                    $result[$key][$name] = $this->normalise_schema($property);
                }
            } else if (($key === 'items') && is_array($value)) {
                $result[$key] = $this->normalise_schema($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
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
        if (!$this->is_configured()) {
            throw new provider_exception(\get_string('provider_not_configured', 'local_coursetitan'));
        }
        $url = self::ENDPOINT . rawurlencode($this->model) . ':generateContent';
        $response = $this->post_json($url, ['x-goog-api-key: ' . $this->apikey],
            $this->build_request($system, $messages, $schemaname, $schema));
        return $this->parse_response($response);
    }

    /**
     * Extract JSON and token usage from a Gemini generateContent response.
     *
     * @param array $response
     * @return array
     * @throws provider_exception
     */
    public function parse_response(array $response): array {
        $candidate = $response['candidates'][0] ?? null;
        if (!$candidate) {
            $blockreason = $response['promptFeedback']['blockReason'] ?? null;
            if ($blockreason) {
                throw new provider_exception(\get_string('gemini_prompt_blocked', 'local_coursetitan', $blockreason));
            }
            throw new provider_exception(\get_string('gemini_no_candidates', 'local_coursetitan'));
        }

        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }
        $finishreason = $candidate['finishReason'] ?? '';
        if ($finishreason === 'MAX_TOKENS') {
            throw new provider_exception(\get_string('provider_output_truncated', 'local_coursetitan'), 0, $text);
        }
        if (in_array($finishreason, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT'], true)) {
            throw new provider_exception(\get_string('gemini_finish_reason', 'local_coursetitan', $finishreason));
        }
        if ($text === '') {
            throw new provider_exception(\get_string('provider_empty_response', 'local_coursetitan'));
        }

        $usage = $response['usageMetadata'] ?? [];
        return [
            'data' => $this->decode_output($text),
            'usage' => [
                'in' => (int) ($usage['promptTokenCount'] ?? 0),
                'out' => (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0),
                'cache' => (int) ($usage['cachedContentTokenCount'] ?? 0),
            ],
        ];
    }
}
