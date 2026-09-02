<?php

namespace App\Services\Merchandising\PoParser\TextExtractor;

use App\Exceptions\Merchandising\PoParser\TextExtractionException;
use DOMDocument;
use DOMNode;
use ZipArchive;

/**
 * Reads a `.docx` as fixed-width text lines, in-process.
 *
 * A `.docx` is a zip holding `word/document.xml`; this walks its paragraphs and
 * rebuilds the visual layout, because **column position is the document's only
 * structure** and a plain text dump would destroy it.
 *
 * The tab expansion is the whole trick. Word stores a `<w:tab/>` as an instruction,
 * not as spaces, so each one is replaced with however many spaces reach the next
 * multiple of `po-parser.parsing.default_tab_stop`. Get that arithmetic wrong and
 * every column shifts — the extractors still run and quietly return the wrong cells.
 *
 * Requires `ext-zip` and `ext-dom`.
 */
final class DocxTextExtractor
{
    private const string NAMESPACE_WORDPROCESSING = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @return list<string>
     *
     * @throws TextExtractionException
     */
    public function extract(string $docxPath): array
    {
        $xml = $this->readDocumentXml($docxPath);

        $document = new DOMDocument;
        $document->preserveWhiteSpace = true;

        if (@$document->loadXML($xml, LIBXML_NONET) === false) {
            throw new TextExtractionException('word/document.xml is not valid XML.');
        }

        $lines = [];

        foreach ($document->getElementsByTagNameNS(self::NAMESPACE_WORDPROCESSING, 'p') as $paragraph) {
            foreach ($this->paragraphToLines($paragraph) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @throws TextExtractionException
     */
    private function readDocumentXml(string $docxPath): string
    {
        $archive = new ZipArchive;

        if ($archive->open($docxPath) !== true) {
            throw new TextExtractionException('Cannot open DOCX archive: '.$docxPath);
        }

        $xml = $archive->getFromName('word/document.xml');
        $archive->close();

        if ($xml === false) {
            throw new TextExtractionException('word/document.xml is missing from the DOCX.');
        }

        return $xml;
    }

    /**
     * One Word paragraph becomes one or more lines — `<w:br/>` starts a new one.
     *
     * @return list<string>
     */
    private function paragraphToLines(DOMNode $paragraph): array
    {
        $tabStop = (int) config('po-parser.parsing.default_tab_stop', 8);

        return $this->walk($paragraph, [''], $tabStop);
    }

    /**
     * Append this node's text to the lines built so far.
     *
     * Returned rather than taken by reference: a `&$lines` parameter cannot be
     * described as a non-empty list on the way out as well as in, and the
     * "current line is the last one" invariant below depends on it never emptying.
     *
     * @param  non-empty-list<string>  $lines
     * @return non-empty-list<string>
     */
    private function walk(DOMNode $node, array $lines, int $tabStop): array
    {
        foreach ($node->childNodes as $child) {
            // The line being written is always the last one; `<w:br/>` starts a new
            // one, and nothing ever removes one.
            $last = array_key_last($lines);

            switch ($child->localName) {
                case 't':
                    $lines[$last] .= $child->nodeValue;
                    break;

                case 'tab':
                    $lines[$last] .= $this->padToTabStop($lines[$last], $tabStop);
                    break;

                case 'br':
                    $lines[] = '';
                    break;

                default:
                    if ($child->hasChildNodes()) {
                        $lines = $this->walk($child, $lines, $tabStop);
                    }
            }
        }

        return $lines;
    }

    /**
     * The spaces that carry the current line to the next tab stop.
     *
     * A line already sitting exactly on a stop advances a whole one, which is what
     * a word processor does.
     */
    private function padToTabStop(string $current, int $tabStop): string
    {
        $remainder = $tabStop - (strlen($current) % $tabStop);

        return str_repeat(' ', $remainder !== 0 ? $remainder : $tabStop);
    }
}
