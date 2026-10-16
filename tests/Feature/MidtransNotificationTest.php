<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MidtransNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.midtrans.server_key' => 'server-key-test']);
        $this->order = Order::create(['order_id' => 'ORDER-1001', 'email' => 'pembeli@example.com', 'amount' => 50000]);
    }

    public function test_settlement_issues_one_voucher_and_emails_it(): void
    {
        $this->postJson('/midtrans/notification', $this->notification())->assertOk();

        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame(1, $this->order->vouchers()->count());
        $this->assertSame(1, $this->emailsSent());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->postJson('/midtrans/notification', $this->notification(['signature_key' => 'palsu']))
            ->assertForbidden();

        $this->assertSame(0, $this->order->vouchers()->count());
    }

    public function test_duplicate_notification_issues_no_second_voucher(): void
    {
        $this->postJson('/midtrans/notification', $this->notification())->assertOk();
        $this->postJson('/midtrans/notification', $this->notification())->assertOk();

        $this->assertSame(1, $this->order->vouchers()->count());
        $this->assertSame(1, $this->emailsSent());
    }

    /** A notification shaped like Midtrans', signed with the test server key. */
    private function notification(array $overrides = []): array
    {
        $n = array_merge([
            'order_id' => 'ORDER-1001',
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'bank_transfer',
        ], $overrides);

        $n['signature_key'] ??= hash('sha512', $n['order_id'].$n['status_code'].$n['gross_amount'].'server-key-test');

        return $n;
    }

    /** Emails delivered through the array mailer that phpunit.xml sets. */
    private function emailsSent(): int
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->count();
    }
}
