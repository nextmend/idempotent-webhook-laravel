<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * One unpaid order, matching contoh/notifikasi-settlement.json.
     */
    public function run(): void
    {
        Order::create(['order_id' => 'ORDER-1001', 'email' => 'pembeli@example.com', 'amount' => 50000]);
    }
}
