<?php

namespace App\Filament\Resources\PromoCodes;

use App\Filament\Resources\PromoCodes\Pages\CreatePromoCode;
use App\Filament\Resources\PromoCodes\Pages\EditPromoCode;
use App\Filament\Resources\PromoCodes\Pages\ListPromoCodes;
use App\Filament\Resources\PromoCodes\Schemas\PromoCodeForm;
use App\Filament\Resources\PromoCodes\Tables\PromoCodesTable;
use App\Models\PromoCode;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Date;
use SebastianBergmann\CodeCoverage\Report\Text;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('code')
                    ->unique(ignoreRecord:true) //Agar Kode Promo menjadi Unik, tidak duplikat.
                    ->required()
                    ->label('Kode Promo')
                     ->rules([ //Custom Rules
                        'string',
                        'min:5',
                        'max:255'
                    ])
                    ->validationMessages([ //Pesan Khusus
                        'unique' => 'Kode Promo sudah ada, Mohon ganti dengan yang lain',
                        'min'    => 'Kode Promo terlalu pendek (minimal 5 karakter)',
                        'max'    => 'Kode Promo terlalu panjang'
                    ]),

                TextInput::make('discount_amount')
                    ->required()
                    ->numeric()
                    ->prefix('IDR')
                    ->label('Diskon Harga'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode Promo')
                    ->searchable(),
                
                TextColumn::make('discount_amount')
                    ->label('Diskon Harga')
                    ->money('idr', true)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
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
            'index' => ListPromoCodes::route('/'),
            'create' => CreatePromoCode::route('/create'),
            'edit' => EditPromoCode::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
