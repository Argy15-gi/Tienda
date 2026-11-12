<?php

use App\Http\Controllers\VentaController;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$basePath = dirname(__DIR__, 2);
$mode = $argv[1] ?? 'parent';

if ($mode === 'worker') {
    chdir($basePath);
    require $basePath.'/vendor/autoload.php';
    $app = require $basePath.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $signal = getenv('SALE_START_SIGNAL');
    while (! is_file($signal)) {
        usleep(1000);
    }

    $request = Request::create('/vendedor/ventas', 'POST', [
        'items' => [[
            'producto_id' => (int) getenv('SALE_PRODUCT_ID'),
            'cantidad' => 1,
        ]],
    ]);
    $seller = User::query()->findOrFail((int) getenv('SALE_SELLER_ID'));
    $session = $app['session']->driver();
    $session->start();
    $request->setLaravelSession($session);
    $app->instance('request', $request);
    $request->setUserResolver(fn () => $seller);

    try {
        $response = app(VentaController::class)->store($request);
        fwrite(STDOUT, $response->isRedirect() ? "SOLD\n" : "UNEXPECTED_RESPONSE\n");
    } catch (ValidationException) {
        fwrite(STDOUT, "REJECTED\n");
    }

    exit(0);
}

$databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'la-tiendita-concurrency-'.bin2hex(random_bytes(8)).'.sqlite';
$signalPath = $databasePath.'.start';
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$databasePath);
putenv('SESSION_DRIVER=array');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $databasePath;
$_ENV['SESSION_DRIVER'] = 'array';

try {
    chdir($basePath);
    require $basePath.'/vendor/autoload.php';
    $app = require $basePath.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    Artisan::call('migrate:fresh', ['--force' => true]);
    $seller = User::query()->create([
        'name' => 'Concurrent Seller',
        'email' => 'concurrent-seller@example.test',
        'google_id' => 'concurrent-seller',
        'rol' => 'VENDEDOR',
    ]);
    $product = Producto::query()->create([
        'sku' => 'LAST-UNIT',
        'nombre' => 'Última unidad',
        'descripcion' => '',
        'precio_centavos' => 500,
        'stock' => 1,
        'activo' => true,
    ]);

    $environment = array_merge(getenv(), [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $databasePath,
        'SESSION_DRIVER' => 'array',
        'SALE_START_SIGNAL' => $signalPath,
        'SALE_PRODUCT_ID' => (string) $product->id,
        'SALE_SELLER_ID' => (string) $seller->id,
    ]);
    $processes = [];

    for ($index = 0; $index < 2; $index++) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, __FILE__, 'worker'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $basePath,
            $environment,
        );
        if (! is_resource($process)) {
            throw new RuntimeException('No se pudo iniciar un proceso de prueba concurrente.');
        }

        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }

    file_put_contents($signalPath, 'start');
    $outcomes = [];

    foreach ($processes as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('Falló el proceso de venta: '.$stderr);
        }
        $outcomes[] = trim($stdout);
    }

    sort($outcomes);
    $stock = Producto::query()->findOrFail($product->id)->stock;
    $sales = Venta::query()->count();

    if ($outcomes !== ['REJECTED', 'SOLD'] || $stock !== 0 || $sales !== 1) {
        throw new RuntimeException(json_encode([
            'outcomes' => $outcomes,
            'stock' => $stock,
            'sales' => $sales,
        ], JSON_THROW_ON_ERROR));
    }

    fwrite(STDOUT, "PASS: una venta completada, una rechazada, stock final 0.\n");
} finally {
    DB::disconnect();
    @unlink($signalPath);
    @unlink($databasePath);
    @unlink($databasePath.'-journal');
    @unlink($databasePath.'-wal');
    @unlink($databasePath.'-shm');
}
