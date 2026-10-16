<?php

use App\Models\Order;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Sends the same signed notification twice at the same moment, for --orders
// new orders, then counts orders that got more than one voucher.
// Run the app with: PHP_CLI_SERVER_WORKERS=4 php artisan serve --no-reload
Artisan::command('demo:notifikasi-dobel {--orders=20} {--url=http://127.0.0.1:8000/midtrans/notification}', function () {
    $statuses = [];

    $orders = collect(range(1, (int) $this->option('orders')))->map(function () use (&$statuses) {
        $order = Order::create([
            'order_id' => 'DEMO-'.Str::upper(Str::random(8)),
            'email' => 'pembeli@example.com',
            'amount' => 50000,
        ]);

        $n = [
            'order_id' => $order->order_id,
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ];
        $n['signature_key'] = hash('sha512', $n['order_id'].$n['status_code'].$n['gross_amount'].config('services.midtrans.server_key'));

        $responses = Http::pool(fn (Pool $pool) => [
            $pool->acceptJson()->post($this->option('url'), $n),
            $pool->acceptJson()->post($this->option('url'), $n),
        ]);

        foreach ($responses as $response) {
            $statuses[] = $response instanceof Response ? $response->status() : 'gagal';
        }

        return $order;
    });

    $doubled = $orders->filter(fn (Order $order) => $order->vouchers()->count() > 1)->count();

    $this->line('Status HTTP: '.json_encode(array_count_values($statuses)));
    $this->line("Order dengan lebih dari satu voucher: {$doubled} dari {$orders->count()}");
});
