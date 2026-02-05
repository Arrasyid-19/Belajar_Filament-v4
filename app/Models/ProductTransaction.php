<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTransaction extends Model
{
    use HasFactory, SoftDeletes;
    
    // Isi atribut yang dapat diisi secara massal
    protected $fillable = [
        'name',
        'phone',
        'email',
        'booking_trx_id',
        'city',
        'post_code',
        'address',
        'quantity',
        'sub_total_amount',
        'grand_total_amount',
        'discount_amount',
        'is_paid',
        'produk_id',
        'shoe_size',
        'promo_code_id',
        'proof',
    ];

    public static function generateUniqueTrxId() // Fungsi untuk menghasilkan ID transaksi unik
    {
        $prefix = 'TJH';
        do {
            $randomString = $prefix . mt_rand(10001, 99999);
        } while (self::where('booking_trx_id', $randomString)->exists());
        return $randomString;
    }


    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }
}