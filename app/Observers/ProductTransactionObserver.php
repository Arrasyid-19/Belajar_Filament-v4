<?php

namespace App\Observers;

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
        if (! $transaction->is_paid) return;

        DB::transaction(function () use ($transaction) {
            $produk = Produk::lockForUpdate()->find($transaction->produk_id);

            if (! $produk) return;

            if ($transaction->quantity > $produk->stock) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok Produk tidak mencukupi.'
                ]);
            }
        });
    }

    public function created(ProductTransaction $transaction)
    {
        if ($transaction->is_paid) {
            produk::where('id', $transaction->produk_id)
                ->decrement('stock', $transaction->quantity);
        }
    }


    /**
     * Handle the ProductTransaction "updated" event.
     */
    public function updating(ProductTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {

            $produk = Produk::lockForUpdate()->find($transaction->produk_id);
            if(! $produk) return;

            $oldPaid = $transaction->getOriginal('is_paid');
            $oldQty  = $transaction->getOriginal('quantity');
            $newQty  = $transaction->quantity;

             // unpaid → paid
            if (! $oldPaid && $transaction->is_paid) {
                if ($newQty > $produk->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stok produk tidak mencukupi.',
                    ]);
                }

                $produk->decrement('stock', $newQty);
            }

            // paid → unpaid
            if ($oldPaid && ! $transaction->is_paid) {
                $produk->increment('stock', $oldQty);
            }

            // paid → paid (qty berubah)
            if ($oldPaid && $transaction->is_paid && $oldQty !== $newQty) {
                $diff = $newQty - $oldQty;

                if ($diff > 0 && $diff > $produk->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stok produk tidak mencukupi.',
                    ]);
                }

                $produk->decrement('stock', $diff);
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
