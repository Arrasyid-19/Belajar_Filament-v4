<?php

namespace App\Filament\Resources\ProductTransactions;

use App\Filament\Resources\ProductTransactions\Pages\CreateProductTransaction;
use App\Filament\Resources\ProductTransactions\Pages\EditProductTransaction;
use App\Filament\Resources\ProductTransactions\Pages\ListProductTransactions;
use App\Filament\Resources\ProductTransactions\Schemas\ProductTransactionForm;
use App\Filament\Resources\ProductTransactions\Tables\ProductTransactionsTable;
use App\Models\ProductTransaction;
use App\Models\ProdukSize;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\PromoCode;
use Filament\Forms\Components\TextInput;
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
use Filament\Livewire\Notifications;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Laravel\Pail\File;

/* TODO:
- Konfigurasi Nama Category, Kode Promo & Brands tidak boleh sama dengan yang sudah ada
- Konfigurasi Stock Produk di Transaksi
- Tampilan dari Mata Uang
- Add Fitur Approve bagi yang belum Lunas
*/

class ProductTransactionResource extends Resource
{
    protected static ?string $model = ProductTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

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
                            ->default(fn () => ProductTransaction::generateUniqueTrxId())
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
                        TextInput::make('address')
                            ->required()
                            ->maxLength(500)
                            ->label('Alamat Lengkap'),
                    ])
                    ->collapsible()
                    ->columns(2),

                    Section::make('Detail Pembayaran')
                    ->schema([
                        Select::make('produk_id')
                            ->label('Pilih Produk yang Dibeli')
                            ->relationship('produk', 'name')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {

                                if (!$state) {
                                    return;
                                }
                                $produk = Produk::find($state);
                                    if ($produk) {
                                        $set('shoe_size', null);
                                        $set('product_price', $produk->price);
                                        $set('quantity', 1);
                                        $set('sub_total_amount', $produk->price);
                                        $set('discount_amount', 0);
                                        $set('grand_total_amount', $produk->price);
                                    }
                            })
                            ->required(),

                        Select::make('shoe_size')
                            ->label('Ukuran Sepatu')
                            ->required()
                            ->options(fn (callable $get) =>
                                ProdukSize::getSize($get('produk_id'))
                            )
                            ->hidden(fn (callable $get) => !$get('produk_id'))
                            ->reactive(),
                            
                        TextInput::make('promo_code_input')
                            ->label('Kode Promo')
                            ->dehydrated(false)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get) {

                            if (blank($state)) {
                                return;
                            }

                            $promo = PromoCode::where('code', $state)->first();

                            $subTotal = (int) ($get('sub_total_amount') ?? 0);

                                if ($promo) {
                                    $set('promo_code_id', $promo->id);
                                    $set('discount_amount', $promo->discount_amount);
                                    $set(
                                        'grand_total_amount',
                                        max(0, $subTotal - $promo->discount_amount)
                                        );
                                } else {
                                    $set('promo_code_id', null);
                                    $set('discount_amount', 0);
                                    $set('grand_total_amount', $subTotal);
                                }
                            }),

                            Hidden::make('promo_code_id')->nullable(),
                            Hidden::make('product_price')->default(0),

                        TextInput::make('sub_total_amount')
                            ->label('Total Harga Sebelum Diskon')
                            ->numeric()
                            ->live()
                            ->prefix('Rp')
                            ->readOnly()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $discount = $get('discount_amount') ?? 0;
                                $set(
                                    'grand_total_amount',
                                    max(0, $state - $discount)
                                );
                            }),

                        TextInput::make('discount_amount')
                            ->label('Total Diskon')
                            ->numeric()
                            ->readOnly(),

                        TextInput::make('grand_total_amount')
                            ->numeric()
                            ->prefix('Rp')
                            ->label('Total Harga')
                            ->readOnly(),

                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {

                                $price = (int) $get('product_price') ?? 0;
                                $qty = max(1, (int) $state);

                                $subTotal = $price * $qty;

                                $set('sub_total_amount', $subTotal);

                                $discount = (int) ($get('discount_amount') ?? 0);
                                
                                $set(
                                    'grand_total_amount',
                                    max(0, $subTotal - $discount)
                                );
                            })
                            ->label('Jumlah Produk'),

                        Select::make('is_paid')
                            ->label('Status Pembayaran ?')
                            ->options([
                                1 => 'Lunas',
                                0 => 'Belum Lunas',
                            ])
                            ->required(),
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
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('proof')
                ->label('Bukti Pembayaran'),
                TextColumn::make('name')
                ->label('Nama Pembeli')
                ->searchable()
                ->sortable(),
                TextColumn::make('email')
                ->label('Akun Email')
                ->sortable(),
                TextColumn::make('address')
                ->label('Alamat')
                ->sortable(),
                TextColumn::make('quantity')
                ->label('Quantitas')
                ->sortable(),
                TextColumn::make('shoe_size')
                ->label('size')
                ->sortable(),
                IconColumn::make('is_paid')
                ->label('Lunas')
                ->boolean(),
            ])
            // ->filters([
            //     TrashedFilter::make(),
            // ])
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
}
