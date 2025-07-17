<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/PdfStructureRepairer.php';
require_once __DIR__ . '/SmalotPdfParser.php';

use Psr\Log\LoggerInterface;

/**
 * PDF JavaScript Detector
 *
 * Implements the complete workflow for detecting JavaScript in PDF files:
 * 1. First pass: Attempt JS detection via SmalotPdfParser on original PDF
 * 2. On parse failure: Apply repair methods from PdfStructureRepairer
 * 3. Second pass: Re-attempt parsing with SmalotPdfParser on repaired content
 * 4. If the repairing is unsuccessful: Log failure and return false (no JS detected)
 */
class PdfJavaScriptDetector {

    private LoggerInterface $logger;
    private PdfStructureRepairer $repairer;
    private SmalotPdfParser $parser;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
        $this->repairer = new PdfStructureRepairer($logger);
        $this->parser = new SmalotPdfParser();
    }

    /**
     * Detect JavaScript in a PDF file
     *
     * @param string $filePath Path to the PDF file
     * @return bool True if JavaScript is detected, false otherwise
     */
    public function detectJavaScript(string $filePath): bool {
        if (!file_exists($filePath)) {
            $this->logger->error('File not found', ['file' => $filePath]);

            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        // Step 1: First pass - attempt JS detection on the original PDF
        try {
            return $this->parser->validate($filePath);
        } catch (\Throwable $e) {
            $this->logger->warning('Original PDF parsing failed, attempting repair', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);
        }

        // Step 2: On parse failure - apply repair methods
        try {
            $originalContent = file_get_contents($filePath);
            $repairedContent = $this->repairer->repairPdfContent($originalContent);
        } catch (\Throwable $e) {
            $this->logger->error('PDF repair failed', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);

            // If the repair is unsuccessful, we mark the file as not containing JS
            return false;
        }

        // Step 3: Second pass - re-attempt parsing with SmalotPdfParser on repaired content
        try {
            // Create a temporary file with repaired content
            $tempFile = $this->createTempFile($repairedContent);

            try {
                return $this->parser->validate($tempFile);
            } finally {
                // Clean up the temporary file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Repaired PDF parsing failed', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);
        }

        // Step 4: If parsing the repaired PDF is unsuccessful - log failure and return false
        $this->logger->error('JavaScript detection failed - PDF is too corrupted to parse safely', [
            'file' => $filePath
        ]);

        return false;
    }

    /**
     * Create a temporary file with the given content
     */
    private function createTempFile(string $content): string {
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_js_detector_');
        file_put_contents($tempFile, $content);

        return $tempFile;
    }
}
