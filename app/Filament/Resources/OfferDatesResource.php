<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferDatesResource\Pages;
use App\Models\Offer;
use App\Models\OfferStatus;
use App\Models\OfferType;
use App\Models\OfferWorkflow;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfferDatesResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $navigationGroup = 'Ofertas';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Fechas';
    protected static ?string $modelLabel = 'Fechas Oferta';
    protected static ?string $pluralModelLabel = 'Fechas';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'offer-dates';

    protected static function getCompanyId(): int
    {
        return (int) session('current_company_id', 1);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('codigo_proyecto')->label('Codigo')->disabled(),
            Forms\Components\TextInput::make('cliente')->label('Cliente')->disabled(),
            Forms\Components\Section::make('Fechas')->schema([
                Forms\Components\DatePicker::make('fecha_anuncio')->label('Anuncio'),
                Forms\Components\DatePicker::make('fecha_publicacion')->label('Publicacion'),
                Forms\Components\DatePicker::make('fecha_presentacion')->label('Presentacion'),
                Forms\Components\DatePicker::make('fecha_adjudicacion')->label('Adjudicacion'),
                Forms\Components\DatePicker::make('fecha_formalizacion')->label('Formalizacion'),
                Forms\Components\DatePicker::make('fecha_fin_contrato')->label('Fin contrato'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        $cid = static::getCompanyId();
        $statusOptions = OfferStatus::where('id_company', $cid)->pluck('status', 'id')->toArray();
        $typeOptions = OfferType::where('id_company', $cid)->pluck('name', 'id')->toArray();
        $workflowOptions = OfferWorkflow::where('id_company', $cid)->orderBy('sort_order')->pluck('name', 'id')->toArray();
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('id_company', $cid))
            ->defaultSort('fecha_presentacion', 'desc')
            ->defaultPaginationPageOption(100)
            ->paginationPageOptions([25, 50, 100])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->persistSearchInSession()
            ->recordClasses(fn (Offer $record) => 'offer-row-' . ($record->id_offer_status ?? 'none'))
            ->columns([
                Tables\Columns\TextColumn::make('codigo_proyecto')->label('Codigo')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('offerStatus.status')->label('Estado')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('cliente')->label('Cliente')->searchable()->limit(25)
                    ->tooltip(fn ($record) => $record->cliente)->toggleable(),
                Tables\Columns\TextColumn::make('objeto')->label('Objeto')->limit(35)
                    ->tooltip(fn ($record) => $record->objeto)->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('proyecto')->label('Proyecto')->limit(30)
                    ->tooltip(fn ($record) => $record->proyecto)->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextInputColumn::make('fecha_anuncio')->label('Anuncio')
                    ->type('date')->sortable()->toggleable()
                    ->extraAttributes(['style' => 'min-width:130px']),
                Tables\Columns\TextInputColumn::make('fecha_publicacion')->label('Publicacion')
                    ->type('date')->sortable()->toggleable()
                    ->extraAttributes(['style' => 'min-width:130px']),
                Tables\Columns\TextInputColumn::make('fecha_presentacion')->label('Presentacion')
                    ->type('date')->sortable()->toggleable()
                    ->extraAttributes(['style' => 'min-width:130px']),
                Tables\Columns\TextInputColumn::make('fecha_adjudicacion')->label('Adjudicacion')
                    ->type('date')->sortable()->toggleable()
                    ->extraAttributes(['style' => 'min-width:130px']),
                Tables\Columns\TextInputColumn::make('fecha_formalizacion')->label('Formalizacion')
                    ->type('date')->sortable()->toggleable()
                    ->extraAttributes(['style' => 'min-width:130px']),
                Tables\Columns\TextInputColumn::make('fecha_fin_contrato')->label('Fin contrato')
                    ->type('date')->sortable()->toggleable()
                    ->extraAttributes(['style' => 'min-width:130px']),
                Tables\Columns\TextColumn::make('offerType.name')->label('Tipo')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sector')->label('Sector')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('importe_licitacion')->label('Imp.Licit.')->money('EUR')->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('provincia')->label('Provincia')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('id_offer_status')
                    ->label('Estado')->placeholder('Estado: Todos')
                    ->options($statusOptions),
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
                Tables\Actions\Action::make('fechas')
                    ->label('Fechas')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->fillForm(fn ($record) => [
                        'fecha_anuncio' => $record->fecha_anuncio,
                        'fecha_publicacion' => $record->fecha_publicacion,
                        'fecha_presentacion' => $record->fecha_presentacion,
                        'fecha_adjudicacion' => $record->fecha_adjudicacion,
                        'fecha_formalizacion' => $record->fecha_formalizacion,
                        'fecha_fin_contrato' => $record->fecha_fin_contrato,
                    ])
                    ->form([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('fecha_anuncio')->label('Anuncio'),
                            Forms\Components\DatePicker::make('fecha_publicacion')->label('Publicacion'),
                            Forms\Components\DatePicker::make('fecha_presentacion')->label('Presentacion'),
                            Forms\Components\DatePicker::make('fecha_adjudicacion')->label('Adjudicacion'),
                            Forms\Components\DatePicker::make('fecha_formalizacion')->label('Formalizacion'),
                            Forms\Components\DatePicker::make('fecha_fin_contrato')->label('Fin contrato'),
                        ]),
                    ])
                    ->action(fn ($record, array $data) => $record->update($data)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfferDates::route('/'),
            'edit' => Pages\EditOfferDates::route('/{record}/edit'),
            'import' => Pages\ImportOfferDates::route('/import'),
        ];
    }
}
