<?php
/**
 * Test different PDF text extraction methods
 */
require __DIR__ . '/vendor/autoload.php';

use Smalot\PdfParser\Parser;

$filePath = $argv[1] ?? null;
if (!$filePath || !file_exists($filePath)) {
    echo "Usage: php test_pdf_methods.php <path-to-pdf>\n";
    exit(1);
}

$parser = new Parser();
$pdf = $parser->parseFile($filePath);

echo "=== Method 1: getText() ===\n";
$text = $pdf->getText();
echo strlen($text) > 0 ? $text : "(EMPTY)\n";
echo "\n\n";

echo "=== Method 2: Page-by-page getText() ===\n";
$pages = $pdf->getPages();
foreach ($pages as $i => $page) {
    echo "--- Page " . ($i + 1) . " ---\n";
    $pageText = $page->getText();
    echo strlen($pageText) > 0 ? $pageText : "(EMPTY)";
    echo "\n";
}
echo "\n";

echo "=== Method 3: getDataTm() per page ===\n";
foreach ($pages as $i => $page) {
    echo "--- Page " . ($i + 1) . " ---\n";
    try {
        $dataTm = $page->getDataTm();
        if (!empty($dataTm)) {
            foreach ($dataTm as $item) {
                // Each item is [coordinates, text]
                $coords = $item[0] ?? [];
                $txt = $item[1] ?? '';
                $x = $coords[4] ?? 0;
                $y = $coords[5] ?? 0;
                echo sprintf("[x:%.0f y:%.0f] %s\n", $x, $y, $txt);
            }
        } else {
            echo "(EMPTY DataTm)\n";
        }
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "=== Method 4: getTextArray() per page ===\n";
foreach ($pages as $i => $page) {
    echo "--- Page " . ($i + 1) . " ---\n";
    try {
        $textArray = $page->getTextArray();
        if (!empty($textArray)) {
            foreach ($textArray as $item) {
                if (trim($item) !== '') {
                    echo json_encode($item) . "\n";
                }
            }
        } else {
            echo "(EMPTY TextArray)\n";
        }
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "=== Method 5: PDF Metadata ===\n";
$details = $pdf->getDetails();
foreach ($details as $key => $value) {
    if (is_array($value)) $value = json_encode($value);
    echo "$key: $value\n";
}
echo "\n";

echo "=== Method 6: Font info ===\n";
try {
    $fonts = $pdf->getFonts();
    foreach ($fonts as $font) {
        echo "Font: " . $font->getName() . " | Type: " . $font->getType() . "\n";
    }
} catch (\Exception $e) {
    echo "Error getting fonts: " . $e->getMessage() . "\n";
}
