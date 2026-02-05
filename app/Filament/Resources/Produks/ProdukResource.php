<?php

namespace App\Filament\Resources\Produks;

use App\Filament\Resources\Produks\Pages\CreateProduk;
use App\Filament\Resources\Produks\Pages\EditProduk;
use App\Filament\Resources\Produks\Pages\ListProduks;
use App\Filament\Resources\Produks\Schemas\ProdukForm;
use App\Filament\Resources\Produks\Tables\ProduksTable;
use App\Models\Produk;
use BackedEnum;
use BladeUI\Icons\Components\Icon;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Laravel\Pail\File;
use SebastianBergmann\CodeCoverage\Report\Text;

class ProdukResource extends Resource
{
    protected static ?string $model = Produk::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                # Product Information
                Fieldset::make('Informasi Produk')
                    ->schema([
                        Grid::make(1)->schema([
                            TextInput::make('name')
                                ->unique(ignoreRecord: true) //Agar Nama menjadi Unik, tidak duplikat.
                                ->required()
                                ->label('Nama Produk')
                                ->rules ([  //Custom rules
                                    'string',
                                    'min:5',
                                    'max:255'
                                ])
                                ->validationMessages([ //Pesan Khusus
                                    'unique' => 'Nama Produk sudah ada',
                                    'min'    => 'Nama Produk terlalu pendek (min 5 huruf/karakter)',
                                    'max'    => 'Nama Produk terlalu panjang'
                                ]),

                            TextInput::make('price')
                                ->prefix('Rp')
                                ->numeric()
                                ->required()
                                ->label('Harga')
                                ->minValue(10000), //Harga tidak boleh lebih rendah dari 10.000
                        ]),

                        FileUpload::make('thumbnail')
                            ->image()
                            ->directory('produks/thumbnails')
                            ->maxSize(1024)
                            ->required()
                            ->columnSpanFull()
                            ->label('Thumbnail'),

                        Repeater::make('photos')
                            ->label('Produk Photos')
                            ->schema([
                                FileUpload::make('photo')
                                    ->directory('produks/photos')
                                    ->required(),
                            ])
                            ->columnSpanFull()
                            ->addActionLabel('Add to photos')
                            ->reorderableWithButtons(),
                    ])
                    ->columnSpanFull()
                    ->maxWidth('4xl'),

                        Fieldset::make('Pilih Size')
                            ->schema([
                                Repeater::make('sizes')
                                    ->relationship()
                                    ->schema([
                                        Select::make('size')
                                            ->required()
                                            ->label('Tambahkan ukuran produk yang lainnya')
                                            ->options(
                                                collect(range(30, 45)) //Pemilihan antara 30-45
                                                    ->mapWithKeys(fn($size) => [$size => (string) $size])
                                                    ->toArray()
                                            )
                                            ->distinct() //checking antar item repeater agar tidak ada data duplikat 
                                            ->validationMessages([
                                                'distinct' => 'Ukuran tidak boleh duplikat'
                                            ])
                                    ])
                                    ->addActionLabel('Tambahkan ukuran Yang lainnya')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->maxWidth('2xl'),

                # Informasi Tambahan
                Fieldset::make('Informasi Tambahan')
                    ->schema([

                        Grid::make(2)->schema([
                            Textarea::make('about')
                                ->label('Deskripsi Produk')
                                ->rows(4),

                            Select::make('is_popular')
                                ->label('Produk Populer ?')
                                ->options([
                                    1 => 'Populer',
                                    0 => 'Tidak',
                                ]),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('category_id')
                                ->relationship('category', 'name')
                                ->required(),

                            Select::make('brand_id')
                                ->relationship('brand', 'name'),
                        ]),

                        TextInput::make('stock')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->suffix('pcs'),
                    ])
                    ->columnSpanFull()
                    ->maxWidth('4xl'),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail')
                ->label('Thumbnail'),
                TextColumn::make('name')
                ->label('Nama Produk')
                ->searchable()
                ->sortable(),
                TextColumn::make('price')
                ->label('Harga')
                ->money('idr', locale:'id')
                ->sortable(),
                TextColumn::make('category.name')
                ->label('Kategori')
                ->sortable(),
                TextColumn::make('brand.name')
                ->label('Brand')
                ->sortable(),
                TextColumn::make('stock')
                ->label('Stok')
                ->sortable(),
                TextColumn::make('is_popular')
                ->label('Status Produk')
                ->badge()
                ->formatStateUsing(fn (bool $state):string => $state
                        ? "Populer"
                        : "Tidak Populer")
                    ->color(fn (bool $state):string => $state
                        ? "success"
                        : "danger")
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
            'index' => ListProduks::route('/'),
            'create' => CreateProduk::route('/create'),
            'edit' => EditProduk::route('/{record}/edit'),
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
