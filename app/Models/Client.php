<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_name', 'company', 'contact_person', 'phone', 'address',
    ];

    public function inspectionRequests()
    {
        return $this->hasMany(InspectionRequest::class);
    }
}
