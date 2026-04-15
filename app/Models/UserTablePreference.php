<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTablePreference extends Model
{
    protected $connection = 'mysql';

    protected $fillable = ['user_id', 'table_key', 'toggled_columns'];

    protected function casts(): array
    {
        return ['toggled_columns' => 'array'];
    }

    public static function getToggledColumns(int $userId, string $tableKey): ?array
    {
        return static::where('user_id', $userId)
            ->where('table_key', $tableKey)
            ->value('toggled_columns');
    }

    public static function saveToggledColumns(int $userId, string $tableKey, array $columns): void
    {
        static::updateOrCreate(
            ['user_id' => $userId, 'table_key' => $tableKey],
            ['toggled_columns' => $columns]
        );
    }
}
