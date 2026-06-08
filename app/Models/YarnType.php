<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YarnType extends Model
{
    use HasFactory;

    protected $fillable = [
        'yarn_name', 'material', 'color', 'description',
    ];

    public function inspectionRequests()
    {
        return $this->hasMany(InspectionRequest::class);
    }
}
