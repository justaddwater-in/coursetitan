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
 * Master JSON Schemas for the course template.
 *
 * These are the "templates" the LLM must fill. They describe teaching content
 * only; Moodle internals (ids, formats, grading settings) are added later by
 * course_builder. Every schema obeys the strictest common subset of OpenAI
 * strict mode and Anthropic structured outputs: all properties required,
 * additionalProperties false, no numeric/length constraints. Counts and other
 * semantic rules are enforced by validator instead.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schema {
    /** @var string[] Module types supported in this version. */
    const MODULE_TYPES = ['page', 'quiz', 'forum'];

    /** @var string[] Question types supported in this version. */
    const QUESTION_TYPES = ['multichoice', 'truefalse'];

    /**
     * Outline: course metadata, sections, and the module list per section.
     *
     * @return array
     */
    public static function outline(): array {
        return self::obj([
            'course' => self::obj([
                'fullname' => self::str('Full course title.'),
                'shortname' => self::str('Short course name, max 30 characters.'),
                'summary_html' => self::str('Course description as simple HTML (2-4 sentences).'),
            ]),
            'sections' => self::arr(self::obj([
                'title' => self::str('Section (topic) title.'),
                'summary_html' => self::str('One or two sentences describing the section, as simple HTML.'),
                'modules' => self::arr(self::obj([
                    'type' => ['type' => 'string', 'enum' => self::MODULE_TYPES],
                    'title' => self::str('Activity title shown on the course page.'),
                    'brief' => self::str('What this activity must cover: key concepts, facts and learning '
                        . 'objectives drawn from the source material. 2-5 sentences.'),
                ])),
            ])),
        ]);
    }

    /**
     * Schema for one module's content.
     *
     * @param string $type One of MODULE_TYPES.
     * @return array
     */
    public static function module(string $type): array {
        switch ($type) {
            case 'page':
                return self::obj([
                    'intro' => self::str('One-sentence description of the page.'),
                    'content_html' => self::str('Full lesson content as HTML. Use h3/h4, p, ul/ol, li, strong, em, '
                        . 'table, blockquote. No scripts, styles, images or external links.'),
                ]);
            case 'quiz':
                return self::obj([
                    'intro' => self::str('One or two sentences telling learners what the quiz covers.'),
                    'questions' => self::arr(self::obj([
                        'type' => ['type' => 'string', 'enum' => self::QUESTION_TYPES],
                        'name' => self::str('Short internal name for the question bank.'),
                        'text_html' => self::str('Question text as simple HTML.'),
                        'options' => self::arr(self::obj([
                            'text' => self::str('Answer option text (plain text).'),
                            'is_correct' => ['type' => 'boolean'],
                            'feedback' => self::str('Feedback shown when this option is chosen.'),
                        ]), 'multichoice: 3-5 options with exactly one correct. truefalse: empty array.'),
                        'truefalse_answer' => ['type' => 'boolean',
                            'description' => 'truefalse: the correct answer. multichoice: ignored, use false.'],
                        'general_feedback' => self::str('Explanation shown after answering.'),
                    ])),
                ]);
            case 'forum':
                return self::obj([
                    'intro_html' => self::str('Discussion prompt as HTML: the question(s) learners should '
                        . 'discuss and what a good post includes.'),
                ]);
        }
        throw new \coding_exception('Unknown module type: ' . $type);
    }

    /**
     * Tiny schema used by the connection test.
     *
     * @return array
     */
    public static function ping(): array {
        return self::obj(['ok' => ['type' => 'boolean']]);
    }

    /**
     * Strict object: every property required, no extras.
     *
     * @param array $properties
     * @return array
     */
    private static function obj(array $properties): array {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * String property with a description.
     *
     * @param string $description
     * @return array
     */
    private static function str(string $description): array {
        return ['type' => 'string', 'description' => $description];
    }

    /**
     * Array property.
     *
     * @param array $items
     * @param string|null $description
     * @return array
     */
    private static function arr(array $items, ?string $description = null): array {
        $a = ['type' => 'array', 'items' => $items];
        if ($description !== null) {
            $a['description'] = $description;
        }
        return $a;
    }
}
