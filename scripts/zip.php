<?php

if ($argc < 3) {
    echo "Usage: php zip.php <source_dir> <output_zip>\n";
    exit(1);
}

$sourceDir = realpath($argv[1]);
$outputZip = $argv[2];

if (!$sourceDir || !is_dir($sourceDir)) {
    echo "Error: Source directory does not exist: {$argv[1]}\n";
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "Error: Could not open/create output zip: {$outputZip}\n";
    exit(1);
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

echo "Creating zip archive: {$outputZip}\n";
echo "Compressing files from: {$sourceDir}\n";

$count = 0;
foreach ($files as $file) {
    $filePath = $file->getRealPath();
    // Get path relative to the source directory for the zip entry
    $relativePath = ltrim(substr($filePath, strlen($sourceDir)), '/\\');
    // Normalize path separators to forward slash for zip archive compatibility
    $relativePath = str_replace('\\', '/', $relativePath);

    if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
        // Unix Directory mode: 040755 (octal) -> 16877 (decimal)
        $zip->setExternalAttributesName($relativePath, ZipArchive::OPSYS_UNIX, 16877 << 16);
    } else {
        $zip->addFile($filePath, $relativePath);
        
        // Check if file is artisan
        $basename = basename($filePath);
        if ($basename === 'artisan') {
            // Unix Executable mode: 0100755 (octal) -> 33261 (decimal)
            $zip->setExternalAttributesName($relativePath, ZipArchive::OPSYS_UNIX, 33261 << 16);
        } else {
            // Unix Regular file mode: 0100644 (octal) -> 33188 (decimal)
            $zip->setExternalAttributesName($relativePath, ZipArchive::OPSYS_UNIX, 33188 << 16);
        }
    }
    
    $count++;
    if ($count % 500 === 0) {
        echo "Processed {$count} items...\n";
    }
}

echo "Finalizing zip archive... This may take a moment.\n";
$zip->close();
echo "SUCCESS: Created zip with {$count} items.\n";
