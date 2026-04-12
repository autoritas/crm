<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InfonaliaStatusResource\Pages;
use App\Models\InfonaliaStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InfonaliaStatusResource extends Resource
{
    protected static ?string $model = InfonaliaStatus::class;

    protected static ?string $navigationGroup = 'Admin';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $modelLabel = 'Estado Infonalia';

    protected static ?string $pluralModelLabel = 'Estados Infonalia';

    protected static ?int $navigationSort = 11;

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
                    ->maxLength(255)
                    ->unique(
                        table: 'infonalia_statuses',
                        column: 'status',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, $get) => $rule->where('id_company', $get('id_company')),
                    ),
                Forms\Components\ColorPicker::make('color')
                    ->label('Color')
                    ->required()
                    ->default('#6b7280'),
                Forms\Components\Toggle::make('generates_offer')
                    ->label('Genera oferta')
                    ->helperText('Si se activa, al asignar este estado se creara una oferta automaticamente en el futuro.')
                    ->default(false),
                Forms\Components\Toggle::make('is_default_filter')
                    ->label('Filtro por defecto')
                    ->helperText('Este estado sera el filtro activo por defecto al entrar en el listado de Infonalia.')
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
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => $state)
                    ->html()
                    ->formatStateUsing(fn ($state, $record) =>
                        '<span style="display:inline-flex;align-items:center;gap:6px;">
                            <span style="width:12px;height:12px;border-radius:50%;background:' . $record->color . ';display:inline-block;"></span>
                            ' . e($state) . '
                        </span>'
                    ),
                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),
                Tables\Columns\IconColumn::make('generates_offer')
                    ->label('Genera oferta')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_default_filter')
                    ->label('Filtro defecto')
                    ->boolean(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa'),
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
            'index' => Pages\ListInfonaliaStatuses::route('/'),
            'create' => Pages\CreateInfonaliaStatus::route('/create'),
            'edit' => Pages\EditInfonaliaStatus::route('/{record}/edit'),
        ];
    }
}
