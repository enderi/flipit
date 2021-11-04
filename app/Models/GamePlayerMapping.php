<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GamePlayerMapping extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $hidden = ['id', 'created_at', 'updated_at'];

    public function game() {
        return $this->belongsTo(Game::class);
    }

    public function player() {
        return $this->belongsTo(Player::class);
    }

    public function getPlayerUuid() {
        return $this->player->uuid;
    }

    public function getGameUuid() {
        return $this->game->uuid;
    }
}
