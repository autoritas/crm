<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiCredentialResource\Pages;
use App\Models\ApiCredential;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApiCredentialResource extends Resource
{
    protected static ?string $model = ApiCredential::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $modelLabel = 'Credencial API';

    protected static ?string $pluralModelLabel = 'Credenciales API';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('id_company')
                ->label('Empresa')
                ->relationship('company', 'name')
                ->placeholder('Global (todas las empresas)')
                ->nullable(),
            Forms\Components\Select::make('service')
                ->label('Servicio')
                ->options([
                    'n8n' => 'n8n',
                    'openai' => 'OpenAI',
                    'anthropic' => 'Anthropic (Claude)',
                    'kanboard' => 'Kanboard API',
                    'other' => 'Otro',
                ])
                ->required(),
            Forms\Components\TextInput::make('label')
                ->label('Etiqueta')
                ->helperText('Nombre descriptivo para identificar esta credencial')
                ->maxLength(255),
            Forms\Components\TextInput::make('base_url')
                ->label('URL base')
                ->url()
                ->maxLength(500)
                ->placeholder('https://...'),
            Forms\Components\Textarea::make('api_key')
                ->label('API Key')
                ->rows(2)
                ->helperText('Se almacena cifrada en la base de datos.'),
            Forms\Components\TextInput::make('folder')
                ->label('Carpeta / Workspace')
                ->maxLength(255)
                ->placeholder('Nombre de carpeta o workspace'),
            Forms\Components\KeyValue::make('extra')
                ->label('Parametros adicionales')
                ->helperText('Clave-valor para configuraciones extra'),
            Forms\Components\Toggle::make('is_active')
                ->label('Activa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service')
                    ->label('Servicio')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'n8n' => 'success',
                        'openai' => 'info',
                        'anthropic' => 'warning',
                        'kanboard' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Etiqueta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->placeholder('Global')
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_url')
                    ->label('URL')
                    ->limit(40),
                Tables\Columns\TextColumn::make('folder')
                    ->label('Carpeta'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
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
            'index' => Pages\ListApiCredentials::route('/'),
            'create' => Pages\CreateApiCredential::route('/create'),
            'edit' => Pages\EditApiCredential::route('/{record}/edit'),
        ];
    }
}
