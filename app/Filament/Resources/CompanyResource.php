<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Admin';

    protected static ?string $modelLabel = 'Empresa';

    protected static ?string $pluralModelLabel = 'Empresas';

    protected static ?int $navigationSort = 12;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la empresa')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color principal'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Imagen corporativa')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo (cabecera)')
                            ->helperText('Acepta SVG, PNG, JPG, WEBP.')
                            ->disk('public')
                            ->directory('companies/logos')
                            ->visibility('public')
                            ->rules(['file', 'max:2048'])
                            ->acceptedFileTypes([
                                'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp', 'image/gif',
                                'text/xml', 'text/plain', 'text/html', 'application/xml',
                            ]),
                        Forms\Components\FileUpload::make('icon_path')
                            ->label('Icono (favicon)')
                            ->helperText('Acepta SVG, PNG, JPG, WEBP.')
                            ->disk('public')
                            ->directory('companies/icons')
                            ->visibility('public')
                            ->rules(['file', 'max:1024'])
                            ->acceptedFileTypes([
                                'image/svg+xml', 'image/png', 'image/jpeg', 'image/webp', 'image/gif',
                                'text/xml', 'text/plain', 'text/html', 'application/xml',
                            ]),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('icon_path')
                    ->label('Icono')
                    ->disk('public')
                    ->circular()
                    ->size(32),
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->height(30),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
