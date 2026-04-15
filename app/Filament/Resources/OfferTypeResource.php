<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferTypeResource\Pages;
use App\Models\OfferType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfferTypeResource extends Resource
{
    protected static ?string $model = OfferType::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $modelLabel = 'Tipo Licitacion';

    protected static ?string $pluralModelLabel = 'Tipos Licitacion';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->default(fn () => session('current_company_id', 1))
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        $companyId = session('current_company_id', 1);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('company_id', $companyId))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfferTypes::route('/'),
            'create' => Pages\CreateOfferType::route('/create'),
            'edit' => Pages\EditOfferType::route('/{record}/edit'),
        ];
    }
}
