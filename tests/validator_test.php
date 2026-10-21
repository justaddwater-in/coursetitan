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

/**
 * Tests for the semantic validator and schema shape.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_coursetitan\validator
 * @covers    \local_coursetitan\schema
 */
final class validator_test extends \advanced_testcase {
    /**
     * A valid outline passes and enum casing is normalised.
     */
    public function test_outline_valid_and_normalised(): void {
        $data = ['course' => ['fullname' => ' Biology ', 'shortname' => 'BIO', 'summary_html' => ''],
            'sections' => [['title' => 'Cells', 'summary_html' => '', 'modules' => [
                ['type' => 'Page', 'title' => 'Intro', 'brief' => 'Cells basics'],
            ]]]];
        $this->assertSame([], validator::outline($data));
        $this->assertSame('page', $data['sections'][0]['modules'][0]['type']);
        $this->assertSame('Biology', $data['course']['fullname']);
    }

    /**
     * Too many sections, empty modules and unknown types are reported.
     */
    public function test_outline_errors(): void {
        $section = ['title' => 'S', 'summary_html' => '', 'modules' => [['type' => 'wiki', 'title' => 'W', 'brief' => 'b']]];
        $data = ['course' => ['fullname' => 'X', 'shortname' => 'X', 'summary_html' => ''],
            'sections' => [$section, $section, ['title' => 'Empty', 'summary_html' => '', 'modules' => []]]];
        $errors = validator::outline($data, 2);
        $this->assertCount(4, $errors);
    }

    /**
     * Quiz must have exactly one correct option per multichoice question.
     */
    public function test_quiz_rules(): void {
        $mc = ['type' => 'multichoice', 'name' => 'Q', 'text_html' => '<p>Q?</p>', 'truefalse_answer' => false,
            'general_feedback' => '', 'options' => [
                ['text' => 'A', 'is_correct' => true, 'feedback' => ''],
                ['text' => 'B', 'is_correct' => true, 'feedback' => ''],
                ['text' => 'C', 'is_correct' => false, 'feedback' => ''],
            ]];
        $tf = ['type' => 'TRUEFALSE', 'name' => 'T', 'text_html' => 'T?', 'truefalse_answer' => true,
            'general_feedback' => '', 'options' => [['text' => 'x', 'is_correct' => true, 'feedback' => '']]];
        $data = ['intro' => 'i', 'questions' => [$mc, $tf, $tf]];
        $errors = validator::module('quiz', $data, 3);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('exactly one must be correct', $errors[0]);
        $this->assertSame([], $data['questions'][1]['options']);
        $this->assertSame('truefalse', $data['questions'][1]['type']);

        $data['questions'][0]['options'][1]['is_correct'] = false;
        $this->assertSame([], validator::module('quiz', $data, 3));
    }

    /**
     * Pages need real content.
     */
    public function test_page_too_short(): void {
        $data = ['intro' => '', 'content_html' => '<p>Short</p>'];
        $this->assertCount(1, validator::module('page', $data));
    }

    /**
     * Every schema object is strict (required = all properties, no additional properties).
     */
    public function test_schemas_are_strict(): void {
        $check = function (array $node) use (&$check): void {
            if (($node['type'] ?? '') === 'object') {
                $this->assertFalse($node['additionalProperties']);
                $this->assertSame(array_keys($node['properties']), $node['required']);
                foreach ($node['properties'] as $child) {
                    $check($child);
                }
            }
            if (($node['type'] ?? '') === 'array') {
                $check($node['items']);
            }
        };
        $check(schema::outline());
        foreach (schema::MODULE_TYPES as $type) {
            $check(schema::module($type));
        }
    }
}
