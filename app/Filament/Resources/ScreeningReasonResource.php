<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScreeningReasonResource\Pages;
use App\Models\ScreeningReason;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScreeningReasonResource extends Resource
{
    protected static ?string $model = ScreeningReason::class;

    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $modelLabel = 'Motivo de Cribado';

    protected static ?string $pluralModelLabel = 'Motivos de Cribado';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('company_id')
                ->label('Empresa')
                ->relationship('company', 'name')
                ->default(fn () => session('current_company_id', 1))
                ->required(),
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options(['positive' => 'Positivo', 'negative' => 'Negativo'])
                ->required(),
            Forms\Components\TextInput::make('reason')
                ->label('Motivo')
                ->required()
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        $cid = (int) session('current_company_id', 1);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('company_id', $cid))
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state) => $state === 'positive' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state) => $state === 'positive' ? 'Positivo' : 'Negativo')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
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
            'index' => Pages\ListScreeningReasons::route('/'),
            'create' => Pages\CreateScreeningReason::route('/create'),
            'edit' => Pages\EditScreeningReason::route('/{record}/edit'),
        ];
    }
}
