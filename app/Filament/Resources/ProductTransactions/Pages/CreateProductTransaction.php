<?php

namespace App\Filament\Resources\ProductTransactions\Pages;

use App\Filament\Resources\ProductTransactions\ProductTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductTransaction extends CreateRecord
{
    protected static string $resource = ProductTransactionResource::class;
}
