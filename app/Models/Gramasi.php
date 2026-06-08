<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gramasi extends Model
{
    use HasFactory;

    protected $table = 'gramasis';

    protected $fillable = [
        'range', 'description',
    ];
}
