<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferResource\Pages;
use App\Models\Offer;
use App\Models\Opportunity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Oferta';

    protected static ?string $pluralModelLabel = 'Ofertas';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la oferta')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Titulo')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->maxLength(255),
                        Forms\Components\Select::make('id_opportunity')
                            ->label('Oportunidad')
                            ->relationship('opportunity', 'title')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'draft' => 'Borrador',
                                'submitted' => 'Presentada',
                                'accepted' => 'Aceptada',
                                'rejected' => 'Rechazada',
                                'withdrawn' => 'Retirada',
                            ])
                            ->default('draft')
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripcion')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Importe')
                            ->numeric()
                            ->prefix('€'),
                        Forms\Components\DatePicker::make('submitted_at')
                            ->label('Fecha de presentacion'),
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
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Titulo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('opportunity.title')
                    ->label('Oportunidad')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'withdrawn' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'submitted' => 'Presentada',
                        'accepted' => 'Aceptada',
                        'rejected' => 'Rechazada',
                        'withdrawn' => 'Retirada',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Importe')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Presentada')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'submitted' => 'Presentada',
                        'accepted' => 'Aceptada',
                        'rejected' => 'Rechazada',
                        'withdrawn' => 'Retirada',
                    ]),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'edit' => Pages\EditOffer::route('/{record}/edit'),
        ];
    }
}
