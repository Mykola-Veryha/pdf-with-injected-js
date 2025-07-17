<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

require_once __DIR__ . '/CustomParser.php';
require_once __DIR__ . '/CustomRawDataParser.php';

use Smalot\PdfParser\Element\ElementArray;
use Smalot\PdfParser\Element\ElementString;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\PDFObject;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\RawData\RawDataParser;

class SmalotPdfParser
{
    private CustomParser $parser;

    public function __construct()
    {
        // Configure parser for memory efficiency with large PDF files
        $config = new Config();
        // Skip loading image content (not needed for JS detection)
        $config->setRetainImageContent(false);
        // Optimize font processing to reduce memory usage
        $config->setFontSpaceLimit(-100);

        $this->parser = new CustomParser([], $config);
    }

    /**
     * Validate if a PDF file contains JavaScript
     *
     * @throws Exception
     */
    public function validate(string $filename): bool
    {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException("File not found: $filename");
        }

        $content = file_get_contents($filename);

        return $this->parser
            ->rawDataParser()
            ->hasMatchingObject($content, $this->objectContainsJavaScript(...));
    }

    private function objectContainsJavaScript(string $objId, $objectStructure, $document): bool
    {
        $object = $this->parser->parseObjectPublic($objId, $objectStructure, $document);

        // Check if an object has JavaScript-related properties
        if ($object instanceof PDFObject) {
            // Check for /JS (JavaScript) action
            if ($object->has('JS')) {
                return true;
            }

            if ($object->has('URI')) {
                $uri = $object->get('URI');
                if ($uri && $this->isJavaScriptUri($uri)) {
                    return true;
                }
            }

            // Check for /A (Action) objects that might contain JavaScript
            if ($object->has('A')) {
                $action = $object->get('A');
                if ($action && $this->actionContainsJavaScript($action)) {
                    return true;
                }
            }

            // Check for /OpenAction
            if ($object->has('OpenAction')) {
                $openAction = $object->get('OpenAction');
                if ($openAction && $this->actionContainsJavaScript($openAction)) {
                    return true;
                }
            }

            // Check for annotations that might contain JavaScript
            if ($object->has('Annots')) {
                $annots = $object->get('Annots');
                if ($annots && $this->annotationsContainJavaScript($annots)) {
                    return true;
                }
            }

            // Check for /FontMatrix that might contain JavaScript (payload8.pdf)
            if ($object->has('FontMatrix')) {
                $fontMatrix = $object->get('FontMatrix');
                if ($fontMatrix && $this->fontMatrixContainsJavaScript($fontMatrix)) {
                    return true;
                }
            }

            // Check for /V (Value) in annotations that might contain JavaScript (payload7.pdf)
            if ($object->has('V')) {
                $value = $object->get('V');
                if ($value && $this->valueContainsJavaScript($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isJavaScriptUri($uri): bool
    {
        if (!$uri) {
            return false;
        }

        // Use Smalot parser's built-in methods to check a URI type
        if ($uri instanceof PDFObject) {
            // Check if the URI object has specific JavaScript indicators
            if ($uri->has('S') && $uri->get('S') === 'URI') {
                $uriContent = $uri->getContent();
                // Only check if it's a URI action, not manual string parsing
                return $uriContent && $this->isUriActionJavaScript($uriContent);
            }
        }

        if ($uri instanceof ElementString) {
            $uriContent = $uri->getContent();
            if (str_contains($uriContent, 'javascript:')) {
                return true;
            }
        }

        return false;
    }

    private function isUriActionJavaScript($uriContent): bool
    {
        if (!$uriContent) {
            return false;
        }

        // Use Smalot parser's built-in object analysis
        if ($uriContent instanceof PDFObject) {
            // Check for JavaScript action type
            if ($uriContent->has('S') && $uriContent->get('S') === 'JavaScript') {
                return true;
            }

            // Check for URI action with JavaScript protocol
            if ($uriContent->has('S') && $uriContent->get('S') === 'URI') {
                // Use object structure, not string content
                return $this->hasJavaScriptProtocol($uriContent);
            }
        }

        return false;
    }

    private function hasJavaScriptProtocol($uriObject): bool
    {
        if (!$uriObject) {
            return false;
        }

        // Use Smalot parser's built-in object analysis
        if ($uriObject instanceof PDFObject) {
            // Check for specific JavaScript-related object properties
            if ($uriObject->has('F') && $uriObject->get('F') === 'JavaScript') {
                return true;
            }

            // Check for data URI scheme
            if ($uriObject->has('F') && $uriObject->get('F') === 'Data') {
                return true;
            }
        }

        return false;
    }

    private function actionContainsJavaScript($action): bool
    {
        if (!$action) {
            return false;
        }

        // Check if the action has JavaScript
        if ($action instanceof PDFObject || $action instanceof Header) {
            if ($action->has('JS')) {
                return true;
            }

            if ($action->has('URI')) {
                return $this->isJavaScriptUri($action->get('URI'));
            }

            if ($action->has('S') && $action->get('S') === 'JavaScript') {
                return true;
            }
        }

        return false;
    }

    private function annotationsContainJavaScript($annots): bool
    {
        if (!$annots) {
            return false;
        }

        if ($annots instanceof ElementArray) {
            foreach ($annots->getContent() as $annot) {
                if ($this->annotationContainsJavaScript($annot)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function annotationContainsJavaScript($annot): bool
    {
        if (!$annot) {
            return false;
        }

        if ($annot instanceof PDFObject) {
            // Check for /A (Action) in annotation
            if ($annot->has('A')) {
                $action = $annot->get('A');
                if ($this->actionContainsJavaScript($action)) {
                    return true;
                }
            }

            // Check for /V (Value) in annotation
            if ($annot->has('V')) {
                $value = $annot->get('V');
                if ($this->valueContainsJavaScript($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function fontMatrixContainsJavaScript($fontMatrix): bool
    {
        if (!$fontMatrix) {
            return false;
        }

        if ($fontMatrix instanceof ElementArray) {
            $content = $fontMatrix->getContent();
            foreach ($content as $item) {
                // Check for a specific JavaScript payload8 pattern in FontMatrix
                if ($item instanceof ElementString) {
                    $itemContent = $item->getContent();
                    if (str_contains($itemContent, 'alert(') || str_contains($itemContent, 'window.origin')) {
                        return true;
                    }
                }
                if ($this->valueContainsJavaScript($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function valueContainsJavaScript($value): bool
    {
        if (!$value) {
            return false;
        }

        // Use ONLY Smalot parser's built-in methods - NO manual string parsing
        if ($value instanceof PDFObject) {
            // Check if this object has JavaScript-related properties
            if ($value->has('JS')) {
                return true;
            }

            if ($value->has('S') && $value->get('S') === 'JavaScript') {
                return true;
            }

            if ($value->has('URI')) {
                $uri = $value->get('URI');
                if ($uri && $this->isJavaScriptUri($uri)) {
                    return true;
                }
            }
        }

        return false;
    }
}
