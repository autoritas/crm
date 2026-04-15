<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompetitorCatalogResource\Pages;
use App\Models\Competitor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompetitorCatalogResource extends Resource
{
    protected static ?string $model = Competitor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $modelLabel = 'Competidor';

    protected static ?string $pluralModelLabel = 'Competidores';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('company_id')
                ->label('Empresa')
                ->relationship('company', 'name')
                ->default(fn () => session('current_company_id', 1))
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label('Nombre normalizado')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('cif')
                ->label('CIF')
                ->maxLength(20),
            Forms\Components\Textarea::make('notes')
                ->label('Notas')
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $cid = (int) session('current_company_id', 1);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('company_id', $cid))
            ->defaultSort('name')
            ->defaultPaginationPageOption(100)
            ->paginationPageOptions([25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cif')
                    ->label('CIF')
                    ->searchable(),
                Tables\Columns\TextColumn::make('aliases_count')
                    ->label('Sinonimos')
                    ->counts('aliases')
                    ->sortable(),
                Tables\Columns\TextColumn::make('offer_competitors_count')
                    ->label('En ofertas')
                    ->counts('offerCompetitors')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListCompetitorCatalog::route('/'),
            'create' => Pages\CreateCompetitorCatalog::route('/create'),
            'edit' => Pages\EditCompetitorCatalog::route('/{record}/edit'),
        ];
    }
}
