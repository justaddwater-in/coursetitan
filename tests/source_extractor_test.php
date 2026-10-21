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
 * Tests for source_extractor. Office files are built on the fly so no binary fixtures are needed.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_coursetitan\source_extractor
 */
final class source_extractor_test extends \advanced_testcase {
    /** @var string Temp directory. */
    private string $dir;

    protected function setUp(): void {
        parent::setUp();
        $this->dir = make_request_directory();
    }

    /**
     * Write a file and return its path.
     *
     * @param string $name
     * @param string $content
     * @return string
     */
    private function file(string $name, string $content): string {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Build a zip with the given entries.
     *
     * @param string $name
     * @param array $entries path => content
     * @return string
     */
    private function zip(string $name, array $entries): string {
        $path = $this->dir . '/' . $name;
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $entry => $content) {
            $zip->addFromString($entry, $content);
        }
        $zip->close();
        return $path;
    }

    public function test_plain_text_and_bom(): void {
        $path = $this->file('notes.txt', "\xEF\xBB\xBFLine one\r\n\r\n\r\n\r\nLine two  \n");
        $this->assertSame("Line one\n\nLine two", source_extractor::extract($path, 'notes.txt'));
    }

    public function test_latin1_text_is_converted(): void {
        $path = $this->file('old.txt', "Caf\xE9");
        $this->assertSame('Café', source_extractor::extract($path, 'old.txt'));
    }

    public function test_html_drops_scripts_and_keeps_blocks(): void {
        $path = $this->file('page.html', '<h1>Title</h1><script>evil()</script><p>A &amp; B</p><p>C</p>');
        $this->assertSame("Title\nA & B\nC", source_extractor::extract($path, 'page.HTML'));
    }

    public function test_docx_paragraphs_and_tables(): void {
        $w = 'xmlns:w="' . source_extractor::NS_WORD . '"';
        $xml = "<w:document $w><w:body>"
            . '<w:p><w:r><w:t>Hello</w:t></w:r><w:r><w:tab/><w:t>world</w:t></w:r></w:p>'
            . '<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Cell A</w:t></w:r></w:p></w:tc>'
            . '<w:tc><w:p><w:r><w:t>Cell B</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
            . '</w:body></w:document>';
        $path = $this->zip('doc.docx', ['word/document.xml' => $xml]);
        $text = source_extractor::extract($path, 'doc.docx');
        $this->assertStringContainsString("Hello\tworld", $text);
        $this->assertStringContainsString('Cell A', $text);
        $this->assertStringContainsString('Cell B', $text);
    }

    public function test_pptx_slides_in_numeric_order(): void {
        $slide = fn($t) => '<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="'
            . source_extractor::NS_DRAWING . '"><a:p><a:r><a:t>' . $t . '</a:t></a:r></a:p></p:sld>';
        $path = $this->zip('deck.pptx', [
            'ppt/slides/slide10.xml' => $slide('Ten'),
            'ppt/slides/slide2.xml' => $slide('Two'),
        ]);
        $this->assertSame("[Slide 2]\nTwo\n\n[Slide 10]\nTen", source_extractor::extract($path, 'deck.pptx'));
    }

    public function test_unsupported_and_corrupt_files(): void {
        $this->assertSame('', source_extractor::extract($this->file('x.exe', 'MZ'), 'x.exe'));
        $this->assertSame('', source_extractor::extract($this->file('bad.docx', 'not a zip'), 'bad.docx'));
        $this->assertSame('', source_extractor::extract($this->file('bad.pdf', 'not a pdf'), 'bad.pdf'));
        $this->assertDebuggingCalled();
    }
}
