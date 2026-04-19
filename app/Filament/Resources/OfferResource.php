<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferResource\Pages;
use App\Models\Client;
use App\Models\Offer;
use App\Models\OfferBusinessLine;
use App\Models\OfferClientActivity;
use App\Models\OfferFormula;
use App\Models\OfferStatus;
use App\Models\OfferType;
use App\Models\OfferWorkflow;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $navigationGroup = 'Ofertas';

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Ofertas';

    protected static ?string $modelLabel = 'Oferta';

    protected static ?string $pluralModelLabel = 'Ofertas';

    protected static ?int $navigationSort = 2;

    protected static function getCompanyId(): int
    {
        return (int) session('current_company_id', 1);
    }

    protected static function getDefaultStatusId(): ?int
    {
        return OfferStatus::where('company_id', static::getCompanyId())
            ->where('is_default_filter', true)
            ->value('id');
    }

    public static function form(Form $form): Form
    {
        $cid = static::getCompanyId();

        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')->default($cid),

                Forms\Components\Tabs::make('Oferta')
                    ->tabs([

                        // === TAB GENERAL ===
                        Forms\Components\Tabs\Tab::make('General')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('codigo_proyecto')
                                        ->label('Codigo proyecto')->maxLength(20),
                                    Forms\Components\Select::make('id_offer_status')
                                        ->label('Estado')
                                        ->options(OfferStatus::where('company_id', $cid)->pluck('status', 'id'))
                                        ->default(static::getDefaultStatusId())->searchable(),
                                    Forms\Components\Select::make('id_workflow')
                                        ->label('Fase')
                                        ->options(OfferWorkflow::where('company_id', $cid)->orderBy('sort_order')->pluck('name', 'id'))
                                        ->searchable(),
                                ]),
                                Forms\Components\TextInput::make('proyecto')
                                    ->label('Proyecto')->maxLength(4096)->columnSpanFull(),
                                Forms\Components\Textarea::make('objeto')
                                    ->label('Objeto')->rows(3)->columnSpanFull(),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('cliente')
                                        ->label('Cliente (texto)')->maxLength(512),
                                    Forms\Components\Select::make('id_client')
                                        ->label('Cliente normalizado')
                                        ->relationship('client', 'name', fn ($query) => $query->where('company_id', $cid))
                                        ->searchable()->preload(),
                                    Forms\Components\TextInput::make('provincia')
                                        ->label('Provincia')->maxLength(100),
                                ]),
                                Forms\Components\Grid::make(4)->schema([
                                    Forms\Components\Select::make('sector')
                                        ->label('Sector')
                                        ->options(['Público' => 'Público', 'Privado' => 'Privado']),
                                    Forms\Components\Select::make('id_offer_type')
                                        ->label('Tipo licitacion')
                                        ->options(OfferType::where('company_id', $cid)->pluck('name', 'id'))
                                        ->searchable(),
                                    Forms\Components\Select::make('id_business_line')
                                        ->label('Linea negocio')
                                        ->options(OfferBusinessLine::where('company_id', $cid)->pluck('name', 'id'))
                                        ->searchable(),
                                    Forms\Components\Select::make('id_client_activity')
                                        ->label('Actividad cliente')
                                        ->options(OfferClientActivity::where('company_id', $cid)->pluck('name', 'id'))
                                        ->searchable(),
                                ]),
                                Forms\Components\Grid::make(4)->schema([
                                    Forms\Components\Select::make('temperatura')
                                        ->label('Temperatura')
                                        ->options(['frio' => 'Frio', 'templado' => 'Templado', 'caliente' => 'Caliente']),
                                    Forms\Components\Select::make('renovable')
                                        ->label('Renovable')
                                        ->options(['Si' => 'Si', 'No' => 'No', 'Desconocido' => 'Desconocido']),
                                    Forms\Components\Select::make('fidelizacion')
                                        ->label('Fidelizacion')
                                        ->options(['Nuevo' => 'Nuevo', 'Cliente' => 'Cliente', 'Desconocido' => 'Desconocido']),
                                    Forms\Components\TextInput::make('responsable')
                                        ->label('Responsable')->numeric(),
                                ]),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('url')
                                        ->label('URL')->url()->maxLength(2083)->columnSpan(2),
                                    Forms\Components\TextInput::make('kanboard_task')
                                        ->label('Kanboard')->maxLength(255),
                                ]),
                                Forms\Components\Textarea::make('notas')
                                    ->label('Notas')->rows(2)->columnSpanFull(),
                            ]),

                        // === TAB IMPORTES Y FECHAS ===
                        Forms\Components\Tabs\Tab::make('Importes y Fechas')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Forms\Components\Section::make('Importes')->schema([
                                    Forms\Components\TextInput::make('importe_licitacion')
                                        ->label('Importe licitacion')->numeric()->prefix('€'),
                                    Forms\Components\TextInput::make('importe_estimado')
                                        ->label('Importe estimado')->numeric()->prefix('€'),
                                    Forms\Components\TextInput::make('duracion_meses')
                                        ->label('Duracion (meses)')->numeric(),
                                ])->columns(3),
                                Forms\Components\Section::make('Fechas del ciclo')->schema([
                                    Forms\Components\DatePicker::make('fecha_anuncio')->label('Anuncio'),
                                    Forms\Components\DatePicker::make('fecha_publicacion')->label('Publicacion'),
                                    Forms\Components\DatePicker::make('fecha_presentacion')->label('Presentacion'),
                                    Forms\Components\DatePicker::make('fecha_adjudicacion')->label('Adjudicacion'),
                                    Forms\Components\DatePicker::make('fecha_formalizacion')->label('Formalizacion'),
                                    Forms\Components\DatePicker::make('fecha_fin_contrato')->label('Fin contrato'),
                                ])->columns(3),
                            ]),

                        // === TAB CRITERIOS PLIEGO ===
                        Forms\Components\Tabs\Tab::make('Criterios')
                            ->icon('heroicon-o-scale')
                            ->schema([
                                Forms\Components\Grid::make(4)->schema([
                                    Forms\Components\TextInput::make('peso_economica')
                                        ->label('Peso economica (%)')->numeric()->suffix('%'),
                                    Forms\Components\TextInput::make('peso_tecnica')
                                        ->label('Peso tecnica (%)')->numeric()->suffix('%'),
                                    Forms\Components\TextInput::make('peso_objetiva_real')
                                        ->label('Peso objetiva real (%)')->numeric()->suffix('%'),
                                    Forms\Components\TextInput::make('peso_objetiva_fake')
                                        ->label('Peso objetiva fake (%)')->numeric()->suffix('%'),
                                ]),
                                Forms\Components\Select::make('id_formula')
                                    ->label('Formula de valoracion')
                                    ->options(OfferFormula::where('company_id', $cid)->pluck('name', 'id'))
                                    ->searchable(),
                            ]),

                        // === TAB COMPETIDORES ===
                        Forms\Components\Tabs\Tab::make('Competidores')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Forms\Components\Repeater::make('offerCompetitors')
                                    ->relationship()
                                    ->label('')
                                    ->schema([
                                        Forms\Components\Grid::make(4)->schema([
                                            Forms\Components\Select::make('id_competitor')
                                                ->label('Competidor')
                                                ->relationship(
                                                    'competitor',
                                                    'name',
                                                    fn ($query) => $query->where('company_id', session('current_company_id', 1))
                                                )
                                                ->searchable()
                                                ->preload()
                                                ->createOptionForm([
                                                    Forms\Components\Hidden::make('company_id')
                                                        ->default(fn () => session('current_company_id', 1)),
                                                    Forms\Components\TextInput::make('name')
                                                        ->label('Nombre del competidor')
                                                        ->required()->maxLength(255),
                                                    Forms\Components\TextInput::make('cif')
                                                        ->label('CIF')->maxLength(20),
                                                ])
                                                ->createOptionUsing(function (array $data) {
                                                    return \App\Models\Competitor::create($data)->id;
                                                })
                                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                    if ($state) {
                                                        $competitor = \App\Models\Competitor::find($state);
                                                        if ($competitor) {
                                                            $set('competitor_nombre', $competitor->name);
                                                        }
                                                    }
                                                })
                                                ->live(),
                                            Forms\Components\TextInput::make('competitor_nombre')
                                                ->label('Nombre (texto)')
                                                ->required()->maxLength(255)
                                                ->helperText('Se rellena automaticamente al elegir competidor'),
                                            Forms\Components\Select::make('admision')
                                                ->label('Admision')
                                                ->options(['Pendiente' => 'Pendiente', 'Admitido' => 'Admitido', 'Excluido' => 'Excluido'])
                                                ->default('Pendiente'),
                                            Forms\Components\Select::make('razon_exclusion')
                                                ->label('Razon exclusion')
                                                ->options(['Administrativa' => 'Administrativa', 'Tecnica' => 'Tecnica', 'Economica' => 'Economica', 'Desconocida' => 'Desconocida'])
                                                ->visible(fn ($get) => $get('admision') === 'Excluido'),
                                        ]),
                                        Forms\Components\Fieldset::make('Valoraciones')
                                            ->relationship('scores')
                                            ->schema([
                                                Forms\Components\TextInput::make('tecnico')->label('Tecnico')->numeric(),
                                                Forms\Components\TextInput::make('economico')->label('Economico')->numeric(),
                                                Forms\Components\TextInput::make('objetivo_real')->label('Obj. Real')->numeric(),
                                                Forms\Components\TextInput::make('objetivo_fake')->label('Obj. Fake')->numeric(),
                                                Forms\Components\TextInput::make('precio')->label('Precio')->numeric()->prefix('€'),
                                            ])->columns(5),
                                    ])
                                    ->itemLabel(fn (array $state): ?string => $state['competitor_nombre'] ?? 'Nuevo competidor')
                                    ->collapsible()
                                    ->cloneable()
                                    ->defaultItems(0)
                                    ->addActionLabel('Añadir competidor'),
                            ]),

                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $cid = static::getCompanyId();

        $statuses = OfferStatus::where('company_id', $cid)->get();
        $statusOptions = $statuses->pluck('status', 'id')->toArray();
        $statusColors = $statuses->pluck('color', 'id')->toArray();
        $typeOptions = OfferType::where('company_id', $cid)->pluck('name', 'id')->toArray();
        $workflowOptions = OfferWorkflow::where('company_id', $cid)->orderBy('sort_order')->pluck('name', 'id')->toArray();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('company_id', $cid))
            ->defaultSort('fecha_presentacion', 'desc')
            ->defaultPaginationPageOption(100)
            ->paginationPageOptions([10, 25, 50, 100])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->recordClasses(fn (Offer $record) =>
                'offer-row-' . ($record->id_offer_status ?? 'none')
            )
            ->columns([
                Tables\Columns\TextInputColumn::make('codigo_proyecto')
                    ->label('Codigo')->searchable()->sortable()->toggleable()
                    ->rules(['nullable', 'max:20']),
                Tables\Columns\SelectColumn::make('id_offer_status')
                    ->label('Estado')->options($statusOptions)
                    ->selectablePlaceholder(false)->sortable()->toggleable(),
                Tables\Columns\SelectColumn::make('id_workflow')
                    ->label('Fase')->options($workflowOptions)
                    ->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')->searchable()->sortable()->limit(25)
                    ->tooltip(fn ($record) => $record->cliente)->toggleable(),
                Tables\Columns\TextColumn::make('proyecto')
                    ->label('Proyecto')->searchable()->limit(30)
                    ->tooltip(fn ($record) => $record->proyecto)->toggleable(),
                Tables\Columns\TextColumn::make('objeto')
                    ->label('Objeto')->searchable()->limit(35)
                    ->tooltip(fn ($record) => $record->objeto)->toggleable(),
                Tables\Columns\SelectColumn::make('id_offer_type')
                    ->label('Tipo')
                    ->options(OfferType::where('company_id', static::getCompanyId())->pluck('name', 'id')->toArray())
                    ->sortable()->toggleable(),
                Tables\Columns\SelectColumn::make('sector')
                    ->label('Sector')
                    ->options(['' => '-', 'Público' => 'Público', 'Privado' => 'Privado'])
                    ->sortable()->toggleable(),
                Tables\Columns\TextInputColumn::make('fecha_presentacion')
                    ->label('Presentacion')
                    ->type('date')
                    ->sortable()
                    ->toggleable()
                    ->extraAttributes(['style' => 'min-width:130px']),
                Tables\Columns\TextColumn::make('importe_licitacion')
                    ->label('Imp.Licit.')
                    ->money('EUR', locale: 'es')
                    ->alignEnd()
                    ->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('importe_estimado')
                    ->label('Imp.Est.')
                    ->money('EUR', locale: 'es')
                    ->alignEnd()
                    ->sortable()->toggleable(),
                Tables\Columns\SelectColumn::make('temperatura')
                    ->label('Temp.')
                    ->options(['' => '-', 'frio' => 'Frio', 'templado' => 'Templado', 'caliente' => 'Caliente'])
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('offer_competitors_count')
                    ->label('Compet.')->counts('offerCompetitors')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('duracion_meses')
                    ->label('Meses')->sortable()->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')->dateTime('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('id_offer_status')
                    ->label('Estado')->placeholder('Estado: Todos')
                    ->options($statusOptions)->default(fn () => static::getDefaultStatusId()),
                Tables\Filters\SelectFilter::make('id_workflow')
                    ->label('Fase')->placeholder('Fase: Todos')
                    ->options($workflowOptions),
                Tables\Filters\SelectFilter::make('id_offer_type')
                    ->label('Tipo')->placeholder('Tipo: Todos')
                    ->options($typeOptions),
                Tables\Filters\SelectFilter::make('sector')
                    ->label('Sector')->placeholder('Sector: Todos')
                    ->options(['Público' => 'Público', 'Privado' => 'Privado']),
                Tables\Filters\SelectFilter::make('temperatura')
                    ->label('Temp.')->placeholder('Temp: Todos')
                    ->options(['frio' => 'Frio', 'templado' => 'Templado', 'caliente' => 'Caliente']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // La accion "Solicitar pliegos" vive ahora en la pagina Go/No Go,
                // que es donde el usuario se da cuenta de que los necesita.
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('cambiar_estado')
                        ->label('Cambiar estado')->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('id_offer_status')
                                ->label('Nuevo estado')->options($statusOptions)->required(),
                        ])
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records, array $data) =>
                            $records->each(fn ($r) => $r->update(['id_offer_status' => $data['id_offer_status']]))
                        )->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('cambiar_fase')
                        ->label('Cambiar fase')->icon('heroicon-o-arrows-right-left')
                        ->form([
                            Forms\Components\Select::make('id_workflow')
                                ->label('Nueva fase')->options($workflowOptions)->required(),
                        ])
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records, array $data) =>
                            $records->each(fn ($r) => $r->update(['id_workflow' => $data['id_workflow']]))
                        )->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'edit' => Pages\EditOffer::route('/{record}/edit'),
            'import' => Pages\ImportOffers::route('/import'),
        ];
    }
}
