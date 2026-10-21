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

/**
 * CourseTitan English strings.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['anthropickey'] = 'Anthropic API key';
$string['anthropickey_desc'] = 'Create one at console.anthropic.com. Used when the provider is Anthropic Claude.';
$string['anthropicmodel'] = 'Claude model';
$string['anthropicmodel_desc'] = 'Model ID, for example claude-sonnet-5-5 or claude-opus-5-5. It must support structured outputs.';
$string['builtsummary'] = '{$a->sections} sections, {$a->activities} activities';
$string['category'] = 'Course category';
$string['category_desc'] = 'Default category for generated courses. Teachers can pick any category where they may create courses.';
$string['coursetitan:generate'] = 'Generate courses with CourseTitan';
$string['coursevisible'] = 'Make new courses visible';
$string['coursevisible_desc'] = 'Off (recommended): generated courses stay hidden from students until a teacher reviews and shows them.';
$string['createdhidden'] = 'the course is created hidden';
$string['diag_active'] = 'Active';
$string['diag_intro'] = 'Sends a tiny request to each provider that has an API key, using the saved settings. This uses a few tokens.';
$string['diag_model'] = 'Model';
$string['diag_provider'] = 'Provider';
$string['diag_nokey'] = 'No API key';
$string['diag_ok'] = 'Working';
$string['diag_result'] = 'Result';
$string['diag_run'] = 'Run test';
$string['diag_unexpected'] = 'Unexpected response: {$a}';
$string['diagnostics'] = 'Test providers';
$string['generate'] = 'Generate course';
$string['generatecourse'] = 'Generate course with AI';
$string['heading_advanced'] = 'Advanced';
$string['heading_course'] = 'Generated courses';
$string['heading_provider'] = 'AI provider';
$string['heading_provider_desc'] = 'CourseTitan calls the provider directly with your own API key. After saving, use <a href="{$a}">Test providers</a> to check the connection.';
$string['instructions'] = 'Standing instructions';
$string['instructions_desc'] = 'Optional guidance added to every generation, for example language, tone, audience or curriculum standards.';
$string['intro'] = 'Paste teaching material or upload files. CourseTitan designs an outline, writes pages, quizzes and forums, and builds a Moodle course you can review and edit.';
$string['js_connectionlost'] = 'The connection closed before the course was finished. Check the course list in a minute; if nothing appears, try again.';
$string['js_elapsed'] = 'Elapsed {time}';
$string['js_nosource'] = 'Add some source text or at least one file.';
$string['js_skipped'] = 'Skipped';
$string['js_summary'] = 'Generated with {model} · {tokens}';
$string['js_tokens'] = '{in} tokens in, {out} out, {cached} cached';
$string['maxactivities'] = 'Max activities per section';
$string['maxactivities_desc'] = 'Upper limit on pages, quizzes and forums in one section.';
$string['maxsections'] = 'Max sections';
$string['maxsections_desc'] = 'Upper limit on the number of sections in a generated course.';
$string['maxtokens'] = 'Max output tokens';
$string['maxtokens_desc'] = 'Limit for each AI response. Reasoning models count their thinking against it, so keep it generous.';
$string['nosource'] = 'No usable source material. Add text, or upload a text-based file.';
$string['notconfigured'] = 'CourseTitan needs an AI provider API key before it can generate courses.';
$string['openaikey'] = 'OpenAI API key';
$string['openaikey_desc'] = 'Create one at platform.openai.com. Used when the provider is OpenAI.';
$string['openaimodel'] = 'OpenAI model';
$string['openaimodel_desc'] = 'Model ID, for example gpt-5-mini or gpt-5. It must support Structured Outputs.';
$string['opencourse'] = 'Open course';
$string['outline_failed'] = 'The AI could not produce a usable course outline: {$a}';
$string['pluginname'] = 'CourseTitan';
$string['privacy:metadata:anthropic'] = 'When Anthropic is the selected provider, source material is sent to the Anthropic API to generate the course.';
$string['privacy:metadata:openai'] = 'When OpenAI is the selected provider, source material is sent to the OpenAI API to generate the course.';
$string['privacy:metadata:sourcematerial'] = 'Text typed by the user and text extracted from files they upload.';
$string['progress'] = 'Progress';
$string['provider'] = 'Active provider';
$string['provider_anthropic'] = 'Anthropic Claude';
$string['provider_desc'] = 'Which provider generates courses. Each has its own key and model below.';
$string['provider_openai'] = 'OpenAI';
$string['providererror'] = 'AI provider error: {$a}';
$string['quizquestions'] = 'Questions per quiz';
$string['quizquestions_desc'] = 'How many questions the AI writes for each quiz.';
$string['reviewbeforepublishing'] = 'AI-written content can contain mistakes. Review every activity before students see it.';
$string['settings'] = 'Settings';
$string['sourcefiles'] = 'Source files';
$string['sourcefiles_help'] = 'Optional. Accepted: {$a}. Scanned PDFs have no text and are skipped.';
$string['sourcetext'] = 'Source text';
$string['sourcetext_placeholder'] = 'Lecture notes, a syllabus, an article, a manual…';
$string['sourcetoolarge'] = 'The source material is larger than {$a} of text. Split it into smaller courses.';
$string['step_build'] = 'Build the Moodle course';
$string['step_content'] = 'Write activities';
$string['step_outline'] = 'Design the outline';
$string['timeout'] = 'Request timeout (seconds)';
$string['timeout_desc'] = 'Maximum wait for a single AI response. A course needs one request for the outline and one per activity.';
$string['usingmodel'] = 'Uses {$a}';
