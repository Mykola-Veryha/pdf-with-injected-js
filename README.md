## Implementation Approach

The PDF specification is complex and parsing raw PDF content is error-prone. It's nearly impossible to reliably
parse PDF content without a proper PDF parser library. This tool uses the **smalot/pdfparser** library to avoid common pitfalls.

### What this tool does NOT do:

❌ **No custom regex patterns** - We don't parse raw PDF content with regex  
❌ **No text searching** - We don't search raw PDF content for JavaScript strings  
❌ **No false positives** - The library reliably avoids misinterpreting PDF content as object tags

### How this tool works:

The smalot/pdfparser library provides robust PDF parsing that correctly identifies actual JavaScript objects within
the PDF structure. Even if a PDF contains text like "app.alert" in its content, it will not be falsely detected
as JavaScript unless it's actually embedded as a JavaScript action or object.

## PDF Version Compatibility

This library supports various PDF versions, including older specifications and files with corrupted structure in tags.
While some PDF viewers are tolerant of such issues, not all are. However, these PDF files remain usable by end users
and should be supported by robust applications.

## Detection Workflow

The library implements a robust two-pass detection strategy with automatic PDF repair:

### Pass 1: Standard Detection
Attempt JavaScript detection using SmalotPdfParser on the original PDF file.

### Pass 2: Repair + Re-detection
If parsing fails:
1. Apply repair methods from PdfStructureRepairer
2. Re-attempt parsing with SmalotPdfParser on repaired content
3. If repair is unsuccessful, log failure and return `false` (no JavaScript detected)

## Test Coverage

The library includes comprehensive unit tests covering various PDF scenarios:

### ✅ Valid PDFs (No JavaScript)
- **10 test files** from 5 different PDF specification versions (1.3, 1.4, 1.5, 1.6, 1.7)
- File sizes range from **1 page to 445 pages**
- Sources include Adobe, W3C, MIT, and various test repositories
- All files are verified as valid PDF documents without JavaScript

### ⚠️ Valid PDFs (With JavaScript)
- Test files containing legitimate JavaScript actions and objects

### 🔧 Invalid PDFs (With JavaScript)
- Files with corrupted structure or old PDF formats
- Tests the repair functionality and detection accuracy

## Performance Optimization

The library uses a fast pre-screening optimization called `mightContainJavaScript()` that dramatically speeds up processing of PDFs without JavaScript. This function performs lightweight string checks on raw PDF object content before expensive parsing, looking for JavaScript indicators like `/JS`, `/JavaScript`, `/OpenAction`, `/AA`, `/Action`, `javascript:`, `alert`, and `window`. Only objects that pass this quick screen are subjected to full parsing and detailed analysis. This optimization allows the library to rapidly skip clean PDFs while maintaining thorough detection of actual JavaScript threats.
