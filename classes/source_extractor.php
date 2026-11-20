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
 * Turns uploaded teaching material into plain text for the AI.
 *
 * Supported: txt, md, csv, html/htm, docx, pptx, pdf (text-based PDFs only).
 * Office files are read straight from their XML parts with DOMDocument, so no
 * extra libraries are needed for them; PDFs use the bundled smalot/pdfparser.
 *
 * @package   local_coursetitan
 * @copyright 2026 JustAddWater <https://justaddwater.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_extractor {
    /** @var string[] Extensions accepted by the upload field. */
    const EXTENSIONS = ['txt', 'md', 'csv', 'html', 'htm', 'docx', 'pptx', 'pdf'];

    /** @var int Files larger than this are ignored (bytes). */
    const MAX_FILE_BYTES = 10485760;

    /** Maximum expanded XML size for a DOCX main document. */
    const MAX_DOCX_XML_BYTES = 2097152;

    /** Maximum expanded XML bytes across PPTX slides. */
    const MAX_PPTX_XML_BYTES = 10485760;

    /** Maximum slide count accepted in a PPTX. */
    const MAX_PPTX_SLIDES = 100;

    /** Maximum memory allowed for decompressed PDF streams. */
    const MAX_PDF_DECODE_BYTES = 16777216;

    /** Maximum number of entries accepted in Office ZIP containers. */
    const MAX_ZIP_ENTRIES = 1000;

    /** Maximum total uncompressed size reported by an Office ZIP container. */
    const MAX_ZIP_EXPANDED_BYTES = 52428800; // 50 MB.

    /** Maximum uncompressed size reported for any single ZIP entry. */
    const MAX_ZIP_ENTRY_BYTES = 10485760; // 10 MB.

    /** Maximum time spent inspecting ZIP metadata (seconds). */
    const MAX_ZIP_INSPECTION_SECONDS = 2.0;

    /** Maximum compression ratio for entries larger than 1 MB. */
    const MAX_ZIP_COMPRESSION_RATIO = 1000;

    /** @var string WordprocessingML namespace. */
    const NS_WORD = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** @var string DrawingML namespace (text in PowerPoint slides). */
    const NS_DRAWING = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    /**
     * Extract text from a file.
     *
     * @param string $path Path on disk.
     * @param string $filename Original filename (used for the extension).
     * @return string Plain text ('' when unsupported or unreadable).
     */
    public static function extract(string $path, string $filename): string {
        if (!is_readable($path) || filesize($path) > self::MAX_FILE_BYTES) {
            return '';
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        try {
            switch ($ext) {
                case 'txt':
                case 'md':
                case 'csv':
                    $text = self::to_utf8((string) file_get_contents($path));
                    break;
                case 'html':
                case 'htm':
                    $text = self::from_html((string) file_get_contents($path));
                    break;
                case 'docx':
                    $text = self::from_docx($path);
                    break;
                case 'pptx':
                    $text = self::from_pptx($path);
                    break;
                case 'pdf':
                    $text = self::from_pdf($path);
                    break;
                default:
                    return '';
            }
        } catch (\Throwable $e) {
            debugging('local_coursetitan: could not read ' . $filename . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
        return self::tidy($text);
    }

    /**
     * Convert to UTF-8 if the file uses another encoding.
     *
     * @param string $raw
     * @return string
     */
    private static function to_utf8(string $raw): string {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }
        return \core_text::convert($raw, 'windows-1252', 'utf-8');
    }

    /**
     * Visible text of an HTML document, keeping block structure as line breaks.
     *
     * @param string $html
     * @return string
     */
    private static function from_html(string $html): string {
        $html = self::to_utf8($html);
        $html = preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<(br|/p|/div|/li|/tr|/h[1-6]|/blockquote|/section|/article)\b[^>]*>#i', "$0\n", $html);
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Text of a Word document, one paragraph per line, table cells tab-separated.
     *
     * @param string $path
     * @return string
     */
    private static function from_docx(string $path): string {
        $xml = self::zip_entry($path, 'word/document.xml', self::MAX_DOCX_XML_BYTES);
        if ($xml === null) {
            return '';
        }
        if (strlen($xml) > self::MAX_DOCX_XML_BYTES) {
            throw new \UnexpectedValueException('DOCX document XML exceeds the extraction limit.');
        }
        $dom = self::load_xml($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS_WORD);

        $lines = [];
        foreach ($xpath->query('//w:body//w:p') as $p) {
            $line = '';
            foreach ($xpath->query('.//w:t | .//w:tab | .//w:br', $p) as $node) {
                $line .= $node->localName === 't' ? $node->textContent : ($node->localName === 'tab' ? "\t" : "\n");
            }
            // Paragraphs inside table cells are joined with tabs on one row.
            $incell = $xpath->query('ancestor::w:tc', $p)->length > 0;
            $lastincell = $incell && $xpath->query('following-sibling::w:p', $p)->length === 0;
            $lines[] = $line . ($incell && !$lastincell ? ' ' : '');
        }
        return implode("\n", $lines);
    }

    /**
     * Text of a PowerPoint deck, slide by slide, in slide order.
     *
     * @param string $path
     * @return string
     */
    private static function from_pptx(string $path): string {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        self::validate_zip($zip);
        $slides = [];
        $xmltotal = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                if (count($slides) >= self::MAX_PPTX_SLIDES) {
                    $zip->close();
                    throw new \UnexpectedValueException('PPTX contains too many slides to extract.');
                }
                $stat = $zip->statIndex($i);
                $size = (int) ($stat['size'] ?? 0);
                $xmltotal += $size;
                if ($size <= 0 || $size > self::MAX_DOCX_XML_BYTES || $xmltotal > self::MAX_PPTX_XML_BYTES) {
                    $zip->close();
                    throw new \UnexpectedValueException('PPTX slide XML exceeds the extraction limit.');
                }
                $slides[(int) $m[1]] = $zip->getFromIndex($i);
            }
        }
        $zip->close();
        ksort($slides);

        $out = [];
        foreach ($slides as $num => $xml) {
            $dom = self::load_xml($xml);
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('a', self::NS_DRAWING);
            $paras = [];
            foreach ($xpath->query('//a:p') as $p) {
                $t = '';
                foreach ($xpath->query('.//a:t', $p) as $run) {
                    $t .= $run->textContent;
                }
                if (trim($t) !== '') {
                    $paras[] = $t;
                }
            }
            if ($paras) {
                $out[] = "[Slide {$num}]\n" . implode("\n", $paras);
            }
        }
        return implode("\n\n", $out);
    }

    /**
     * Text of a text-based PDF.
     *
     * @param string $path
     * @return string
     */
    private static function from_pdf(string $path): string {
        require_once(__DIR__ . '/../vendor/autoload.php');
        $config = new \Smalot\PdfParser\Config();
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(self::MAX_PDF_DECODE_BYTES);
        $parser = new \Smalot\PdfParser\Parser([], $config);
        return $parser->parseFile($path)->getText();
    }

    /**
     * Validate ZIP metadata before reading any entry, bounding archive expansion and inspection work.
     * No entries are extracted to disk. The metadata limits also bound the XML data passed to parsers.
     *
     * @param \ZipArchive $zip
     * @return void
     */
    private static function validate_zip(\ZipArchive $zip): void {
        if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
            $zip->close();
            throw new \UnexpectedValueException('Office archive contains too many entries.');
        }
        $started = microtime(true);
        $expanded = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (microtime(true) - $started > self::MAX_ZIP_INSPECTION_SECONDS) {
                $zip->close();
                throw new \UnexpectedValueException('Office archive inspection exceeded the time limit.');
            }
            $stat = $zip->statIndex($i);
            if (!$stat || !isset($stat['size'], $stat['comp_size']) || (int) $stat['size'] < 0 || (int) $stat['comp_size'] < 0) {
                $zip->close();
                throw new \UnexpectedValueException('Office archive contains invalid entry metadata.');
            }
            $size = (int) $stat['size'];
            $compressed = (int) $stat['comp_size'];
            if ($size > self::MAX_ZIP_ENTRY_BYTES) {
                $zip->close();
                throw new \UnexpectedValueException('Office archive entry exceeds the extraction limit.');
            }
            $expanded += $size;
            if ($expanded > self::MAX_ZIP_EXPANDED_BYTES) {
                $zip->close();
                throw new \UnexpectedValueException('Office archive expanded size exceeds the extraction limit.');
            }
            if ($size > 1048576 && ($compressed === 0 || $size / $compressed > self::MAX_ZIP_COMPRESSION_RATIO)) {
                $zip->close();
                throw new \UnexpectedValueException('Office archive contains a suspicious compression ratio.');
            }
        }
    }

    /**
     * Read one entry from a zip archive.
     *
     * @param string $path
     * @param string $entry
     * @param int $maxbytes Maximum extracted bytes for this entry.
     * @return string|null
     */
    private static function zip_entry(string $path, string $entry, int $maxbytes): ?string {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        self::validate_zip($zip);
        $stat = $zip->statName($entry);
        if (!$stat || (int) ($stat['size'] ?? 0) <= 0 || (int) ($stat['size'] ?? 0) > $maxbytes) {
            $zip->close();
            throw new \UnexpectedValueException('Document XML entry exceeds the extraction limit.');
        }
        $data = $zip->getFromName($entry);
        $zip->close();
        return $data === false ? null : $data;
    }

    /**
     * Parse XML safely (no network, no entity expansion).
     *
     * @param string $xml
     * @return \DOMDocument
     */
    private static function load_xml(string $xml): \DOMDocument {
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$loaded) {
            throw new \UnexpectedValueException('Invalid XML in uploaded document.');
        }
        return $dom;
    }

    /**
     * Normalise whitespace and line endings.
     *
     * @param string $text
     * @return string
     */
    private static function tidy(string $text): string {
        $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        $text = preg_replace("/^ +/m", '', $text);
        $text = preg_replace("/ {2,}/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}
