<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompetitorResource\Pages;
use App\Models\Competitor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompetitorResource extends Resource
{
    protected static ?string $model = Competitor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Competidor';

    protected static ?string $pluralModelLabel = 'Competidores';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del competidor')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('id_opportunity')
                            ->label('Oportunidad')
                            ->relationship('opportunity', 'title')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Textarea::make('contact_info')
                            ->label('Informacion de contacto')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('strengths')
                            ->label('Fortalezas')
                            ->rows(2),
                        Forms\Components\Textarea::make('weaknesses')
                            ->label('Debilidades')
                            ->rows(2),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('opportunity.title')
                    ->label('Oportunidad')
                    ->sortable(),
                Tables\Columns\TextColumn::make('valuations_count')
                    ->label('Valoraciones')
                    ->counts('valuations'),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompetitors::route('/'),
            'create' => Pages\CreateCompetitor::route('/create'),
            'edit' => Pages\EditCompetitor::route('/{record}/edit'),
        ];
    }
}
