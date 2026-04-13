<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferStatusResource\Pages;
use App\Models\OfferStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfferStatusResource extends Resource
{
    protected static ?string $model = OfferStatus::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $modelLabel = 'Estado Oferta';

    protected static ?string $pluralModelLabel = 'Estados Oferta';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('id_company')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->default(fn () => session('current_company_id', 1))
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->label('Estado')
                    ->required()
                    ->maxLength(255),
                Forms\Components\ColorPicker::make('color')
                    ->label('Color')
                    ->required()
                    ->default('#6b7280'),
                Forms\Components\Toggle::make('is_default_filter')
                    ->label('Filtro por defecto')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        $companyId = session('current_company_id', 1);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('id_company', $companyId))
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->html()
                    ->formatStateUsing(fn ($state, $record) =>
                        '<span style="display:inline-flex;align-items:center;gap:6px;"><span style="width:12px;height:12px;border-radius:50%;background:' . $record->color . ';display:inline-block;"></span>' . e($state) . '</span>'
                    ),
                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),
                Tables\Columns\IconColumn::make('is_default_filter')
                    ->label('Filtro defecto')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfferStatuses::route('/'),
            'create' => Pages\CreateOfferStatus::route('/create'),
            'edit' => Pages\EditOfferStatus::route('/{record}/edit'),
        ];
    }
}
