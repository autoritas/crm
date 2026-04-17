<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferWorkflowResource\Pages;
use App\Models\CompanySetting;
use App\Models\OfferStatus;
use App\Models\OfferWorkflow;
use App\Services\KanboardSync;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Mantenimiento de fases del workflow comercial. Cada fase se asocia a
 * una columna de Kanboard del tablero de la empresa activa. El select de
 * columna se alimenta en vivo leyendo `kanboard.columns` via KanboardSync.
 */
class OfferWorkflowResource extends Resource
{
    protected static ?string $model = OfferWorkflow::class;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $modelLabel = 'Estado Workflow';

    protected static ?string $pluralModelLabel = 'Estados Workflow';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        $companyId = (int) session('current_company_id', 1);

        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default($companyId),

                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Ej: PROSPECTS, OFERTAR, EN CURSO, ... (conviene usar el mismo nombre que la columna en Kanboard).'),

                Forms\Components\Select::make('kanboard_column_id')
                    ->label('Columna Kanboard')
                    ->options(fn () => self::kanboardColumnOptions($companyId))
                    ->searchable()
                    ->nullable()
                    ->helperText('Columna del tablero Kanboard de la empresa. El movimiento en una u otra plataforma se sincroniza automaticamente.'),

                Forms\Components\Select::make('closed_offer_status_id')
                    ->label('Estado al cerrar tarea')
                    ->options(fn () => OfferStatus::where('company_id', $companyId)
                        ->orderBy('status')
                        ->pluck('status', 'id')
                        ->toArray())
                    ->searchable()
                    ->nullable()
                    ->helperText('Estado que adopta la oferta si la tarea Kanboard se cierra estando en esta fase. Ej: PROSPECTS -> Descartado, EN DECISION -> Perdido, GANADO -> Ganado.'),

                Forms\Components\Toggle::make('is_go_nogo_phase')
                    ->label('Fase de lectura Go/No Go')
                    ->helperText('Si se activa, las ofertas en esta fase se envian al flujo de n8n para analisis Go/No Go. Solo una fase por empresa deberia tenerlo activo.')
                    ->default(false),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Forms\Components\ColorPicker::make('color')
                    ->label('Color')
                    ->default('#94a3b8'),

                Forms\Components\Textarea::make('description')
                    ->label('Descripcion')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $companyId = (int) session('current_company_id', 1);
        $kbMap = self::kanboardColumnOptions($companyId);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('company_id', $companyId))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Fase')
                    ->searchable()
                    ->html()
                    ->formatStateUsing(fn ($state, OfferWorkflow $record) =>
                        '<span style="display:inline-flex;align-items:center;gap:6px;">'
                        . '<span style="width:12px;height:12px;border-radius:50%;background:' . ($record->color ?: '#94a3b8') . ';display:inline-block;"></span>'
                        . e($state) . '</span>'
                    ),

                Tables\Columns\TextColumn::make('kanboard_column_id')
                    ->label('Columna Kanboard')
                    ->formatStateUsing(fn ($state) =>
                        $state === null ? '—' : ($kbMap[(int) $state] ?? "ID {$state} (no existe)")
                    ),

                Tables\Columns\TextColumn::make('closedOfferStatus.status')
                    ->label('Estado al cerrar')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_go_nogo_phase')
                    ->label('Go/No Go')
                    ->boolean(),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripcion')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Devuelve `[column_id => "titulo (pos N)"]` leyendo del tablero Kanboard
     * de la empresa. Si no hay `kanboard_project_id` configurado, devuelve vacio.
     */
    private static function kanboardColumnOptions(int $companyId): array
    {
        $projectId = CompanySetting::where('company_id', $companyId)->value('kanboard_project_id');
        if (!$projectId) return [];

        try {
            $cols = app(KanboardSync::class)->listProjectColumns((int) $projectId);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($cols as $c) {
            $out[$c['id']] = "{$c['title']}  ·  col {$c['id']}";
        }
        return $out;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOfferWorkflows::route('/'),
            'create' => Pages\CreateOfferWorkflow::route('/create'),
            'edit'   => Pages\EditOfferWorkflow::route('/{record}/edit'),
        ];
    }
}
