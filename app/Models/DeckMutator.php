<?php

namespace App\Models;

use App\DomainObjects\Deck;
use http\Exception\InvalidArgumentException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class DeckMutator implements CastsAttributes
{

    public function get($model, string $key, $value, array $attributes)
    {
        return Deck::of($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if(! $value instanceof Deck) {
            throw new InvalidArgumentException("Need a deck");
        }
        return $value->toString();
    }
}
