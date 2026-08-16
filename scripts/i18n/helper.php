<?php

function processChunk($chunkNum, $translations) {
    $chunkFile = __DIR__ . sprintf('/chunks/chunk-%03d.json', $chunkNum);
    $sourceKeys = json_decode(file_get_contents($chunkFile), true);
    
    $locales = ['bn', 'ar', 'es', 'fr', 'hi'];
    $valid = true;
    foreach ($locales as $loc) {
        if (!isset($translations[$loc])) {
            echo "Missing locale {$loc} in chunk {$chunkNum}\n";
            $valid = false;
            continue;
        }
        if (count($translations[$loc]) !== count($sourceKeys)) {
            echo "Count mismatch in chunk {$chunkNum} for {$loc}: " . count($translations[$loc]) . " vs " . count($sourceKeys) . "\n";
            $valid = false;
        }
        foreach ($sourceKeys as $sk) {
            if (!isset($translations[$loc][$sk])) {
                echo "Missing key in chunk {$chunkNum} [{$loc}]: {$sk}\n";
                $valid = false;
            }
        }
    }
    
    if ($valid) {
        $outFile = __DIR__ . sprintf('/incoming/out-%03d.json', $chunkNum);
        file_put_contents($outFile, json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "Wrote chunk {$chunkNum} (" . count($sourceKeys) . " keys) to {$outFile}\n";
    }
}
