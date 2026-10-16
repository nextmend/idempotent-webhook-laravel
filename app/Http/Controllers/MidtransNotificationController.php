<?php

namespace App\Http\Controllers;

use App\Mail\VoucherIssued;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class MidtransNotificationController
{
    public function __invoke(Request $request)
    {
        // An empty key would make the signature computable by anyone.
        $serverKey = config('services.midtrans.server_key')
            ?: throw new RuntimeException('MIDTRANS_SERVER_KEY is not set.');

        $expected = hash('sha512', $request->input('order_id')
            .$request->input('status_code')
            .$request->input('gross_amount')
            .$serverKey);

        // Not sent by Midtrans, or the server key is from the other environment (sandbox vs production).
        abort_unless(hash_equals($expected, (string) $request->input('signature_key')), 403);

        $order = Order::where('order_id', $request->input('order_id'))->firstOrFail();

        if (! Order::isPaid($request->all())) {
            return response('OK');
        }

        $voucher = DB::transaction(function () use ($order) {
            // Claim the order with one conditional UPDATE. The database lets only
            // one notification move it out of pending; every other one gets 0 rows.
            $claimed = Order::whereKey($order->id)
                ->where('status', 'pending')
                ->update(['status' => 'paid', 'paid_at' => now()]) === 1;

            // Same transaction as the claim: if this fails, the order stays
            // pending and Midtrans' retry can try again.
            return $claimed ? $order->vouchers()->create(['code' => Str::upper(Str::random(12))]) : null;
        });

        if ($voucher) {
            Log::info('Voucher issued', ['order_id' => $order->order_id, 'code' => $voucher->code]);

            // After the commit, through the queue: a slow or failing mail server
            // no longer fails the notification, and the queue retries the email.
            Mail::to($order->email)->queue(new VoucherIssued($voucher));
        }

        return response('OK');
    }
}
