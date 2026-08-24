<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Projectfundtype extends Model
{
    use HasFactory;

    public const FUND_TYPES = [
        'National',
        'Provincial',
        'LGU',
    ];

    public const CATEGORY_TO_TYPE = [
        'national' => 'National',
        'provincial' => 'Provincial',
        'lgu' => 'LGU',
    ];

    public const TYPE_TO_CATEGORY = [
        'National' => 'national',
        'Provincial' => 'provincial',
        'LGU' => 'lgu',
    ];

    public const DEFAULT_SOURCES = [
        'national' => [],
        'provincial' => [],
        'lgu' => [],
    ];

    protected $table = 'project_fund_type_tb';

    protected $fillable = [
        'fund_type',
        'fund_source',
        'project_id',
    ];

    public function project()
    {
        return $this->belongsTo(Projects::class, 'project_id');
    }

    public static function fundTypeForCategory(string $category): ?string
    {
        return self::CATEGORY_TO_TYPE[$category] ?? null;
    }

    public static function categoryForFundType(?string $fundType): ?string
    {
        if ($fundType === null) {
            return null;
        }

        return self::TYPE_TO_CATEGORY[$fundType] ?? null;
    }

    public static function defaultSourcesForCategory(string $category): array
    {
        return self::DEFAULT_SOURCES[$category] ?? [];
    }

    public static function allDefaultSourcesGrouped(): array
    {
        $grouped = [];

        foreach (self::DEFAULT_SOURCES as $category => $sources) {
            $grouped[$category] = $sources;
        }

        return $grouped;
    }
}
