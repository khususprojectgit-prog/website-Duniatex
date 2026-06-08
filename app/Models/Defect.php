<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Defect extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspection_id', 'defect_type_id', 'position_meter', 'point', 'notes', 'side',
    ];

    protected function casts(): array
    {
        return [
        ];
    }

    public function inspection()
    {
        return $this->belongsTo(Inspection::class);
    }

    public function defectType()
    {
        return $this->belongsTo(DefectType::class);
    }
}
