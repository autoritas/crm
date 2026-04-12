<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?int $navigationSort = 8;

    protected static function getCompanyId(): int
    {
        return (int) session('current_company_id', 1);
    }

    public static function form(Form $form): Form
    {
        $companyId = static::getCompanyId();

        return $form
            ->schema([
                Forms\Components\Hidden::make('id_company')
                    ->default($companyId),

                Forms\Components\Section::make('Datos del cliente')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('cif')
                            ->label('CIF / NIF')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('sector')
                            ->label('Sector')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('province')
                            ->label('Provincia')
                            ->maxLength(100),
                        Forms\Components\Textarea::make('address')
                            ->label('Direccion')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('contact_name')
                            ->label('Persona de contacto')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Telefono')
                            ->tel()
                            ->maxLength(30),
                    ])->columns(3),

                Forms\Components\Section::make('Notas')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $companyId = static::getCompanyId();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('id_company', $companyId))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cif')
                    ->label('CIF')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sector')
                    ->label('Sector')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('province')
                    ->label('Provincia')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contacto')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('aliases_count')
                    ->label('Sinonimos')
                    ->counts('aliases')
                    ->sortable(),
                Tables\Columns\TextColumn::make('infonalia_data_count')
                    ->label('Registros')
                    ->counts('infonaliaData')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
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
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
