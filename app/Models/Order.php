<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['order_id', 'email', 'amount', 'status', 'paid_at'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * The three fields Midtrans says to check for a successful payment.
     */
    public static function isPaid(array $trx): bool
    {
        return ($trx['status_code'] ?? null) === '200'
            && in_array($trx['transaction_status'] ?? null, ['capture', 'settlement'], true)
            && ($trx['fraud_status'] ?? 'accept') === 'accept';
    }
}
