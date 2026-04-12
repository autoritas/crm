<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InfonaliaDataResource\Pages;
use App\Models\InfonaliaData;
use App\Models\InfonaliaStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InfonaliaDataResource extends Resource
{
    protected static ?string $model = InfonaliaData::class;

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationIcon = 'heroicon-o-phone';

    protected static ?string $navigationLabel = 'Infonalia';

    protected static ?string $modelLabel = 'Registro Infonalia';

    protected static ?string $pluralModelLabel = 'Infonalia';

    protected static ?int $navigationSort = 7;

    protected static function getCompanyId(): int
    {
        return (int) session('current_company_id', 1);
    }

    protected static function getDefaultDecisionId(): ?int
    {
        $companyId = static::getCompanyId();

        return InfonaliaStatus::where('id_company', $companyId)
            ->where('is_default_filter', true)
            ->value('id');
    }

    public static function form(Form $form): Form
    {
        $companyId = static::getCompanyId();

        return $form
            ->schema([
                Forms\Components\Hidden::make('id_company')
                    ->default($companyId),

                Forms\Components\Section::make('Datos principales')
                    ->schema([
                        Forms\Components\Select::make('id_decision')
                            ->label('Decision')
                            ->options(
                                InfonaliaStatus::where('id_company', $companyId)
                                    ->pluck('status', 'id')
                            )
                            ->default(static::getDefaultDecisionId())
                            ->searchable(),
                        Forms\Components\DatePicker::make('fecha_publicacion')
                            ->label('Fecha publicacion'),
                        Forms\Components\TextInput::make('cliente')
                            ->label('Cliente (texto original)')
                            ->maxLength(255)
                            ->helperText('Al guardar se vinculara automaticamente con la tabla de clientes.'),
                        Forms\Components\Select::make('id_client')
                            ->label('Cliente vinculado')
                            ->relationship(
                                'client',
                                'name',
                                fn ($query) => $query->where('id_company', session('current_company_id', 1))
                            )
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\Hidden::make('id_company')
                                    ->default(fn () => session('current_company_id', 1)),
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                            ]),
                        Forms\Components\TextInput::make('provincia')
                            ->label('Provincia')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('presupuesto')
                            ->label('Presupuesto')
                            ->numeric()
                            ->prefix('€'),
                        Forms\Components\DatePicker::make('presentacion')
                            ->label('Presentacion'),
                        Forms\Components\Textarea::make('resumen_objeto')
                            ->label('Resumen / Objeto')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('perfil_contratante')
                            ->label('Perfil contratante')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Datos adicionales')
                    ->schema([
                        Forms\Components\DateTimePicker::make('fecha_ingreso')
                            ->label('Fecha ingreso'),
                        Forms\Components\TextInput::make('kanboard_task_id')
                            ->label('Kanboard Task ID')
                            ->numeric(),
                    ])->columns(2),

                Forms\Components\Section::make('Analisis IA')
                    ->schema([
                        Forms\Components\Select::make('id_ia_decision')
                            ->label('Decision IA')
                            ->options(
                                InfonaliaStatus::where('id_company', $companyId)
                                    ->pluck('status', 'id')
                            )
                            ->searchable(),
                        Forms\Components\Textarea::make('ia_motivo')
                            ->label('Motivo IA')
                            ->rows(3),
                        Forms\Components\DateTimePicker::make('ia_fecha')
                            ->label('Fecha analisis IA'),
                        Forms\Components\Toggle::make('revisado_humano')
                            ->label('Revisado por humano'),
                        Forms\Components\DateTimePicker::make('revisado_fecha')
                            ->label('Fecha revision'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $companyId = static::getCompanyId();

        $statuses = InfonaliaStatus::where('id_company', $companyId)->get();
        $statusOptions = $statuses->pluck('status', 'id')->toArray();
        $statusColors = $statuses->pluck('color', 'id')->toArray();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('id_company', $companyId))
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(100)
            ->paginationPageOptions([10, 25, 50, 100])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->persistColumnSearchesInSession()
            ->recordClasses(fn (InfonaliaData $record) =>
                'infonalia-row-' . ($record->id_decision ?? 'none')
            )
            ->columns([
                Tables\Columns\SelectColumn::make('id_decision')
                    ->label('Decision')
                    ->options($statusOptions)
                    ->selectablePlaceholder(false)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->cliente)
                    ->description(fn ($record) => $record->client ? null : 'Sin vincular')
                    ->color(fn ($record) => $record->id_client ? null : 'warning')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('resumen_objeto')
                    ->label('Objeto')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->resumen_objeto)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('provincia')
                    ->label('Provincia')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('presupuesto')
                    ->label('Presupuesto')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('fecha_publicacion')
                    ->label('Publicacion')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('presentacion')
                    ->label('Presentacion')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\SelectColumn::make('id_ia_decision')
                    ->label('IA')
                    ->options($statusOptions)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ia_motivo')
                    ->label('Motivo IA')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->ia_motivo)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('revisado_humano')
                    ->label('Revisado')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('perfil_contratante')
                    ->label('Perfil')
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->perfil_contratante)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(20)
                    ->url(fn ($record) => $record->url, true)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fecha_ingreso')
                    ->label('Ingreso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kanboard_task_id')
                    ->label('Kanboard')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('id_decision')
                    ->label('Decision')
                    ->placeholder('Decision: Todos')
                    ->options($statusOptions)
                    ->default(fn () => static::getDefaultDecisionId()),
                Tables\Filters\SelectFilter::make('id_ia_decision')
                    ->label('Decision IA')
                    ->placeholder('IA: Todos')
                    ->options($statusOptions),
                Tables\Filters\TernaryFilter::make('revisado_humano')
                    ->label('Revisado')
                    ->placeholder('Revisado: Todos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('cambiar_decision')
                        ->label('Cambiar decision')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('id_decision')
                                ->label('Nuevo estado')
                                ->options($statusOptions)
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                            $records->each(fn ($record) => $record->update([
                                'id_decision' => $data['id_decision'],
                            ]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInfonaliaData::route('/'),
            'create' => Pages\CreateInfonaliaData::route('/create'),
            'edit' => Pages\EditInfonaliaData::route('/{record}/edit'),
            'import' => Pages\ImportInfonaliaData::route('/import'),
        ];
    }
}
