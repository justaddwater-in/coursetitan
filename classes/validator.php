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
 * Semantic validation of LLM output that a JSON Schema cannot express.
 *
 * Each method normalises the data in place (trims strings, lower-cases enum
 * values, since Claude may change their case) and returns a list of
 * human-readable problems. An empty list means the data is usable. The
 * problems are sent back to the model verbatim in the repair prompt.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator {
    /** @var int Minimum visible text length for a page. */
    const MIN_PAGE_TEXT = 200;

    /**
     * Validate the course outline.
     *
     * @param array $data Decoded outline (modified in place).
     * @param int $maxsections Maximum sections allowed.
     * @param int $maxmodules Maximum modules per section.
     * @return string[] Problems found.
     */
    public static function outline(array &$data, int $maxsections = 8, int $maxmodules = 5): array {
        $errors = [];
        $data['course']['fullname'] = self::text($data['course']['fullname'] ?? '');
        $data['course']['shortname'] = self::text($data['course']['shortname'] ?? '');
        $data['course']['summary_html'] = trim((string) ($data['course']['summary_html'] ?? ''));
        if ($data['course']['fullname'] === '') {
            $errors[] = 'course.fullname is empty.';
        }

        $sections = $data['sections'] ?? [];
        if (!is_array($sections) || count($sections) === 0) {
            return array_merge($errors, ['sections must contain at least one section.']);
        }
        if (count($sections) > $maxsections) {
            $errors[] = 'There are ' . count($sections) . " sections; the maximum is {$maxsections}. Merge related topics.";
        }

        foreach ($sections as $si => &$section) {
            $label = 'sections[' . $si . ']';
            $section['title'] = self::text($section['title'] ?? '');
            $section['summary_html'] = trim((string) ($section['summary_html'] ?? ''));
            if ($section['title'] === '') {
                $errors[] = "{$label}.title is empty.";
            }
            $modules = $section['modules'] ?? [];
            if (!is_array($modules) || count($modules) === 0) {
                $errors[] = "{$label} has no modules; add at least one page.";
                continue;
            }
            if (count($modules) > $maxmodules) {
                $errors[] = "{$label} has " . count($modules) . " modules; the maximum is {$maxmodules}.";
            }
            foreach ($modules as $mi => &$module) {
                $mlabel = "{$label}.modules[{$mi}]";
                $module['type'] = strtolower(trim((string) ($module['type'] ?? '')));
                $module['title'] = self::text($module['title'] ?? '');
                $module['brief'] = trim((string) ($module['brief'] ?? ''));
                if (!in_array($module['type'], schema::MODULE_TYPES, true)) {
                    $errors[] = "{$mlabel}.type '{$module['type']}' is not one of: " . implode(', ', schema::MODULE_TYPES) . '.';
                }
                if ($module['title'] === '') {
                    $errors[] = "{$mlabel}.title is empty.";
                }
                if ($module['brief'] === '') {
                    $errors[] = "{$mlabel}.brief is empty.";
                }
            }
            unset($module);
            $section['modules'] = array_values($modules);
        }
        unset($section);
        $data['sections'] = array_values($sections);

        return $errors;
    }

    /**
     * Validate one module's content.
     *
     * @param string $type Module type.
     * @param array $data Decoded content (modified in place).
     * @param int $minquestions Minimum questions for a quiz.
     * @return string[] Problems found.
     */
    public static function module(string $type, array &$data, int $minquestions = 3): array {
        switch ($type) {
            case 'page':
                return self::page($data);
            case 'quiz':
                return self::quiz($data, $minquestions);
            case 'forum':
                $data['intro_html'] = trim((string) ($data['intro_html'] ?? ''));
                return $data['intro_html'] === '' ? ['intro_html is empty.'] : [];
        }
        return ["Unknown module type '{$type}'."];
    }

    /**
     * Page rules.
     *
     * @param array $data
     * @return string[]
     */
    private static function page(array &$data): array {
        $data['intro'] = self::text($data['intro'] ?? '');
        $data['content_html'] = trim((string) ($data['content_html'] ?? ''));
        $visible = trim(html_entity_decode(strip_tags($data['content_html'])));
        if (\core_text::strlen($visible) < self::MIN_PAGE_TEXT) {
            return ['content_html is too short (' . \core_text::strlen($visible) . ' characters of text); '
                . 'write a complete lesson of at least several paragraphs.'];
        }
        return [];
    }

    /**
     * Quiz rules.
     *
     * @param array $data
     * @param int $minquestions
     * @return string[]
     */
    private static function quiz(array &$data, int $minquestions): array {
        $errors = [];
        $data['intro'] = self::text($data['intro'] ?? '');
        $questions = $data['questions'] ?? [];
        if (!is_array($questions) || count($questions) < $minquestions) {
            $errors[] = 'The quiz has ' . (is_array($questions) ? count($questions) : 0)
                . " questions; at least {$minquestions} are required.";
            if (!is_array($questions)) {
                return $errors;
            }
        }

        foreach ($questions as $qi => &$q) {
            $label = "questions[{$qi}]";
            $q['type'] = strtolower(trim((string) ($q['type'] ?? '')));
            $q['name'] = self::text($q['name'] ?? '');
            $q['text_html'] = trim((string) ($q['text_html'] ?? ''));
            $q['general_feedback'] = trim((string) ($q['general_feedback'] ?? ''));
            $q['truefalse_answer'] = (bool) ($q['truefalse_answer'] ?? false);
            if (trim(strip_tags($q['text_html'])) === '') {
                $errors[] = "{$label}.text_html is empty.";
            }
            if (!in_array($q['type'], schema::QUESTION_TYPES, true)) {
                $errors[] = "{$label}.type '{$q['type']}' is not one of: " . implode(', ', schema::QUESTION_TYPES) . '.';
                continue;
            }
            if ($q['type'] !== 'multichoice') {
                $q['options'] = [];
                continue;
            }

            $options = is_array($q['options'] ?? null) ? array_values($q['options']) : [];
            $correct = 0;
            $seen = [];
            foreach ($options as $oi => &$o) {
                $o['text'] = self::text($o['text'] ?? '');
                $o['feedback'] = trim((string) ($o['feedback'] ?? ''));
                $o['is_correct'] = (bool) ($o['is_correct'] ?? false);
                if ($o['text'] === '') {
                    $errors[] = "{$label}.options[{$oi}].text is empty.";
                }
                $key = \core_text::strtolower($o['text']);
                if (isset($seen[$key])) {
                    $errors[] = "{$label} has duplicate option '{$o['text']}'.";
                }
                $seen[$key] = true;
                $correct += $o['is_correct'] ? 1 : 0;
            }
            unset($o);
            $q['options'] = $options;
            if (count($options) < 3 || count($options) > 6) {
                $errors[] = "{$label} has " . count($options) . ' options; multichoice needs 3 to 5.';
            }
            if ($correct !== 1) {
                $errors[] = "{$label} has {$correct} correct options; exactly one must be correct.";
            }
        }
        unset($q);
        $data['questions'] = array_values($questions);
        return $errors;
    }

    /**
     * Normalise a plain-text field.
     *
     * @param mixed $value
     * @return string
     */
    private static function text($value): string {
        return trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
    }
}
