<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; //import Model class agar bisa; akses database, query ORM dan relasi Tabel
use Illuminate\Database\Eloquent\Factories\HasFactory; //dipakai untuk dummy data, seeder dan testing
use Illuminate\Database\Eloquent\SoftDeletes; // mengaktifkan fitur soft delete, jadi data tidak langsung hilang dari database
use Illuminate\Support\Str; //import Str class untuk manipulasi string, seperti membuat slug
use Illuminate\Database\Eloquent\Relations\BelongsTo; //import BelongsTo class untuk relasi many-to-one
use Illuminate\Database\Eloquent\Relations\HasMany; //import HasMany class untuk relasi one-to-many

class Produk extends Model
{
    use HasFactory, SoftDeletes;

    # fillable berisi daftar atribut yang boleh diisi secara massal
    protected $fillable = [
        'name',
        'slug',
        'thumbnail',
        'about',
        'price',
        'stock',
        'is_popular',
        'category_id',
        'brand_id',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProdukPhoto::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProdukSize::class, 'produk_id');
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    protected static function setStock()
    {

    }
}
