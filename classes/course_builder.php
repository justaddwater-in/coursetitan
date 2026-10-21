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
 * Turns a validated course spec into a real Moodle course using core APIs.
 *
 * No backup (.mbz) file is involved: the course, sections, activities and quiz
 * questions are created directly. All HTML from the model is passed through
 * Moodle's HTML purifier first, because mod_page renders its content with
 * noclean=true.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_builder {
    /**
     * Create the course.
     *
     * @param array $spec Output of generator::run().
     * @param int $categoryid Target course category (0 = site default).
     * @param bool $visible Whether students can see the course immediately.
     * @return \stdClass The new course record.
     */
    public function build(array $spec, int $categoryid = 0, bool $visible = false): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/resourcelib.php');

        if (!$categoryid || !$DB->record_exists('course_categories', ['id' => $categoryid])) {
            $categoryid = \core_course_category::get_default()->id;
        }

        $sections = $spec['sections'];
        $course = create_course((object) [
            'fullname' => \core_text::substr($spec['course']['fullname'], 0, 254),
            'shortname' => $this->unique_shortname($spec['course']['shortname'] ?: $spec['course']['fullname']),
            'category' => $categoryid,
            'summary' => $this->clean($spec['course']['summary_html'] ?? ''),
            'summaryformat' => FORMAT_HTML,
            'format' => 'topics',
            'numsections' => count($sections),
            'visible' => $visible ? 1 : 0,
        ]);
        try {
            course_create_sections_if_missing($course, range(0, count($sections)));

            foreach (array_values($sections) as $i => $section) {
                $sectionnum = $i + 1;
                $record = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum],
                    '*', MUST_EXIST);
                course_update_section($course, $record, [
                    'name' => \core_text::substr($section['title'], 0, 255),
                    'summary' => $this->clean($section['summary_html'] ?? ''),
                    'summaryformat' => FORMAT_HTML,
                ]);
                foreach ($section['modules'] as $module) {
                    $this->add_module($course, $sectionnum, $module);
                }
            }
        } catch (\Throwable $e) {
            // Never leave a half-built course behind.
            delete_course($course, false);
            throw $e;
        }

        rebuild_course_cache($course->id, true);
        return $course;
    }

    /**
     * Add one activity.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param array $module ['type', 'title', 'content']
     */
    private function add_module(\stdClass $course, int $sectionnum, array $module): void {
        $c = $module['content'];
        switch ($module['type']) {
            case 'page':
                $info = $this->moduleinfo($course, $sectionnum, 'page', $module['title'], s($c['intro']));
                $info->content = $this->clean($c['content_html']);
                $info->contentformat = FORMAT_HTML;
                $info->display = RESOURCELIB_DISPLAY_AUTO;
                $info->printintro = 0;
                $info->printlastmodified = 1;
                $info->printheading = 1;
                create_module($info);
                break;

            case 'forum':
                $info = $this->moduleinfo($course, $sectionnum, 'forum', $module['title'], $this->clean($c['intro_html']));
                $info->type = 'general';
                $info->assessed = 0;
                $info->scale = 0;
                $info->grade_forum = 0;
                $info->forcesubscribe = 0;
                create_module($info);
                break;

            case 'quiz':
                $this->add_quiz($course, $sectionnum, $module['title'], $c);
                break;
        }
    }

    /**
     * Create a quiz and its questions.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param string $title
     * @param array $c Quiz content.
     */
    private function add_quiz(\stdClass $course, int $sectionnum, string $title, array $c): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php');

        $info = $this->moduleinfo($course, $sectionnum, 'quiz', $title, s($c['intro']));
        foreach (self::quiz_defaults() as $k => $v) {
            $info->$k = $v;
        }
        $info = create_module($info);

        $quiz = $DB->get_record('quiz', ['id' => $info->instance], '*', MUST_EXIST);
        $quiz->cmid = $info->coursemodule;
        $category = $this->question_category(\context_module::instance($quiz->cmid));

        foreach ($c['questions'] as $q) {
            $questionid = $q['type'] === 'truefalse'
                ? $this->save_truefalse($category, $q)
                : $this->save_multichoice($category, $q);
            quiz_add_quiz_question($questionid, $quiz, 0);
        }
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
    }

    /**
     * Default category for questions, created in the quiz's own context.
     *
     * Works on Moodle 4.2-4.5 and on 5.x, where question banks moved to module contexts.
     *
     * @param \context_module $context
     * @return \stdClass
     */
    private function question_category(\context_module $context): \stdClass {
        $fn = new \ReflectionFunction('question_get_default_category');
        if ($fn->getNumberOfParameters() >= 2) {
            return question_get_default_category($context->id, true);
        }
        return question_make_default_categories([$context]);
    }

    /**
     * Save a single-answer multiple-choice question.
     *
     * @param \stdClass $category
     * @param array $q
     * @return int Question id.
     */
    private function save_multichoice(\stdClass $category, array $q): int {
        $form = $this->question_form($category, $q);
        $form->single = 1;
        $form->shuffleanswers = 1;
        $form->answernumbering = 'abc';
        $form->showstandardinstruction = 0;
        $form->penalty = 0.3333333;
        $form->shownumcorrect = 1;
        $form->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->answer = [];
        $form->fraction = [];
        $form->feedback = [];
        foreach ($q['options'] as $o) {
            $form->answer[] = ['text' => s($o['text']), 'format' => FORMAT_HTML];
            $form->fraction[] = $o['is_correct'] ? '1.0' : '0.0';
            $form->feedback[] = ['text' => s($o['feedback']), 'format' => FORMAT_HTML];
        }
        $form->noanswers = count($form->answer);
        return $this->save_question('multichoice', $form);
    }

    /**
     * Save a true/false question.
     *
     * @param \stdClass $category
     * @param array $q
     * @return int Question id.
     */
    private function save_truefalse(\stdClass $category, array $q): int {
        $form = $this->question_form($category, $q);
        $form->correctanswer = $q['truefalse_answer'] ? '1' : '0';
        $form->feedbacktrue = ['text' => '', 'format' => FORMAT_HTML];
        $form->feedbackfalse = ['text' => '', 'format' => FORMAT_HTML];
        $form->penalty = 1;
        return $this->save_question('truefalse', $form);
    }

    /**
     * Fields common to all question types.
     *
     * @param \stdClass $category
     * @param array $q
     * @return \stdClass
     */
    private function question_form(\stdClass $category, array $q): \stdClass {
        $form = new \stdClass();
        $form->category = $category->id . ',' . $category->contextid;
        $form->name = \core_text::substr($q['name'] ?: strip_tags($q['text_html']), 0, 250);
        $form->questiontext = ['text' => $this->clean($q['text_html']), 'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => $this->clean($q['general_feedback']), 'format' => FORMAT_HTML];
        $form->defaultmark = 1;
        $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        return $form;
    }

    /**
     * Persist a question through its question type.
     *
     * @param string $qtype
     * @param \stdClass $form
     * @return int
     */
    private function save_question(string $qtype, \stdClass $form): int {
        $question = new \stdClass();
        $question->qtype = $qtype;
        $question->createdby = 0;
        $question->idnumber = null;
        $saved = \question_bank::get_qtype($qtype)->save_question($question, $form);
        return (int) $saved->id;
    }

    /**
     * Base moduleinfo object for create_module().
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param string $modname
     * @param string $name
     * @param string $introhtml
     * @return \stdClass
     */
    private function moduleinfo(\stdClass $course, int $sectionnum, string $modname, string $name, string $introhtml): \stdClass {
        return (object) [
            'modulename' => $modname,
            'course' => $course->id,
            'section' => $sectionnum,
            'visible' => 1,
            'visibleoncoursepage' => 1,
            'name' => \core_text::substr($name, 0, 255),
            'introeditor' => ['text' => $introhtml, 'format' => FORMAT_HTML, 'itemid' => 0],
            'showdescription' => 0,
            'cmidnumber' => '',
            'groupmode' => 0,
            'groupingid' => 0,
            'completion' => 0,
        ];
    }

    /**
     * Quiz settings (mirrors Moodle's own defaults: deferred feedback, unlimited attempts, review on).
     *
     * @return array
     */
    public static function quiz_defaults(): array {
        $d = [
            'timeopen' => 0, 'timeclose' => 0, 'timelimit' => 0,
            'preferredbehaviour' => 'deferredfeedback',
            'attempts' => 0, 'attemptonlast' => 0,
            'grademethod' => QUIZ_GRADEHIGHEST,
            'decimalpoints' => 2, 'questiondecimalpoints' => -1,
            'questionsperpage' => 1, 'navmethod' => QUIZ_NAVMETHOD_FREE,
            'shuffleanswers' => 1, 'sumgrades' => 0, 'grade' => 10,
            'overduehandling' => 'autosubmit', 'graceperiod' => 0,
            'quizpassword' => '', 'subnet' => '', 'browsersecurity' => '-',
            'delay1' => 0, 'delay2' => 0, 'showuserpicture' => 0, 'showblocks' => 0,
        ];
        $items = ['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback',
            'rightanswer', 'overallfeedback'];
        foreach (['during', 'immediately', 'open', 'closed'] as $when) {
            foreach ($items as $item) {
                $d[$item . $when] = ($item === 'overallfeedback' && $when === 'during') ? 0 : 1;
            }
        }
        return $d;
    }

    /**
     * Pick a shortname that is not already taken.
     *
     * @param string $base
     * @return string
     */
    private function unique_shortname(string $base): string {
        global $DB;
        $base = \core_text::substr(trim($base), 0, 80);
        $name = $base;
        $i = 2;
        while ($DB->record_exists('course', ['shortname' => $name])) {
            $name = $base . ' (' . $i++ . ')';
        }
        return $name;
    }

    /**
     * Sanitise model HTML.
     *
     * @param string $html
     * @return string
     */
    private function clean(string $html): string {
        return trim(purify_html($html));
    }
}
