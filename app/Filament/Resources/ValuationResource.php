<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ValuationResource\Pages;
use App\Models\Valuation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ValuationResource extends Resource
{
    protected static ?string $model = Valuation::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Valoracion';

    protected static ?string $pluralModelLabel = 'Valoraciones';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la valoracion')
                    ->schema([
                        Forms\Components\Select::make('id_offer')
                            ->label('Oferta')
                            ->relationship('offer', 'title')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('id_competitor')
                            ->label('Competidor')
                            ->relationship('competitor', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('score')
                            ->label('Puntuacion')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.5),
                        Forms\Components\TextInput::make('criteria')
                            ->label('Criterio')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('qualitative_notes')
                            ->label('Valoracion cualitativa')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('offer.title')
                    ->label('Oferta')
                    ->sortable(),
                Tables\Columns\TextColumn::make('competitor.name')
                    ->label('Competidor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('criteria')
                    ->label('Criterio')
                    ->searchable(),
                Tables\Columns\TextColumn::make('score')
                    ->label('Puntuacion')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListValuations::route('/'),
            'create' => Pages\CreateValuation::route('/create'),
            'edit' => Pages\EditValuation::route('/{record}/edit'),
        ];
    }
}
