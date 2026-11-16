<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Key / value settings that the super admin edits (company details, logo, bank details for the invoice ...). */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    private static ?array $cache = null;

    private static function all_(): array
    {
        return self::$cache ??= self::query()->pluck('value', 'key')->all();
    }

    public static function get(string $key, $default = null)
    {
        $value = self::all_()[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    /** @param string[] $keys  @return array<string, string|null> */
    public static function many(array $keys): array
    {
        $all = self::all_();
        return collect($keys)->mapWithKeys(fn ($k) => [$k => $all[$k] ?? null])->all();
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache = null;
    }
}
