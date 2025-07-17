<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Smalot\PdfParser\Document;
use Smalot\PdfParser\Exception\EmptyPdfException;
use Smalot\PdfParser\Exception\MissingPdfHeaderException;
use Smalot\PdfParser\RawData\RawDataParser;

/**
 * Custom Raw Data Parser extending Smalot's RawDataParser
 */
class CustomRawDataParser extends RawDataParser
{
    /**
     * Parses PDF data and returns extracted data as array.
     *
     * @throws EmptyPdfException
     * @throws MissingPdfHeaderException
     * @throws Exception
     */
    public function hasMatchingObject(string $data, callable $callback): bool
    {
        $xref = $this->getXrefDataObjects($data);

        foreach ($xref['xref'] as $obj => $offset) {
            if ($offset > 0) {
                $objectContent = $this->getRawObjectContent($data, $offset);
                if ($this->mightContainJavaScript($objectContent)) {
                    // Only now call the expensive parsing
                    $objectStructure = $this->getIndirectObject($data, $xref, $obj, $offset, true);
                    if ($callback((string)$obj, $objectStructure, new Document())) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Quick check if raw object content might contain JavaScript
     */
    private function mightContainJavaScript(string $content): bool
    {
        // Look for JS indicators
        return (
            str_contains($content, '/JS')
            || str_contains($content, '/JavaScript')
            || str_contains($content, '/OpenAction')
            || str_contains($content, '/AA')
            || str_contains($content, '/Action')
            || str_contains($content, 'javascript:')
            || str_contains($content, 'alert')
            || str_contains($content, 'window')
        );
    }

    /**
     * Get raw object content without full parsing
     */
    private function getRawObjectContent(string $pdfData, int $offset): string
    {
        // Find object start and end
        $start = $offset;
        $endPos = strpos($pdfData, 'endobj', $start);

        if ($endPos === false) {
            return '';
        }

        return substr($pdfData, $start, $endPos - $start + 6);
    }

    /**
     * Get the xref data objects in the same way as the original RawDataParser
     *
     * @throws EmptyPdfException
     * @throws MissingPdfHeaderException
     * @throws Exception
     */
    protected function getXrefDataObjects(string $data): array
    {
        if (empty($data)) {
            throw new EmptyPdfException('Empty PDF data given.');
        }
        // find the PDF header starting position
        if (false === ($trimpos = strpos($data, '%PDF-'))) {
            throw new MissingPdfHeaderException('Invalid PDF data: Missing `%PDF-` header.');
        }

        // get PDF content string
        $pdfData = $trimpos > 0 ? substr($data, $trimpos) : $data;

        // get xref and trailer data
        $xref = $this->getXrefData($pdfData);

        // If we found Unix line-endings
        if (isset($xref['Unix'])) {
            $pdfData = str_replace("\r\n", "\n", $pdfData);
            $xref = $this->getXrefData($pdfData);
        }

        return $xref;
    }
}
