<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferCriteriaResource\Pages;
use App\Models\Offer;
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

class OfferCriteriaResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $navigationGroup = 'Ofertas';
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationLabel = 'Criterios';
    protected static ?string $modelLabel = 'Criterios Oferta';
    protected static ?string $pluralModelLabel = 'Criterios';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'offer-criteria';

    protected static function getCompanyId(): int
    {
        return (int) session('current_company_id', 1);
    }

    public static function form(Form $form): Form
    {
        $cid = static::getCompanyId();

        return $form->schema([
            Forms\Components\TextInput::make('codigo_proyecto')->label('Codigo')->disabled(),
            Forms\Components\TextInput::make('cliente')->label('Cliente')->disabled(),
            Forms\Components\Section::make('Criterios del pliego')->schema([
                Forms\Components\TextInput::make('peso_economica')->label('Peso economica (%)')->numeric()->suffix('%'),
                Forms\Components\TextInput::make('peso_tecnica')->label('Peso tecnica (%)')->numeric()->suffix('%'),
                Forms\Components\TextInput::make('peso_objetiva_real')->label('Peso objetiva real (%)')->numeric()->suffix('%'),
                Forms\Components\TextInput::make('peso_objetiva_fake')->label('Peso objetiva fake (%)')->numeric()->suffix('%'),
                Forms\Components\Select::make('id_formula')
                    ->label('Formula')
                    ->options(OfferFormula::where('id_company', $cid)->pluck('name', 'id'))
                    ->searchable(),
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
                Tables\Columns\TextInputColumn::make('peso_economica')->label('Econ.%')
                    ->type('number')->sortable()->toggleable()
                    ->rules(['nullable', 'numeric', 'min:0', 'max:100'])
                    ->extraAttributes(['style' => 'min-width:70px;text-align:right']),
                Tables\Columns\TextInputColumn::make('peso_tecnica')->label('Tecn.%')
                    ->type('number')->sortable()->toggleable()
                    ->rules(['nullable', 'numeric', 'min:0', 'max:100'])
                    ->extraAttributes(['style' => 'min-width:70px;text-align:right']),
                Tables\Columns\TextInputColumn::make('peso_objetiva_real')->label('Obj.R.%')
                    ->type('number')->sortable()->toggleable()
                    ->rules(['nullable', 'numeric', 'min:0', 'max:100'])
                    ->extraAttributes(['style' => 'min-width:70px;text-align:right']),
                Tables\Columns\TextInputColumn::make('peso_objetiva_fake')->label('Obj.F.%')
                    ->type('number')->sortable()->toggleable()
                    ->rules(['nullable', 'numeric', 'min:0', 'max:100'])
                    ->extraAttributes(['style' => 'min-width:70px;text-align:right']),
                Tables\Columns\SelectColumn::make('id_formula')->label('Formula')
                    ->options(OfferFormula::where('id_company', static::getCompanyId())->pluck('name', 'id')->toArray())
                    ->sortable()->toggleable(),
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
                Tables\Actions\Action::make('criterios')
                    ->label('Criterios')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->fillForm(fn ($record) => [
                        'peso_economica' => $record->peso_economica,
                        'peso_tecnica' => $record->peso_tecnica,
                        'peso_objetiva_real' => $record->peso_objetiva_real,
                        'peso_objetiva_fake' => $record->peso_objetiva_fake,
                        'id_formula' => $record->id_formula,
                    ])
                    ->form([
                        Forms\Components\Grid::make(5)->schema([
                            Forms\Components\TextInput::make('peso_economica')->label('Economica %')->numeric()->suffix('%'),
                            Forms\Components\TextInput::make('peso_tecnica')->label('Tecnica %')->numeric()->suffix('%'),
                            Forms\Components\TextInput::make('peso_objetiva_real')->label('Obj. Real %')->numeric()->suffix('%'),
                            Forms\Components\TextInput::make('peso_objetiva_fake')->label('Obj. Fake %')->numeric()->suffix('%'),
                            Forms\Components\Select::make('id_formula')->label('Formula')
                                ->options(OfferFormula::where('id_company', static::getCompanyId())->pluck('name', 'id')),
                        ]),
                    ])
                    ->action(fn ($record, array $data) => $record->update($data)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfferCriteria::route('/'),
            'edit' => Pages\EditOfferCriteria::route('/{record}/edit'),
            'import' => Pages\ImportOfferCriteria::route('/import'),
        ];
    }
}
