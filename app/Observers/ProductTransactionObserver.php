<?php

namespace App\Observers; // Observer berguna untuk memantau event pada model ProductTransaction

use App\Models\ProductTransaction;
use App\Models\Produk;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductTransactionObserver
{
    /**
     * Handle the ProductTransaction "created" event.
     */
    public function creating(ProductTransaction $transaction): void
    {
        if (! $transaction->is_paid) return; // Jika transaksi belum dibayar, tidak perlu cek stok

        DB::transaction(function () use ($transaction) { // Menggunakan transaksi database untuk memastikan konsistensi data
            $produk = Produk::lockForUpdate()->find($transaction->produk_id); // Mengunci baris produk untuk update

            if (! $produk) return; // Jika produk tidak ditemukan, keluar dari fungsi

             // Cek apakah stok produk mencukupi

            if ($transaction->quantity > $produk->stock) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok Produk tidak mencukupi.'
                ]);
            }
        });
    }

    public function created(ProductTransaction $transaction)
    {
        // Setelah transaksi dibuat, jika sudah dibayar, kurangi stok produk
        if ($transaction->is_paid) {
            Produk::where('id', $transaction->produk_id)
                ->decrement('stock', $transaction->quantity);
        }
    }


    /**
     * Handle the ProductTransaction "updated" event.
     */
    public function updating(ProductTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {

            $produk = Produk::lockForUpdate()->find($transaction->produk_id); // Mengunci baris produk untuk update
            if(! $produk) return;

            // Ambil status pembayaran lama dan baru serta jumlah produk
            $oldPaid = $transaction->getOriginal('is_paid');
            $newPaid = $transaction->is_paid;
            $qty  = $transaction->quantity;

             // paid -> unpaid (BLOCK)
            if ($oldPaid && ! $newPaid) {
                    throw ValidationException::withMessages([
                        'is_paid' => 'Status pembayaran tidak bisa dibatalkan.',
                    ]);
                }

            // edit qty setelah Lunas (BLOCK)
            if ($oldPaid && $transaction->is_dirty('quantity')) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah Produk tidak bisa diubah setelah Transaksi Lunas.'
                ]);
            }

            // unpaid -> paid (Approve)
            if (! $oldPaid && $newPaid) {

                // Wajib upload bukti pembayaran
            if (! $transaction->proof) {
                throw ValidationException::withMessages([
                    'proof' => 'Bukti pembayaran wajib diunggah sebelum approve.',
                ]);
            }

            // Cek stok produk
                if ($qty > $produk->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stok Produk tidak mencukupi.',
                    ]);
                }

                // Kurangi stok produk
                $produk->decrement('stock', $qty);
            }
        });
    }

    /**
     * Handle the ProductTransaction "deleted" event.
     */
    public function deleted(ProductTransaction $transaction): void
    {
        if ($transaction->is_paid) {
            Produk::where('id', $transaction->produk_id)
                ->increment('stock', $transaction->quantity);
        }
    }

    /**
     * Handle the ProductTransaction "restored" event.
     */
    public function restored(ProductTransaction $transaction): void
    {
        if ($transaction->is_paid) {
            Produk::where('id', $transaction->produk_id)
                ->decrement('stock', $transaction->quantity);
        }
    }

    /**
     * Handle the ProductTransaction "force deleted" event.
     */
    public function forceDeleted(ProductTransaction $productTransaction): void
    {
        //
    }
}
