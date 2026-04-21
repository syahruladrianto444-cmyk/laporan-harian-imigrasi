<?php

/**
 * Diagnostic script to extract raw text from PDF files
 * Usage: php test_pdf_parse.php <path-to-pdf>
 * 
 * This will show the exact text output from smalot/pdfparser
 * to help debug regex patterns.
 */

require __DIR__ . '/vendor/autoload.php';

use Smalot\PdfParser\Parser;

if ($argc < 2) {
    echo "Usage: php test_pdf_parse.php <path-to-pdf>\n";
    echo "Example: php test_pdf_parse.php test_skim.pdf\n";
    exit(1);
}

$filePath = $argv[1];

if (!file_exists($filePath)) {
    echo "Error: File not found: {$filePath}\n";
    exit(1);
}

$parser = new Parser();
$pdf = $parser->parseFile($filePath);

echo "=== RAW TEXT (getText()) ===\n";
$text = $pdf->getText();
echo $text;
echo "\n\n=== END RAW TEXT ===\n\n";

echo "=== TEXT WITH VISIBLE WHITESPACE ===\n";
// Show newlines, tabs, spaces explicitly
$visible = str_replace(
    ["\n", "\r", "\t"],
    ["↵\n", "←\n", "→"],
    $text
);
echo $visible;
echo "\n=== END VISIBLE WHITESPACE ===\n\n";

echo "=== PAGE-BY-PAGE TEXT ===\n";
$pages = $pdf->getPages();
foreach ($pages as $i => $page) {
    echo "--- Page " . ($i + 1) . " ---\n";
    echo $page->getText();
    echo "\n--- End Page " . ($i + 1) . " ---\n\n";
}
