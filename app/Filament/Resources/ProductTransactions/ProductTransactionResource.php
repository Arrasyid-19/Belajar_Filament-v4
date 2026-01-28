<?php

namespace App\Filament\Resources\ProductTransactions;

use App\Filament\Resources\ProductTransactions\Pages\CreateProductTransaction;
use App\Filament\Resources\ProductTransactions\Pages\EditProductTransaction;
use App\Filament\Resources\ProductTransactions\Pages\ListProductTransactions;
use App\Models\ProductTransaction;
use App\Models\ProdukSize;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use App\Models\PromoCode;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Models\Produk;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Laravel\Pail\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/* TODO:
- Konfigurasi Nama Category, Kode Promo & Brands tidak boleh sama dengan yang sudah ada
- Add Fitur Approve bagi yang belum Lunas
- Validasi Harga gak boleh dari 3 digit ke bawah. misal 500, 50, 5
*/

class ProductTransactionResource extends Resource
{
    protected static ?string $model = ProductTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static function rupiah(int $value = 0): string
    {
        return number_format($value, 0, ',', '.');
    }

    protected static function toInt(?string $value): int
    {
        return (int) str_replace('.', '', $value ?? '0');
    }

    protected static function recalculate(callable $get, callable $set): void
    {

        $produkId = $get('produk_id');
        $qty = max(1, (int) $get('quantity'));

        if (! $produkId) {
            $set('sub_total_amount', self::rupiah(0));
            $set('grand_total_amount', self::rupiah(0));
            return;
        }

        $produk = Produk::find($produkId);

        if (! $produk) return;


        $price    = (int) $produk->price;
        $discount = self::toInt($get('discount_amount'));

        $subTotal = $price * $qty;
        $grand = max(0, $subTotal - $discount);

        $set('sub_total_amount', self::rupiah($subTotal));
        $set('grand_total_amount', self::rupiah($grand));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informasi Pembeli')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nama Pembeli'),
                        TextInput::make('phone')
                            ->numeric()
                            ->required()
                            ->maxLength(255)
                            ->label('No. Telp'),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->label('Alamat Email'),
                        TextInput::make('booking_trx_id')
                            ->label('Booking ID')
                            ->default(fn() => ProductTransaction::generateUniqueTrxId())
                            ->nullable()
                            ->readOnly()
                            ->dehydrated()
                            ->required(),
                        TextInput::make('city')
                            ->required()
                            ->maxLength(255)
                            ->label('Kota/Kabupaten'),
                        TextInput::make('post_code')
                            ->required()
                            ->maxLength(20)
                            ->label('Kode Pos'),
                        TextArea::make('address')
                            ->required()
                            ->maxLength(500)
                            ->label('Alamat Lengkap')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columns(2),

                Section::make('Detail Pembayaran')
                    ->schema([
                        Select::make('produk_id')
                            ->label('Pilih Produk yang Dibeli')
                            ->relationship('produk', 'name')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {

                                if (!$state) {
                                    return;
                                }

                                $produk = Produk::find($state);

                                if (! $produk) {
                                    return;
                                }

                                $set('shoe_size', null);
                                $set('quantity', 1);
                                $set('discount_amount', 0);

                                static::recalculate($get, $set);
                            })
                            ->required(),

                        Select::make('shoe_size')
                            ->label('Ukuran Sepatu')
                            ->required()
                            ->options(
                                fn(callable $get) =>
                                ProdukSize::getSize($get('produk_id'))
                            )
                            ->hidden(fn(callable $get) => !$get('produk_id'))
                            ->reactive(),

                        TextInput::make('promo_code_input')
                            ->label('Kode Promo')
                            ->dehydrated(false)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (?string $state, callable $set, callable $get) {

                                if (blank($state)) {
                                    $set('promo_code_id', null);
                                    $set('discount_amount', 0);
                                    static::recalculate($get, $set);
                                    return;
                                }

                                $promo = PromoCode::where('code', $state)->first();

                                if ($promo) {
                                    $set('promo_code_id', $promo->id);
                                    $set('discount_amount', self::rupiah($promo->discount_amount));
                                } else {
                                    $set('promo_code_id', null);
                                    $set('discount_amount', self::rupiah(0));
                                }

                                static::recalculate($get, $set);
                            }),

                        Hidden::make('promo_code_id')->nullable(),
                        Hidden::make('product_price')->default(0),

                        TextInput::make('sub_total_amount')
                            ->label('Total Harga Sebelum Diskon')
                            ->readOnly()
                            ->prefix('Rp.')
                            ->dehydrateStateUsing(fn($state) => static::toInt($state)),

                        TextInput::make('discount_amount')
                            ->label('Total Diskon')
                            ->readOnly()
                            ->prefix('Rp')
                            ->dehydrateStateUsing(fn($state) => static::toInt($state)),

                        TextInput::make('grand_total_amount')
                            ->label('Total Harga')
                            ->readOnly()
                            ->prefix('Rp.')
                            ->dehydrateStateUsing(fn($state) => static::toInt($state)),

                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {

                                $produkId = $get('produk_id');

                                if (! $produkId) {
                                    return;
                                }

                                $produk = Produk::find($produkId);

                                if (! $produk) {
                                    return;
                                }

                                if ($state > $produk->stock) {
                                    $set('quantity', $produk->stock);

                                    Notification::make()
                                        ->title('Stok tidak mencukupi')
                                        ->body("Stok tersedia hanya {$produk->stock} item.")
                                        ->danger()
                                        ->send();
                                }

                                static::recalculate($get, $set);
                            })
                            ->label('Jumlah Produk'),

                        Toggle::make('is_paid')
                            ->label('Sudah di bayar ?')
                            ->inline()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    $set('proof', null);
                                }
                            })
                    ])
                    ->collapsible()
                    ->columns(2),

                Section::make('Bukti Pembayaran')
                    ->schema([
                        FileUpload::make('proof')
                            ->label('Unggah Bukti Pembayaran')
                            ->image()
                            ->preserveFilenames()
                            ->directory('product-transactions/proofs')
                            ->maxSize(2048)
                            ->disk('public')
                            ->required(fn(callable $get) => $get('is_paid') === true),
                    ])
                    ->visible(fn(callable $get) => $get('is_paid') === true)
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Pembeli')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('produk.name')
                    ->label('Produk')
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Quantitas')
                    ->sortable(),
                TextColumn::make('booking_trx_id')
                    ->label('Kode Booking')
                    ->sortable(),
                IconColumn::make('is_paid')
                    ->label('Lunas')
                    ->boolean(),
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
            'index' => ListProductTransactions::route('/'),
            'create' => CreateProductTransaction::route('/create'),
            'edit' => EditProductTransaction::route('/{record}/edit'),
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
