<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PdfJavaScriptDetector;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @coversDefaultClass \PdfJavaScriptDetector
 */
class PdfJavaScriptDetectorTest extends TestCase
{
    private PdfJavaScriptDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../PdfJavaScriptDetector.php';
        $this->detector = new PdfJavaScriptDetector(new NullLogger());
    }

    #[DataProvider('pdfFileProvider')]
    public function testPdfJavaScriptDetection(string $filename, bool $expected): void
    {
        if (!file_exists($filename)) {
            $this->markTestSkipped("Test file not found: {$filename}");
        }

        try {
            $detected = $this->detector->detectJavaScript($filename);
            $this->assertEquals(
                $expected,
                $detected,
                "JavaScript detection failed for {$filename}. Expected: " . ($expected ? 'YES' : 'NO') . ", Got: " . ($detected ? 'YES' : 'NO')
            );
        } catch (\Exception $e) {
            $this->fail("Exception occurred while testing {$filename}: " . $e->getMessage() . $e->getTraceAsString());
        } catch (\Throwable $e) {
            $this->fail("Fatal error occurred while testing {$filename}: " . $e->getMessage());
        }
    }

    /**
     * Data provider for PDF test files
     */
    public static function pdfFileProvider(): array
    {
        return [
            // Valid PDFs (no JavaScript)
            'valid--1.pdf' => ['tests/Data/valid--1.pdf', false],
            'valid--2.pdf' => ['tests/Data/valid--2.pdf', false],
            'valid--with-startxref-warning.pdf' => ['tests/Data/valid--with-startxref-warning.pdf', false],
            'valid-has-non-array-object.pdf' => ['tests/Data/valid-has-non-array-object.pdf', false],

            // New valid PDFs from different versions (no JavaScript)
            'pdf_version_1_3_reference.pdf' => ['tests/Data/valid/pdf_version_1_3_reference.pdf', false],
            'pdf_version_1_3_sample_unec.pdf' => ['tests/Data/valid/pdf_version_1_3_sample_unec.pdf', false],
            'pdf_version_1_4_reference.pdf' => ['tests/Data/valid/pdf_version_1_4_reference.pdf', false],
            'pdf_version_1_4_reference_2.pdf' => ['tests/Data/valid/pdf_version_1_4_reference_2.pdf', false],
            'pdf_version_1_4_sample_w3.pdf' => ['tests/Data/valid/pdf_version_1_4_sample_w3.pdf', false],
            'pdf_version_1_5_reference.pdf' => ['tests/Data/valid/pdf_version_1_5_reference.pdf', false],
            'pdf_version_1_5_sample_learning.pdf' => ['tests/Data/valid/pdf_version_1_5_sample_learning.pdf', false],
            'pdf_version_1_6_iso_32000.pdf' => ['tests/Data/valid/pdf_version_1_6_iso_32000.pdf', false],
            'pdf_version_1_6_mit_reference.pdf' => ['tests/Data/valid/pdf_version_1_6_mit_reference.pdf', false],
            'pdf_version_1_7_sample_freetestdata.pdf' => ['tests/Data/valid/pdf_version_1_7_sample_freetestdata.pdf', false],

            // Invalid PDFs (contain JavaScript)
            'invalid--has-javascript-action-1.pdf' => ['tests/Data/invalid--has-javascript-action-1.pdf', true],
            'invalid--has-javascript-action.pdf' => ['tests/Data/invalid--has-javascript-action.pdf', true],
            'invalid--has-javascript-in-open-action.pdf' => ['tests/Data/invalid--has-javascript-in-open-action.pdf', true],
            'invalid--has-javascript-in-uri-1.pdf' => ['tests/Data/invalid--has-javascript-in-uri-1.pdf', true],
            'invalid--has-javascript-in-uri.pdf' => ['tests/Data/invalid--has-javascript-in-uri.pdf', true],
            'invalid--has-javascript.pdf' => ['tests/Data/invalid--has-javascript.pdf', true],
            'invalid--without-start-xref.pdf' => ['tests/Data/invalid--without-start-xref.pdf', true],

            // PayloadsAllThePDFs directory - all contain injected JavaScript
            'payload1.pdf' => ['tests/Data/PayloadsAllThePDFs/payload1.pdf', true],
            'payload2.pdf' => ['tests/Data/PayloadsAllThePDFs/payload2.pdf', true],
            'payload3.pdf' => ['tests/Data/PayloadsAllThePDFs/payload3.pdf', true],
            'payload4.pdf' => ['tests/Data/PayloadsAllThePDFs/payload4.pdf', true],
            'payload5.pdf' => ['tests/Data/PayloadsAllThePDFs/payload5.pdf', true],
            'payload6.pdf' => ['tests/Data/PayloadsAllThePDFs/payload6.pdf', true],
            'payload7.pdf' => ['tests/Data/PayloadsAllThePDFs/payload7.pdf', true],
            'payload8.pdf' => ['tests/Data/PayloadsAllThePDFs/payload8.pdf', true],
            'payload9.pdf' => ['tests/Data/PayloadsAllThePDFs/payload9.pdf', true]
        ];
    }
}
