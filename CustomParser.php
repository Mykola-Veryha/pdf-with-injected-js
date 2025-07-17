<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/CustomRawDataParser.php';

use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\PDFObject;

/**
 * Custom Parser extending Smalot's Parser
 *
 * This class is designed to allow public access to the parseObject method.
 */
class CustomParser extends Parser
{
    public function __construct($cfg = [], ?Config $config = null)
    {
        parent::__construct($cfg, $config);
        $this->rawDataParser = new CustomRawDataParser($cfg, $config);
    }

    public function rawDataParser(): CustomRawDataParser
    {
        return $this->rawDataParser;
    }

    public function parseObjectPublic(string $id, array $structure, ?Document $document): ?PDFObject
    {
        // Clear the array - this automatically frees all object references
        $this->objects = [];
        $this->parseObject($id, $structure, $document);

        return $this->objects[$id] ?? null;
    }
}
