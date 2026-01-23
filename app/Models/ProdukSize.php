<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProdukSize extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['size', 'produk_id'];

    protected static function getSize(?int $produkId)
    {
        if (!$produkId) {
            return [];
        }

        return static::where('produk_id', $produkId)
        ->pluck('size', 'size')
        ->toArray();
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
