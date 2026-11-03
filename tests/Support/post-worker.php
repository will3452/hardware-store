<?php

use App\Models\Store;
use App\Models\User;
use App\Services\Operations;
use App\Services\SalesOrders;
use App\Support\Tenancy\StoreContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

// Separate database connection for concurrency tests; never targets production.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'hardware_store_test') {
    throw new RuntimeException('Concurrency worker requires the isolated test database.');
}
$input = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$store = Store::findOrFail($input['store_id']);
$user = User::findOrFail($input['user_id']);
app(StoreContext::class)->set($store);
try {
    $document = $input['kind'] === 'order_release'
        ? app(SalesOrders::class)->act($user, 'release', $input['data']['order_id'], array_diff_key($input['data'], ['order_id' => true]))
        : app(Operations::class)->post($user, $input['kind'], $input['data']);
    echo json_encode(['result' => 'posted', 'id' => $document->id]);
} catch (ValidationException $error) {
    echo json_encode(['result' => 'rejected', 'errors' => $error->errors()]);
}
