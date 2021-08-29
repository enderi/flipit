<?php

namespace App\Dealers\PokerGames\FourStreetGames;

use App\Dealers\DealerUtils\DealtCards;
use App\Dealers\PokerGames\Commands\AllCardsRevealed;

class FourStreetHeadUpGame
{
    public static $gameStates = [
        'WAITING_PLAYERS' => 'WAITING_PLAYERS',
        'PLAYERS_JOINED' => 'PLAYERS_JOINED',
        'PREFLOP' => 'PREFLOP',
        'FLOP_DEALT' => 'FLOP_DEALT',
        'TURN_DEALT' => 'TURN_DEALT',
        'RIVER_DEALT' => 'RIVER_DEALT',
        'HAND_ENDED' => 'HAND_ENDED',
    ];

    private $streetsDealt = [
        'pocket_cards' => false,
        'flop' => false,
        'turn' => false,
        'river' => false
    ];

    private $gameStatus;
    private $cards;
    private $cardsInDealOrder;
    private $options;
    private $cardIndex = 0;
    private $allCardsRevealed = false;
    private $newHandRequested;
    private $cardsInSeatRevealed;
    private $resultSaved = false;

    private DealtCards $dealtCards;

    public function __construct() {
        $this->dealtCards = new DealtCards();
    }

    private function initializeState()
    {
        $this->options = [
            1 => [],
            2 => []
        ];
        $this->cardsInSeatRevealed = [
            1 => false,
            2 => false
        ];

        $this->dealtCards = new DealtCards();
    }

    public function addAction($data) {
        $key = $data['key'];
        if ($key == 'all_cards_revealed') {
            $cmd = new AllCardsRevealed();
            $this->allCardsRevealed = true;
            $this->cardsInSeatRevealed[1] = true;
            $this->cardsInSeatRevealed[2] = true;
        }
        if ($key == 'pocket_card') {
            $seatNo = $data['seat_number'];
            $this->dealtCards->addCard($seatNo, $data['card']);
        }
        if (in_array($key, ['flop_card', 'turn_card', 'river_card'])) {
            $this->dealtCards->addCard('community', $data['card']);
        }
    }

    public function getStatus() {
        return $this->dealtCards;
    }
}
