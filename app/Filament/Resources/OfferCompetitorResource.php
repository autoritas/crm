<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferCompetitorResource\Pages;
use App\Models\Competitor;
use App\Models\OfferCompetitor;
use App\Models\OfferStatus;
use App\Models\OfferType;
use App\Models\OfferWorkflow;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfferCompetitorResource extends Resource
{
    protected static ?string $model = OfferCompetitor::class;

    protected static ?string $navigationGroup = 'Ofertas';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Competidores';
    protected static ?string $modelLabel = 'Competidor en Oferta';
    protected static ?string $pluralModelLabel = 'Competidores';
    protected static ?int $navigationSort = 4;

    protected static function getCompanyId(): int
    {
        return (int) session('current_company_id', 1);
    }

    public static function form(Form $form): Form
    {
        $cid = static::getCompanyId();

        return $form->schema([
            Forms\Components\Select::make('id_offer')
                ->label('Oferta')
                ->relationship('offer', 'codigo_proyecto', fn ($query) => $query->where('id_company', $cid))
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('id_competitor')
                ->label('Competidor')
                ->relationship('competitor', 'name', fn ($query) => $query->where('id_company', $cid))
                ->searchable()->preload()
                ->createOptionForm([
                    Forms\Components\Hidden::make('id_company')->default(fn () => session('current_company_id', 1)),
                    Forms\Components\TextInput::make('name')->label('Nombre')->required(),
                    Forms\Components\TextInput::make('cif')->label('CIF')->maxLength(20),
                ])
                ->createOptionUsing(fn (array $data) => Competitor::create($data)->id),
            Forms\Components\TextInput::make('competitor_nombre')
                ->label('Nombre (texto)')->required()->maxLength(255),
            Forms\Components\Select::make('admision')
                ->label('Admision')
                ->options(['Pendiente' => 'Pendiente', 'Admitido' => 'Admitido', 'Excluido' => 'Excluido'])
                ->default('Pendiente'),
            Forms\Components\Select::make('razon_exclusion')
                ->label('Razon exclusion')
                ->options(['Administrativa' => 'Administrativa', 'Tecnica' => 'Tecnica', 'Economica' => 'Economica', 'Desconocida' => 'Desconocida'])
                ->visible(fn ($get) => $get('admision') === 'Excluido'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $cid = static::getCompanyId();
        $statusOptions = OfferStatus::where('id_company', $cid)->pluck('status', 'id')->toArray();
        $typeOptions = OfferType::where('id_company', $cid)->pluck('name', 'id')->toArray();
        $workflowOptions = OfferWorkflow::where('id_company', $cid)->orderBy('sort_order')->pluck('name', 'id')->toArray();
        $defaultStatusId = OfferStatus::where('id_company', $cid)->where('is_default_filter', true)->value('id');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('offer', fn ($q) => $q->where('id_company', $cid)))
            ->defaultSort('id_offer', 'desc')
            ->defaultPaginationPageOption(100)
            ->paginationPageOptions([25, 50, 100])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->persistSearchInSession()
            ->columns([
                Tables\Columns\TextColumn::make('offer.codigo_proyecto')->label('Oferta')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('offer.offerStatus.status')->label('Estado')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('offer.cliente')->label('Cliente')->searchable()->limit(25)
                    ->tooltip(fn ($record) => $record->offer?->cliente)->toggleable(),
                Tables\Columns\TextColumn::make('offer.objeto')->label('Objeto')->limit(30)
                    ->tooltip(fn ($record) => $record->offer?->objeto)->toggleable(),
                Tables\Columns\TextColumn::make('competitor_nombre')->label('Competidor')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('competitor.name')->label('Normalizado')->sortable()->toggleable(),
                Tables\Columns\SelectColumn::make('admision')
                    ->label('Admision')
                    ->options(['Pendiente' => 'Pendiente', 'Admitido' => 'Admitido', 'Excluido' => 'Excluido'])
                    ->toggleable(),
                Tables\Columns\SelectColumn::make('razon_exclusion')
                    ->label('Exclusion')
                    ->options(['' => '-', 'Administrativa' => 'Administrativa', 'Tecnica' => 'Tecnica', 'Economica' => 'Economica', 'Desconocida' => 'Desconocida'])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('scores.tecnico')->label('Tecnico')->alignEnd()->toggleable(),
                Tables\Columns\TextColumn::make('scores.economico')->label('Economico')->alignEnd()->toggleable(),
                Tables\Columns\TextColumn::make('scores.objetivo_real')->label('Obj.Real')->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('scores.objetivo_fake')->label('Obj.Fake')->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('scores.precio')->label('Precio')->money('EUR')->alignEnd()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('offer_status')
                    ->label('Estado oferta')->placeholder('Estado: Todos')
                    ->options($statusOptions)
                    ->query(fn (Builder $query, array $data) =>
                        $data['value'] ? $query->whereHas('offer', fn ($q) => $q->where('id_offer_status', $data['value'])) : $query
                    ),
                Tables\Filters\SelectFilter::make('offer_workflow')
                    ->label('Fase')->placeholder('Fase: Todos')
                    ->options($workflowOptions)
                    ->query(fn (Builder $query, array $data) =>
                        $data['value'] ? $query->whereHas('offer', fn ($q) => $q->where('id_workflow', $data['value'])) : $query
                    ),
                Tables\Filters\SelectFilter::make('offer_type')
                    ->label('Tipo')->placeholder('Tipo: Todos')
                    ->options($typeOptions)
                    ->query(fn (Builder $query, array $data) =>
                        $data['value'] ? $query->whereHas('offer', fn ($q) => $q->where('id_offer_type', $data['value'])) : $query
                    ),
                Tables\Filters\SelectFilter::make('admision')
                    ->label('Admision')->placeholder('Admision: Todos')
                    ->options(['Pendiente' => 'Pendiente', 'Admitido' => 'Admitido', 'Excluido' => 'Excluido']),
            ])
            ->actions([
                Tables\Actions\Action::make('scores')
                    ->label('Scores')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->fillForm(fn ($record) => [
                        'tecnico' => $record->scores?->tecnico,
                        'economico' => $record->scores?->economico,
                        'objetivo_real' => $record->scores?->objetivo_real,
                        'objetivo_fake' => $record->scores?->objetivo_fake,
                        'precio' => $record->scores?->precio,
                    ])
                    ->form([
                        Forms\Components\Grid::make(5)->schema([
                            Forms\Components\TextInput::make('tecnico')->label('Tecnico')->numeric(),
                            Forms\Components\TextInput::make('economico')->label('Economico')->numeric(),
                            Forms\Components\TextInput::make('objetivo_real')->label('Obj.Real')->numeric(),
                            Forms\Components\TextInput::make('objetivo_fake')->label('Obj.Fake')->numeric(),
                            Forms\Components\TextInput::make('precio')->label('Precio')->numeric()->prefix('€'),
                        ]),
                    ])
                    ->action(function ($record, array $data): void {
                        $record->scores()->updateOrCreate(
                            ['id_offer_competitor' => $record->id],
                            $data
                        );
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfferCompetitors::route('/'),
            'create' => Pages\CreateOfferCompetitor::route('/create'),
            'edit' => Pages\EditOfferCompetitor::route('/{record}/edit'),
            'import' => Pages\ImportOfferCompetitors::route('/import'),
        ];
    }
}
