<?php

namespace App\Models;

use App\DomainObjects\Deck;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hand extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $hidden = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'ended' => 'boolean',
        'data' => 'array',
        'result' => 'array',
        'deck' => DeckMutator::class
    ];

    protected $attributes = [
        'result' => '{}'
    ];

    public function actions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Action::class);
    }

    public function getDeck():Deck
    {
        return $this->deck;
    }
}
