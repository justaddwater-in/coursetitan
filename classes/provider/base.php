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
 * Base class for LLM providers that return schema-constrained JSON.
 *
 * Each provider sends a system prompt plus a list of chat messages and asks the
 * vendor API to enforce a JSON Schema on the reply (OpenAI strict json_schema,
 * Anthropic output_config.format). The decoded array is returned along with
 * token usage.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var string API key. */
    protected string $apikey;

    /** @var string Model identifier. */
    protected string $model;

    /** @var int Per-request timeout in seconds. */
    protected int $timeout;

    /** @var int Maximum output tokens per request. */
    protected int $maxtokens;

    /** @var callable|null Called periodically while a request is in flight (keeps SSE alive). */
    protected $heartbeat = null;

    /** @var int Number of retries on 429 / 5xx / network errors. */
    protected int $retries = 2;

    /**
     * Constructor.
     *
     * @param string $apikey API key.
     * @param string $model Model identifier.
     * @param int $timeout Per-request timeout in seconds.
     * @param int $maxtokens Maximum output tokens per request.
     */
    public function __construct(string $apikey, string $model, int $timeout = 300, int $maxtokens = 16000) {
        $this->apikey = trim($apikey);
        $this->model = trim($model);
        $this->timeout = max(30, $timeout);
        $this->maxtokens = max(1024, $maxtokens);
    }

    /**
     * Short machine name, e.g. "openai".
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Send one request and return the decoded JSON object.
     *
     * @param string $system System prompt (kept identical across calls so vendors can cache it).
     * @param array $messages List of ['role' => 'user'|'assistant', 'content' => string].
     * @param string $schemaname Short identifier for the schema (a-z, 0-9, _).
     * @param array $schema JSON Schema (object at the root, additionalProperties false everywhere).
     * @return array ['data' => array, 'usage' => ['in' => int, 'out' => int, 'cache' => int]]
     * @throws provider_exception
     */
    abstract public function generate_json(string $system, array $messages, string $schemaname, array $schema): array;

    /**
     * Whether a key and model are present.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return $this->apikey !== '' && $this->model !== '';
    }

    /**
     * Model identifier in use.
     *
     * @return string
     */
    public function get_model(): string {
        return $this->model;
    }

    /**
     * Register a callable invoked roughly every 15 s while waiting on the API.
     *
     * @param callable|null $heartbeat
     */
    public function set_heartbeat(?callable $heartbeat): void {
        $this->heartbeat = $heartbeat;
    }

    /**
     * POST a JSON body and return the decoded JSON response, retrying transient failures.
     *
     * @param string $url Endpoint.
     * @param array $headers HTTP headers (without Content-Type).
     * @param array $body Request body.
     * @return array Decoded response.
     * @throws provider_exception
     */
    protected function post_json(string $url, array $headers, array $body): array {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new provider_exception('Could not encode request: ' . json_last_error_msg());
        }
        $headers[] = 'Content-Type: application/json';

        $attempt = 0;
        while (true) {
            $attempt++;
            [$status, $raw, $errno, $error] = $this->http_post($url, $headers, $payload);

            $transient = $errno > 0 || $status === 429 || $status === 408 || $status >= 500;
            if ($transient && $attempt <= $this->retries) {
                $this->wait(min(30, 3 * $attempt * $attempt));
                continue;
            }

            if ($errno === -1) {
                throw new provider_exception("Moodle blocked the request to {$url}: {$error} Check Site administration > "
                    . 'General > Security > HTTP security (cURL blocked hosts / allowed ports) and DNS on the server.');
            }
            if ($errno !== 0) {
                throw new provider_exception("Network error calling {$this->get_name()} (cURL {$errno}): {$error}");
            }

            $decoded = json_decode((string) $raw, true);
            if ($status < 200 || $status >= 300) {
                $message = $this->extract_error_message($decoded) ?? substr(strip_tags((string) $raw), 0, 300);
                throw new provider_exception("{$this->get_name()} returned HTTP {$status}: {$message}", $status);
            }
            if (!is_array($decoded)) {
                throw new provider_exception("{$this->get_name()} returned a non-JSON response.");
            }
            return $decoded;
        }
    }

    /**
     * Perform the HTTP request. Separated so tests can override it.
     *
     * @param string $url
     * @param array $headers
     * @param string $payload
     * @return array [int status, string body, int curl errno, string curl error]
     */
    protected function http_post(string $url, array $headers, string $payload): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $heartbeat = $this->heartbeat;
        $lastbeat = time();

        $curl = new \curl(['ignoresecurity' => false]);
        $options = [
            'CURLOPT_HTTPHEADER'     => $headers,
            'CURLOPT_TIMEOUT'        => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => 20,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
        ];
        if ($heartbeat) {
            $options['CURLOPT_NOPROGRESS'] = false;
            $options['CURLOPT_PROGRESSFUNCTION'] = function () use ($heartbeat, &$lastbeat) {
                if (time() - $lastbeat >= 15) {
                    $lastbeat = time();
                    $heartbeat();
                }
                return 0;
            };
        }

        $raw = $curl->post($url, $payload, $options);
        $info = $curl->get_info();
        $errno = (int) $curl->get_errno();
        $error = (string) $curl->error;
        if ($errno === 0 && $error !== '') {
            // Moodle's curl security helper refused the URL (Site admin > Security > HTTP security).
            $errno = -1;
        }
        return [(int) ($info['http_code'] ?? 0), (string) $raw, $errno, $error];
    }

    /**
     * Sleep between retries while still sending heartbeats.
     *
     * @param int $seconds
     */
    protected function wait(int $seconds): void {
        for ($i = 0; $i < $seconds; $i++) {
            sleep(1);
            if ($this->heartbeat && $i % 10 === 9) {
                ($this->heartbeat)();
            }
        }
    }

    /**
     * Pull a human-readable message out of a vendor error body.
     *
     * @param mixed $decoded
     * @return string|null
     */
    protected function extract_error_message($decoded): ?string {
        if (is_array($decoded) && isset($decoded['error'])) {
            if (is_array($decoded['error'])) {
                return (string) ($decoded['error']['message'] ?? json_encode($decoded['error']));
            }
            return (string) $decoded['error'];
        }
        return null;
    }

    /**
     * Decode the model's JSON text.
     *
     * @param string $text
     * @return array
     * @throws provider_exception
     */
    protected function decode_output(string $text): array {
        $text = trim($text);
        // Defensive: strip a markdown fence if a model adds one despite schema enforcement.
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);
        }
        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new provider_exception('Model output was not valid JSON: ' . json_last_error_msg(), 0, $text);
        }
        return $data;
    }
}
