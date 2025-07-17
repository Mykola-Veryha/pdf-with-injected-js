# PDF JavaScript Detection Tests

This directory contains PHPUnit tests for the PDF JavaScript detection functionality.

## Directory Structure

```
tests/
├── Data/                    # Test PDF files
│   ├── valid--*.pdf        # Valid PDFs (no JavaScript)
│   ├── invalid--*.pdf      # Invalid PDFs (contain JavaScript)
│   └── PayloadsAllThePDFs/ # Malicious PDF payloads
├── Unit/                   # Unit tests
│   ├── SimpleTest.php      # Basic functionality tests
│   └── PdfJavaScriptDetectorTest.php # Main test suite
├── reports/                # Test reports and coverage
└── README.md              # This file
```

## Running Tests

### Run all tests
```bash
./vendor/bin/phpunit
```

### Run specific test class
```bash
./vendor/bin/phpunit tests/Unit/PdfJavaScriptDetectorTest.php
```

### Run specific test method
```bash
./vendor/bin/phpunit --filter testValidPdfNoJavaScript tests/Unit/PdfJavaScriptDetectorTest.php
```

## Test Coverage

The tests cover:

1. **Valid PDFs**: Files that should not contain JavaScript
2. **Invalid PDFs**: Files that contain JavaScript actions
3. **Malicious Payloads**: Known malicious PDF files with injected JavaScript
4. **Edge Cases**: Non-existent files, empty files, corrupted files
5. **Repair Workflow**: Testing the complete repair + detection workflow

## Test Data

- **Valid PDFs**: Clean PDF files without JavaScript
- **Invalid PDFs**: PDFs with various JavaScript injection techniques
- **PayloadsAllThePDFs**: Collection of malicious PDF payloads for testing

## Expected Results

- Valid PDFs should return `false` (no JavaScript detected)
- Invalid PDFs should return `true` (JavaScript detected)
- Malicious payloads should return `true` (JavaScript detected)
- Non-existent files should throw `InvalidArgumentException`
- Empty files should return `false` (no JavaScript detected) 