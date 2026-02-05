<?php

namespace App\Filament\Resources\ProductTransactions;
# Import semua class yang dibutuhkan
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
use Filament\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Laravel\Pail\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/* TODO: - KOMENTARI, Per baris mengenai fungsi dan penjelasan Code
*/

class ProductTransactionResource extends Resource
{
    protected static ?string $model = ProductTransaction::class; //Model yang digunakan oleh resource ini

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static function rupiah(int $value = 0): string //Format angka ke dalam format Rupiah
    {
        return number_format($value, 0, ',', '.');
    }

    protected static function toInt(?string $value): int //Mengubah format Rupiah kembali ke integer
    {
        return (int) str_replace('.', '', $value ?? '0');
    }

    # Fungsi untuk menghitung ulang total harga berdasarkan produk, jumlah, dan diskon
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
                # Informasi Pembeli
                Section::make('Informasi Pembeli')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nama Pembeli')
                            ->regex('/^[a-zA-Z\s]+$/') //validasi agar nama tidak numeric dan simbol
                            ->validationMessages([
                                'regex' => 'Nama tidak boleh mengandung angka atau simbol.',
                            ]),
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
                            ->default(fn() => ProductTransaction::generateUniqueTrxId()) //Generate Booking ID unik
                            ->nullable()
                            ->readOnly()
                            ->dehydrated() //agar tetap tersimpan di database meskipun readOnly
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

                # Detail Pembayaran
                Section::make('Detail Pembayaran')
                    ->schema([
                        Select::make('produk_id')
                            ->label('Pilih Produk yang Dibeli')
                            ->relationship('produk', 'name')
                            ->live() //agar langsung merespon perubahan tanpa submit
                            ->afterStateUpdated(function ($state, callable $get, callable $set) { // Setelah produk diubah

                                if (!$state) { // jika tidak ada produk yang dipilih
                                    return;
                                }

                                $produk = Produk::find($state);

                                if (! $produk) { // jika produk tidak ditemukan
                                    return;
                                }

                                $set('shoe_size', null); // reset ukuran sepatu
                                $set('quantity', 1); // reset jumlah produk
                                $set('discount_amount', 0); // reset diskon

                                static::recalculate($get, $set); // memanggil fungsi recalculate untuk menghitung ulang
                            })
                            ->required(),

                        Select::make('shoe_size')
                            ->label('Ukuran Sepatu')
                            ->required()
                            ->options( //mengambil opsi ukuran sepatu berdasarkan produk yang dipilih
                                fn(callable $get) =>
                                ProdukSize::getSize($get('produk_id'))
                            )
                            ->hidden(fn(callable $get) => !$get('produk_id')) //sembunyikan jika produk belum dipilih
                            ->reactive(), // agar langsung merespon perubahan tanpa submit

                        TextInput::make('promo_code_input')
                            ->label('Kode Promo')
                            ->dehydrated(false)
                            ->live(debounce: 500) //tunggu 500ms setelah user berhenti mengetik
                            ->afterStateUpdated(function (?string $state, callable $set, callable $get) { // setelah kode promo diubah

                                if (blank($state)) { //jika kode promo kosong
                                    $set('promo_code_id', null); // reset promo_code_id
                                    $set('discount_amount', 0); // reset diskon
                                    static::recalculate($get, $set); // memanggil fungsi recalculate untuk menghitung ulang
                                    return;
                                }

                                $promo = PromoCode::where('code', $state)->first(); // mencari kode promo di database

                                if ($promo) { // jika kode promo ditemukan
                                    $set('promo_code_id', $promo->id);
                                    $set('discount_amount', self::rupiah($promo->discount_amount)); // set diskon sesuai kode promo
                                } else { // jika kode promo tidak ditemukan
                                    $set('promo_code_id', null);
                                    $set('discount_amount', self::rupiah(0));
                                }

                                static::recalculate($get, $set);
                            }),

                        Hidden::make('promo_code_id')->nullable(), // menyimpan ID kode promo yang dipilih
                        Hidden::make('product_price')->default(0), // menyimpan harga produk

                        TextInput::make('sub_total_amount')
                            ->label('Total Harga Sebelum Diskon')
                            ->readOnly()
                            ->prefix('Rp.')
                            ->dehydrateStateUsing(fn($state) => static::toInt($state)), // mengubah format rupiah ke integer saat disimpan

                        TextInput::make('discount_amount')
                            ->label('Total Diskon')
                            ->readOnly()
                            ->prefix('Rp')
                            ->dehydrateStateUsing(fn($state) => static::toInt($state)), // mengubah format rupiah ke integer saat disimpan

                        TextInput::make('grand_total_amount')
                            ->label('Total Harga')
                            ->readOnly()
                            ->prefix('Rp.')
                            ->dehydrateStateUsing(fn($state) => static::toInt($state)), // mengubah format rupiah ke integer saat disimpan

                        TextInput::make('quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {

                                // Cek Angka Negatif/ Nol
                                if ($state < 1) {
                                    $set('quantity', 1);

                                    Notification::make()
                                        ->title('Jumlah tidak valid')
                                        ->body('Jumlah produk tidak boleh kurang dari 1.')
                                        ->danger()
                                        ->send();

                                    static::recalculate($get, $set);
                                    return;
                                }

                                $produkId = $get('produk_id');

                                if (! $produkId) {
                                    return;
                                }

                                $produk = Produk::find($produkId);

                                if (! $produk) {
                                    return;
                                }

                                // Cek Stok
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
                            ->default(false)
                            ->reactive()
                            ->disabled(fn($record) => $record?->is_paid) //jika sudah Lunas, tidak bisa diubah lagi
                            ->dehydrated()
                            ->afterStateUpdated(function ($state, callable $set) { // setelah status is_paid diubah
                                if (! $state) {
                                    $set('proof', null);
                                }
                            })
                    ])
                    ->disabled(fn($record) => $record?->is_paid) // seluruh section detail pembayaran tidak bisa diubah jika sudah Lunas
                    ->collapsible()
                    ->columns(2),

                # Bukti Pembayaran
                Section::make('Bukti Pembayaran')
                    ->schema([
                        FileUpload::make('proof')
                            ->label('Unggah Bukti Pembayaran')
                            ->image()
                            ->preserveFilenames()
                            ->directory('product-transactions/proofs')
                            ->maxSize(2048)
                            ->disk('public')
                            ->required(fn(callable $get) => $get('is_paid') === true), // wajib diisi jika status is_paid = true
                    ])
                    ->visible(fn(callable $get) => $get('is_paid') === true) // hanya tampil jika status is_paid = true
                    ->disabled(fn($record) => $record?->is_paid) // tidak bisa diubah jika sudah Lunas
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('produk.thumbnail')
                    ->label('Gambar Produk'),
                TextColumn::make('name')
                    ->label('Pembeli')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('produk.name')
                    ->label('Produk')
                    ->sortable(),
                TextColumn::make('booking_trx_id')
                    ->label('Kode Booking')
                    ->sortable(),
                TextColumn::make('grand_total_amount')
                    ->label('Harga total')
                    ->money('idr', locale: 'id'),
                TextColumn::make('is_paid')
                    ->label('Status Pembayaran')
                    ->badge()
                    ->formatStateUsing(fn(bool $state): string => $state // format tampilan status pembayaran
                        ? "Sudah Lunas"
                        : "Belum Lunas")
                    ->color(fn(bool $state): string => $state // warna badge berdasarkan status pembayaran
                        ? "success"
                        : "danger")
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),

                // fitur approve
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('succes')
                    ->visible(fn($record) => ! $record->is_paid) //hanya tampil jika status belum Lunas
                    ->requiresConfirmation()
                    ->modalHeading('Approve Pembayaran')
                    ->modalDescription('Unggah bukti pembayaran sebelum melunasi')

                    ->action(function ($record) {

                        # Kondisi jika mengklik approve, tapi bukti belum diunggah, maka di arahkan ke form Edit.
                        if (! $record->proof) {
                            Notification::make()
                                ->title('Upload bukti pembayaran terlebih dahulu')
                                ->warning()
                                ->send();

                            return redirect(
                                static::getUrl('edit', ['record' => $record])
                            );
                        }

                        // Ada bukti, status Lunas (approve)
                        $record->update([
                            'is_paid' => true,
                        ]);

                        Notification::make()
                            ->title('Transaksi berhasil di-approve')
                            ->success()
                            ->send();
                    })
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

    # Override query untuk mengabaikan global scope SoftDeletingScope
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
