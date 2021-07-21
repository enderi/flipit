<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasFactory;
    protected $hidden = ['id', 'created_at', 'updated_at'];
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $casts = [
        'expires_at' => 'datetime'
    ];
}
