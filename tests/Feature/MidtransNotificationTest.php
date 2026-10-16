<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
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

    public function test_retry_after_a_failed_email_issues_no_second_voucher(): void
    {
        // The mail server times out once, so the first attempt fails and Midtrans retries.
        $failed = false;
        Event::listen(MessageSending::class, function () use (&$failed) {
            if (! $failed) {
                $failed = true;
                throw new TransportException('Connection to smtp.example.com:587 timed out.');
            }
        });

        $this->postJson('/midtrans/notification', $this->notification());
        $this->postJson('/midtrans/notification', $this->notification())->assertOk();

        $this->assertSame(1, $this->order->vouchers()->count());
    }

    public function test_duplicate_arriving_mid_processing_issues_no_second_voucher(): void
    {
        // The duplicate arrives while the first notification is still sending its email,
        // one spot inside the window that two parallel requests can hit in production.
        $duplicateSent = false;
        Event::listen(MessageSending::class, function () use (&$duplicateSent) {
            if (! $duplicateSent) {
                $duplicateSent = true;
                $this->postJson('/midtrans/notification', $this->notification())->assertOk();
            }
        });

        $this->postJson('/midtrans/notification', $this->notification())->assertOk();

        $this->assertSame(1, $this->order->vouchers()->count());
        $this->assertSame(1, $this->emailsSent());
    }

    public function test_database_refuses_a_second_voucher_for_one_order(): void
    {
        $this->order->vouchers()->create(['code' => 'KODEPERTAMA1']);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->order->vouchers()->create(['code' => 'KODEKEDUA002']);
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
