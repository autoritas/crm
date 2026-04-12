<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OpportunityResource\Pages;
use App\Models\Opportunity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Oportunidad';

    protected static ?string $pluralModelLabel = 'Oportunidades';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la oportunidad')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Titulo')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'new' => 'Nueva',
                                'in_progress' => 'En curso',
                                'won' => 'Ganada',
                                'lost' => 'Perdida',
                                'cancelled' => 'Cancelada',
                            ])
                            ->default('new')
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripcion')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('source')
                            ->label('Origen')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('estimated_value')
                            ->label('Valor estimado')
                            ->numeric()
                            ->prefix('€'),
                        Forms\Components\DatePicker::make('deadline')
                            ->label('Fecha limite'),
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
                Tables\Columns\TextColumn::make('title')
                    ->label('Titulo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'info',
                        'in_progress' => 'warning',
                        'won' => 'success',
                        'lost' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new' => 'Nueva',
                        'in_progress' => 'En curso',
                        'won' => 'Ganada',
                        'lost' => 'Perdida',
                        'cancelled' => 'Cancelada',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->searchable(),
                Tables\Columns\TextColumn::make('estimated_value')
                    ->label('Valor estimado')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('deadline')
                    ->label('Fecha limite')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('offers_count')
                    ->label('Ofertas')
                    ->counts('offers'),
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
                        'new' => 'Nueva',
                        'in_progress' => 'En curso',
                        'won' => 'Ganada',
                        'lost' => 'Perdida',
                        'cancelled' => 'Cancelada',
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
            'index' => Pages\ListOpportunities::route('/'),
            'create' => Pages\CreateOpportunity::route('/create'),
            'edit' => Pages\EditOpportunity::route('/{record}/edit'),
        ];
    }
}
