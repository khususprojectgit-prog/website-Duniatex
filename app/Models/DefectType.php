<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefectType extends Model
{
    use HasFactory;

    protected $fillable = [
        'defect_name', 'category', 'default_point', 'description',
    ];

    /** Defect types that record position as meter range (e.g. 10-25). */
    public const RANGE_POSITION_NAMES = ['patah jarum', 'bopeng'];

    public static function usesRangePosition(?string $defectName): bool
    {
        if ($defectName === null || $defectName === '') {
            return false;
        }

        return in_array(strtolower(trim($defectName)), self::RANGE_POSITION_NAMES, true);
    }

    public function defects()
    {
        return $this->hasMany(Defect::class);
    }
}
