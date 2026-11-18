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
$string['diag_action'] = 'Action';
$string['diag_add_key_to_test'] = 'Add API key to test';
$string['diag_configuration'] = 'Configuration';
$string['diag_configured'] = 'Configured';
$string['diag_individual_hint'] = 'Run a connection test for only the provider you choose. Each test sends a small request and may use API tokens.';
$string['diag_not_tested'] = 'Not tested';
$string['diag_test_provider'] = 'Test {$a}';
$string['diag_test'] = 'Test';
$string['diag_testing'] = 'Testing…';
$string['diag_success'] = 'Success';
$string['diag_failed'] = 'Failed';
$string['diag_response_time'] = 'Response time: {$a} s';
$string['diag_notification_success'] = '{$a} connection test completed successfully.';
$string['diag_notification_failure'] = '{$a} connection test failed. See the result for details.';
$string['invalidprovider'] = 'The selected AI provider is invalid.';
$string['diag_intro'] = 'Check the connection to your configured AI providers using the saved settings.';
$string['diag_model'] = 'Model';
$string['diag_provider'] = 'Provider';
$string['diag_nokey'] = 'No API key';
$string['diag_ok'] = 'Working';
$string['diag_result'] = 'Result';
$string['diag_run'] = 'Run test';
$string['diag_unexpected'] = 'Unexpected response: {$a}';
$string['diagnostics'] = 'Test providers';
$string['generate'] = 'Generate course';
$string['geminikey'] = 'Google Gemini API key';
$string['geminikey_desc'] = 'Create a key in Google AI Studio at aistudio.google.com. Used when the provider is Google Gemini.';
$string['geminimodel'] = 'Gemini model';
$string['geminimodel_desc'] = 'Model ID, for example gemini-3.8-flash. Choose a model that supports structured output.';
$string['generatecourse'] = 'Generate course with AI';
$string['heading_advanced'] = 'Advanced';
$string['heading_course'] = 'Generated courses';
$string['heading_provider'] = 'AI provider';
$string['heading_provider_desc'] = 'CourseTitan calls the provider directly with your own API key. After saving, use <a href="{$a}">Test providers</a> to check the connection.';
$string['instructions'] = 'Standing instructions';
$string['instructions_desc'] = 'Optional guidance added to every generation, for example language, tone, audience or curriculum standards.';
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
$string['privacy:metadata:gemini'] = 'When Google Gemini is the selected provider, source material is sent to the Google Gemini API to generate the course.';
$string['privacy:metadata:anthropic'] = 'When Anthropic is the selected provider, source material is sent to the Anthropic API to generate the course.';
$string['privacy:metadata:openai'] = 'When OpenAI is the selected provider, source material is sent to the OpenAI API to generate the course.';
$string['privacy:metadata:sourcematerial'] = 'Text typed by the user and text extracted from files they upload.';
$string['progress'] = 'Progress';
$string['provider'] = 'Active provider';
$string['provider_anthropic'] = 'Anthropic Claude';
$string['provider_gemini'] = 'Google Gemini';
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
$string['step_build'] = 'Build your course';
$string['step_content'] = 'Write activities';
$string['step_outline'] = 'Design the outline';
$string['timeout'] = 'Request timeout (seconds)';
$string['timeout_desc'] = 'Maximum wait for a single AI response. A course needs one request for the outline and one per activity.';
$string['usingmodel'] = 'Uses {$a}';
$string['source_material_heading'] = 'Course content';
$string['source_material_subheading'] = 'Add source material and choose how you want it generated.';
$string['generation_provider'] = 'AI provider';
$string['default_provider'] = 'default';
$string['selected_model'] = 'Model: {$a}';
$string['sourcetext_hint'] = 'Paste lesson notes, a syllabus, learning objectives or other reference material.';
$string['whatyoullcreate'] = 'What you’ll create';
$string['output_outline'] = 'A structured course outline';
$string['output_outline_desc'] = 'Organised sections based on your source material.';
$string['output_lessons'] = 'Ready-to-review learning content';
$string['output_lessons_desc'] = 'Clear lesson pages with explanations and examples.';
$string['output_assessments'] = 'Activities and assessments';
$string['output_assessments_desc'] = 'Knowledge checks, quizzes and discussion prompts.';
$string['createdvisible'] = 'The generated course will be visible immediately.';
$string['providerunavailable'] = 'The selected AI provider is not configured. Choose a configured provider or ask your administrator to add its API key.';

// Validation messages returned by the semantic output validator.
$string['validation_course_fullname_empty'] = 'The course name is empty.';
$string['validation_sections_required'] = 'The outline must contain at least one section.';
$string['validation_sections_max'] = 'The outline contains {$a->count} sections; the maximum is {$a->maximum}. Merge related topics.';
$string['validation_section_title_empty'] = '{$a} has an empty title.';
$string['validation_section_modules_empty'] = '{$a} has no learning activities. Add at least one page.';
$string['validation_section_modules_max'] = '{$a->label} contains {$a->count} activities; the maximum is {$a->maximum}.';
$string['validation_module_type_invalid'] = '{$a->label} uses the unsupported activity type "{$a->type}". Allowed types: {$a->types}.';
$string['validation_module_title_empty'] = '{$a} has an empty title.';
$string['validation_module_brief_empty'] = '{$a} has no activity description.';
$string['validation_unknown_module_type'] = 'The activity type "{$a}" is not supported.';
$string['validation_forum_intro_empty'] = 'The discussion introduction is empty.';
$string['validation_page_too_short'] = 'The lesson contains only {$a} characters of text. Write a complete lesson with several paragraphs.';
$string['validation_quiz_min_questions'] = 'The quiz contains {$a->count} questions; at least {$a->minimum} are required.';
$string['validation_question_text_empty'] = '{$a} has no question text.';
$string['validation_question_type_invalid'] = '{$a->label} uses the unsupported question type "{$a->type}". Allowed types: {$a->types}.';
$string['validation_option_text_empty'] = '{$a->label}, option {$a->index}: the option text is empty.';
$string['validation_duplicate_option'] = '{$a->label} contains the duplicate answer option "{$a->option}".';
$string['validation_multichoice_options_count'] = '{$a->label} contains {$a->count} answer options. Multiple-choice questions must have between 3 and 6 options.';
$string['validation_correct_option_count'] = '{$a->label} has {$a->count} correct options; exactly one must be correct.';

// Provider and transport errors.
$string['provider_not_configured'] = 'The selected AI provider requires both an API key and a model.';
$string['provider_no_choices'] = 'The AI provider returned no response choices.';
$string['provider_refusal'] = 'The AI provider declined to complete this request.';
$string['provider_refusal_detail'] = 'The AI provider declined to complete this request: {$a}';
$string['provider_output_truncated'] = 'The response was cut off at the output token limit. Increase the maximum output tokens or use shorter source material.';
$string['provider_empty_response'] = 'The AI provider returned an empty response.';
$string['provider_request_encode_failed'] = 'The request could not be encoded: {$a}';
$string['provider_request_blocked'] = 'The site blocked the request to {$a->url}: {$a->error} Check the site security settings for blocked hosts and allowed ports, and verify the server DNS settings.';
$string['provider_network_error'] = 'A network error occurred while contacting {$a->provider} (cURL {$a->code}): {$a->error}';
$string['provider_http_error'] = '{$a->provider} returned HTTP {$a->status}: {$a->message}';
$string['provider_non_json_response'] = '{$a} returned a response that was not valid JSON.';
$string['provider_invalid_json'] = 'The AI response was not valid JSON: {$a}';
$string['gemini_prompt_blocked'] = 'Google Gemini blocked the prompt: {$a}';
$string['gemini_no_candidates'] = 'Google Gemini did not return any response candidates.';
$string['gemini_finish_reason'] = 'Google Gemini stopped generation: {$a}';

// Client-side request feedback.
$string['js_http_error'] = 'The server returned HTTP {$a->status}.';
$string['js_request_failed'] = 'The request could not be completed. Check your connection and try again.';

$string['generation_unexpected_error'] = 'An unexpected error occurred while generating the course. Please try again. If the problem continues, contact your site administrator.';
$string['diag_unexpected_error'] = 'An unexpected error occurred while testing this provider. Check the server logs for details.';
