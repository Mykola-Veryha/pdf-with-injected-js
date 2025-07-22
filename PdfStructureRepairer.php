<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Psr\Log\LoggerInterface;

/**
 * PDF Repair Utility
 *
 * Implements targeted PDF repair functionality for common structural issues.
 * Not a comprehensive repair solution - for more complex cases, use a dedicated
 * library like qpdf.
 *
 * Implementation workflow:
 * 1. First pass: Attempt JS detection via SmalotPdfParser on original PDF
 * 2. On parse failure: Apply repair methods from this class
 * 3. Second pass: Re-attempt parsing with SmalotPdfParser on repaired content
 * 4. If the repairing is unsuccessful: Log failure and return false (no JS detected)
 */
class PdfStructureRepairer
{

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Repair PDF content and return the repaired version.
     *
     * @throws \Throwable
     */
    public function repairPdfContent(string $content): string
    {
        try {
            $content = $this->repairMissingEndobj($content);
            $content = $this->repairPdfHeader($content);
            $content = $this->repairXrefTable($content);

            return $this->repairStartxref($content);
        } catch (\Throwable $e) {
            $this->logger->error('PDF repair failed', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Repair the PDF header if missing or corrupted
     */
    private function repairPdfHeader(string $content): string
    {
        if (!str_starts_with($content, '%PDF-')) {
            $content = "%PDF-1.4\n" . $content;
        }

        return $content;
    }

    /**
     * Repair xref table if corrupted or missing
     */
    private function repairXrefTable(string $content): string
    {
        $xrefPos = $this->findLastXrefPosition($content);

        if ($xrefPos === false || !$this->isValidXrefTable(substr($content, $xrefPos))) {
            $bodyContent = $xrefPos !== false ? substr($content, 0, $xrefPos) : $content;
            $objects = $this->findAllObjects($bodyContent);

            if (!empty($objects)) {
                $xref = $this->buildXrefTable($objects);
                $trailer = $this->buildTrailer($objects);
                $startxref = "startxref\n" . strlen($bodyContent) . "\n%%EOF\n";

                return $bodyContent . $xref . $trailer . $startxref;
            }
        }

        return $content;
    }

    /**
     * Find the last occurrence of xref in content
     */
    private function findLastXrefPosition(string $content): int|false
    {
        $pos = -1;
        $searchPos = 0;

        while (($found = strpos($content, 'xref', $searchPos)) !== false) {
            $pos = $found;
            $searchPos = $found + 1;
        }

        return $pos >= 0 ? $pos : false;
    }

    /**
     * Check if the xref table appears valid
     */
    private function isValidXrefTable(string $xrefContent): bool
    {
        return str_contains($xrefContent, 'xref') &&
            str_contains($xrefContent, 'trailer') &&
            str_contains($xrefContent, 'startxref');
    }

    /**
     * Find all PDF objects in content
     */
    private function findAllObjects(string $content): array
    {
        $objects = [];
        $lines = explode("\n", $content);
        $currentObject = null;

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);

            if ($this->isObjectStartLine($line)) {
                $objectInfo = $this->parseObjectStartLine($line);
                if ($objectInfo !== null) {
                    $currentObject = [
                        'id' => $objectInfo['id'],
                        'generation' => $objectInfo['generation'],
                        'start_pos' => $this->getLinePosition($content, $lineNum)
                    ];
                }
            }

            if ($line === 'endobj' && $currentObject !== null) {
                $currentObject['end_pos'] = $this->getLinePosition($content, $lineNum) + strlen($line);
                $objects[$currentObject['id']] = $currentObject;
                $currentObject = null;
            }
        }

        return $objects;
    }

    /**
     * Check if the line is an object start line
     */
    private function isObjectStartLine(string $line): bool
    {
        $parts = explode(' ', trim($line));

        return count($parts) === 3 && is_numeric($parts[0]) && is_numeric($parts[1]) && $parts[2] === 'obj';
    }

    /**
     * Parse object start line to get ID and generation
     */
    private function parseObjectStartLine(string $line): ?array
    {
        $parts = explode(' ', trim($line));

        return count($parts) === 3
            ? ['id' => (int)$parts[0], 'generation' => (int)$parts[1]]
            : null;
    }

    /**
     * Get position of line in content
     */
    private function getLinePosition(string $content, int $lineNum): int
    {
        $lines = explode("\n", $content);
        $pos = 0;

        for ($i = 0; $i < $lineNum; $i++) {
            $pos += strlen($lines[$i]) + 1;
        }

        return $pos;
    }

    /**
     * Build xref table from objects
     */
    private function buildXrefTable(array $objects): string
    {
        if (empty($objects)) {
            return "xref\n0 1\n0000000000 65535 f \n";
        }

        $maxId = max(array_keys($objects));
        $xref = "xref\n0 " . ($maxId + 1) . "\n";

        for ($i = 0; $i <= $maxId; $i++) {
            if (isset($objects[$i])) {
                $offset = $objects[$i]['start_pos'];
                $xref .= sprintf("%010d %05d n \n", $offset, $objects[$i]['generation']);
            } else {
                $xref .= sprintf("%010d %05d f \n", 0, 0);
            }
        }

        return $xref;
    }

    /**
     * Build trailer dictionary
     */
    private function buildTrailer(array $objects): string
    {
        if (empty($objects)) {
            return "trailer\n<<\n/Size 1\n/Root 1 0 R\n>>\n";
        }
        $maxId = max(array_keys($objects));

        return "trailer\n<<\n/Size " . ($maxId + 1) . "\n/Root 1 0 R\n>>\n";
    }

    /**
     * Repair the startxref if missing
     */
    private function repairStartxref(string $content): string
    {
        if (!str_contains($content, 'startxref')) {
            $xrefPos = $this->findLastXrefPosition($content);
            if ($xrefPos !== false) {
                $content .= "startxref\n" . $xrefPos . "\n%%EOF\n";
            }
        }

        return $content;
    }

    /**
     * Repair missing endobj for objects that are not properly closed.
     * Only edits the broken part, does not break other parts.
     */
    private function repairMissingEndobj(string $content): string
    {
        // Regex to find all object starts
        $pattern = '/(\d+ \d+ obj\b)/';
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
        $object_starts = $matches[0];

        $insertions = [];
        $object_count = count($object_starts);
        for ($i = 0; $i < $object_count; $i++) {
            $start_offset = (int) $object_starts[$i][1];
            $next_offset = isset($object_starts[$i + 1]) ? $object_starts[$i + 1][1] : null;

            // Find the end of this object (either next object, or trailer/xref/startxref, or end of a file)
            if ($next_offset === null) {
                // Last object: search for trailer/xref/startxref or end of a file
                $end_search = preg_match(
                    '/\b(trailer|xref|startxref|%%EOF)\b/',
                    $content,
                    $end_match,
                    PREG_OFFSET_CAPTURE,
                    $start_offset
                );
                $end_offset = $end_search ? $end_match[0][1] : strlen($content);
            } else {
                $end_offset = $next_offset;
            }

            $object_body = substr($content, $start_offset, $end_offset - $start_offset);
            if (!str_contains($object_body, 'endobj')) {
                // Insert endobj just before the next object/trailer/xref/startxref/EOF
                $insertions[] = [
                    'pos' => $end_offset,
                    'text' => "\nendobj\n"
                ];
            }
        }

        // Apply insertions in reverse order so offsets don't shift
        foreach (array_reverse($insertions) as $ins) {
            $content = substr($content, 0, $ins['pos']) . $ins['text'] . substr($content, $ins['pos']);
        }

        return $content;
    }
}
