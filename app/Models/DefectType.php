<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefectType extends Model
{
    use HasFactory;

    protected $fillable = [
        'defect_name', 'default_point', 'description',
    ];

    public function defects()
    {
        return $this->hasMany(Defect::class);
    }
}
