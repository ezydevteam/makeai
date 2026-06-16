<?php
$zip = new ZipArchive();
if ($zip->open('dist/makeai-v1.0.0.zip') === true) {
    echo "Total files: " . $zip->numFiles . "\n";
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === 'index.php' || $name === '.htaccess' || $name === 'robots.txt' || $name === 'favicon.ico') {
            echo "Found root file: " . $name . "\n";
        }
    }
    $zip->close();
} else {
    echo "Failed to open zip\n";
}
