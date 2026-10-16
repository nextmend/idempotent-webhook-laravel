<?php

namespace App\Http\Controllers;

use App\Mail\VoucherIssued;
use App\Models\Order;
use Illuminate\Http\Request;
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

        if ($order->status === 'pending' && Order::isPaid($request->all())) {
            $voucher = $order->vouchers()->create(['code' => Str::upper(Str::random(12))]);
            Log::info('Voucher issued', ['order_id' => $order->order_id, 'code' => $voucher->code]);

            Mail::to($order->email)->send(new VoucherIssued($voucher));

            $order->update(['status' => 'paid', 'paid_at' => now()]);
        }

        return response('OK');
    }
}
