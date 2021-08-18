<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $hidden = ['id', 'created_at', 'updated_at', 'players'];

    protected $casts = ['information' => 'array'];

    public function invitation() {
        return $this->hasOne(Invitation::class);
    }

    public function players() {
        return $this->hasMany(Player::class);
    }

    public function hands() {
        return $this->hasMany(Hand::class);
    }

    public function mappings() {
        return $this->hasMany(GamePlayerMapping::class);
    }

    public function hand() {
        return $this->belongsTo(Hand::class);
    }

    public function getCurrentHand() {
        return $this->hands()->where('ended', false)->first();
    }
}
