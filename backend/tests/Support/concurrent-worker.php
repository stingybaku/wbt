<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'testing' || ! str_ends_with(config('database.connections.testing.database'), '_test')) {
    throw new RuntimeException('Concurrency worker requires the isolated test database.');
}
config(['queue.connections.database.connection' => 'testing']);
\Illuminate\Support\Facades\Mail::fake();
while (microtime(true) < (float) $argv[3]) {
    usleep(1000);
}
try {
    if ($argv[1] === 'payment') {
        app(\App\Services\CheckoutFulfillment::class)->fulfill((object) [
            'id' => $argv[2], 'payment_status' => 'paid', 'amount_total' => 3900,
        ]);
    } else {
        app(\App\Services\GenerationCredits::class)->enqueue(\App\Models\Project::findOrFail($argv[2]), []);
    }
    echo 'ok';
} catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
    echo $e->getResponse()->getStatusCode();
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    echo $e->getStatusCode();
}
