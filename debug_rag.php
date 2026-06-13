<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\RagSession;

$ulid = '01KTXZ8SDGBZ9HA38MG447A0FD';
$session = RagSession::find($ulid);

if (!$session) {
    echo "Session not found in database!\n";
} else {
    echo "Session found:\n";
    print_r($session->toArray());
    
    echo "\nTrying to call sessionStatus...\n";
    try {
        $service = app(App\Services\RagToolService::class);
        $status = $service->sessionStatus($session);
        echo "Result:\n";
        print_r($status);
    } catch (\Throwable $e) {
        echo "Error calling sessionStatus: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    }
}
