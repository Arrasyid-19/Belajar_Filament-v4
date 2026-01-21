<?php

namespace App\Filament\Resources\ProductTransactions;

use App\Filament\Resources\ProductTransactions\Pages\CreateProductTransaction;
use App\Filament\Resources\ProductTransactions\Pages\EditProductTransaction;
use App\Filament\Resources\ProductTransactions\Pages\ListProductTransactions;
use App\Filament\Resources\ProductTransactions\Schemas\ProductTransactionForm;
use App\Filament\Resources\ProductTransactions\Tables\ProductTransactionsTable;
use App\Models\ProductTransaction;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductTransactionResource extends Resource
{
    protected static ?string $model = ProductTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('icon')
                    ->image()
                    ->directory('categories')
                    ->maxSize(1024)
                    ->required()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return ProductTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductTransactions::route('/'),
            'create' => CreateProductTransaction::route('/create'),
            'edit' => EditProductTransaction::route('/{record}/edit'),
        ];
    }
}
