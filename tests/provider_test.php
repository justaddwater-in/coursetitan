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

namespace local_coursetitan;

use local_coursetitan\provider\anthropic;
use local_coursetitan\provider\factory;
use local_coursetitan\provider\openai;
use local_coursetitan\provider\provider_exception;

/**
 * Tests for request building and response parsing of both providers.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_coursetitan\provider\openai
 * @covers    \local_coursetitan\provider\anthropic
 * @covers    \local_coursetitan\provider\factory
 */
final class provider_test extends \advanced_testcase {
    /**
     * OpenAI request uses strict json_schema and max_completion_tokens.
     */
    public function test_openai_request(): void {
        $p = new openai('key', 'gpt-5-mini', 60, 2000);
        $req = $p->build_request('sys', [['role' => 'user', 'content' => 'hi']], 'ping', schema::ping());
        $this->assertSame('system', $req['messages'][0]['role']);
        $this->assertTrue($req['response_format']['json_schema']['strict']);
        $this->assertSame(2000, $req['max_completion_tokens']);
    }

    /**
     * OpenAI response parsing, including truncation and refusal.
     */
    public function test_openai_parse(): void {
        $p = new openai('key', 'm');
        $r = $p->parse_response(['choices' => [['message' => ['content' => '{"ok":true}'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 2, 'prompt_tokens_details' => ['cached_tokens' => 5]]]);
        $this->assertSame(['ok' => true], $r['data']);
        $this->assertSame(['in' => 10, 'out' => 2, 'cache' => 5], $r['usage']);

        try {
            $p->parse_response(['choices' => [['message' => ['content' => '{"ok":'], 'finish_reason' => 'length']]]);
            $this->fail('Expected exception');
        } catch (provider_exception $e) {
            $this->assertSame('{"ok":', $e->rawoutput);
        }

        $this->expectException(provider_exception::class);
        $p->parse_response(['choices' => [['message' => ['content' => null, 'refusal' => 'no'], 'finish_reason' => 'stop']]]);
    }

    /**
     * Anthropic request uses output_config.format and caches the system prompt.
     */
    public function test_anthropic_request_and_parse(): void {
        $p = new anthropic('key', 'claude-sonnet-5-5', 60, 4000);
        $req = $p->build_request('sys', [['role' => 'user', 'content' => 'hi']], 'ping', schema::ping());
        $this->assertSame('json_schema', $req['output_config']['format']['type']);
        $this->assertSame('ephemeral', $req['system'][0]['cache_control']['type']);
        $this->assertSame(4000, $req['max_tokens']);

        $r = $p->parse_response(['content' => [['type' => 'text', 'text' => '{"ok":true}']], 'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 3, 'cache_creation_input_tokens' => 7, 'output_tokens' => 1,
                'cache_read_input_tokens' => 9]]);
        $this->assertSame(['ok' => true], $r['data']);
        $this->assertSame(['in' => 10, 'out' => 1, 'cache' => 9], $r['usage']);

        $this->expectException(provider_exception::class);
        $p->parse_response(['content' => [], 'stop_reason' => 'max_tokens']);
    }

    /**
     * Factory picks the configured provider and its own key/model.
     */
    public function test_factory(): void {
        $this->resetAfterTest();
        set_config('provider', 'anthropic', 'local_coursetitan');
        set_config('anthropickey', '', 'local_coursetitan');
        $this->assertInstanceOf(anthropic::class, factory::create());
        $this->assertFalse(factory::create()->is_configured());

        set_config('anthropickey', 'abc', 'local_coursetitan');
        set_config('anthropicmodel', 'claude-haiku-5-5', 'local_coursetitan');
        $this->assertTrue(factory::create()->is_configured());
        $this->assertSame('claude-haiku-5-5', factory::create()->get_model());
        $this->assertInstanceOf(openai::class, factory::create('openai'));
    }
}
