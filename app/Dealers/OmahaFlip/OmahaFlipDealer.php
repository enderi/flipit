<?php

namespace App\Dealers\OmahaFlip;

use App\Dealers\FourStreetGames\FourStreetPokerFlow;
use App\Dealers\TexasFlip\Combinations;
use App\Events\GameStateChanged;
use App\Lib\DeckLib\Deck;
use App\Lib\DeckLib\evaluate;
use App\Models\Action;
use App\Models\Game;
use App\Models\GamePlayerMapping;
use App\Models\Hand;
use App\Models\Invitation;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class OmahaFlipDealer
{
    const OMAHA_FLIP = 'OMAHA-FLIP';
    const MIN_SEATS = 2;
    const MAX_SEATS = 2;
    const POCKET_CARD_COUNT = 4;
    const CONFIRM_REQUIRED = 'confirm_required';
    const PLAYER_ACTION = 'player_action';
    const ACTION_UUID = 'actionUuid';
    const KEY = 'key';
    const ALL_CARDS_REVEALED = 'ALL_CARDS_REVEALED';
    const WAITING_PLAYERS_TO_ACT = 'WAITING_PLAYERS_TO_ACT';
    const WAITING_PLAYERS = 'WAITING_PLAYERS';
    const HAND_ENDED = 'HAND_ENDED';
    const TURN = 'turn';
    const FLOP = 'flop';
    const READY_TO_START = 'READY_TO_START';
    const PREFLOP = 'pocket_cards';
    const REQUEST_NEW_HAND = 'REQUEST_NEW_HAND';

    private $currentHand;
    private $game;
    private $players;

    private $card_index;
    private $handPhase;

    private $pendingActions;
    private $allCardsRevealed;
    private $handStatus;

    public function __construct() {
    }

    public function initWithGame($game) {
        $this->setGame($game);
        $this->players = collect([]);
        $this->pendingActions = [];
        $this->allCardsRevealed = false;
        $this->playersJoined = collect([]);
        $this->parseStatus();
    }
    public static function of($game): OmahaFlipDealer
    {
        $result = new OmahaFlipDealer();
        $result->initWithGame($game);
        return $result;
    }
    public function setGame($game)
    {
        $this->game = $game;
        $this->currentHand = $game->hands()->where('ended', false)->first();
    }

    public function newGame()
    {
        $this->game = Game::create([
            'uuid' => Uuid::uuid4(),
            'game_type' => self::OMAHA_FLIP,
            'min_seats' => self::MIN_SEATS,
            'max_seats' => self::MAX_SEATS,
            'information' => array()]);
        $this->createNewHand();
        $invitation = new Invitation([
            'code' => Uuid::uuid4(),
            'expires_at' => Carbon::now()->addHour()
        ]);
        $this->game->invitation()->save($invitation);
        return $this->game;
    }

    public function joinAsPlayer()
    {
        $player = Player::create([
            'uuid' => Uuid::uuid4(),
            'game_id' => $this->game['id'],
            'seat_number' => $this->game->players->count() + 1
        ]);

        $this->createAction([
            'hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                'playerUuid' => $player->uuid,
                self::KEY => 'player_joined'
            ]
        ]);
        $this->broadcastMessage();
        return GamePlayerMapping::create(
            [
                'uuid' => Uuid::uuid4(),
                'game_id' => $this->game->id,
                'player_id' => $player->id
            ]);
    }

    public function addUserAction($actionKey, $actionUuid, $playerUuid)
    {
        $this->createAction([
            'hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                self::KEY => self::PLAYER_ACTION,
                'playerUuid' => $playerUuid,
                'action' => $actionKey,
                self::ACTION_UUID => $actionUuid
            ]
        ]);
    }
    public function tick($playerUuid): array
    {
        $this->proceedIfPossible();
        return $this->getStatus($playerUuid);
    }

    public function requestNewHand($playerUuid)
    {
        $this->createAction(['hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                self::KEY => self::REQUEST_NEW_HAND,
                'playerUuid' => $playerUuid
            ]
        ]);
    }

    private function parseStatus()
    {
        // check if all players have requested a new hand
        $newHandRequests = $this->currentHand->actions->filter(function($action){
            return $action->data[self::KEY] == self::REQUEST_NEW_HAND;
        })->map(function($action){
            return $action->data['playerUuid'];
        });

        $allFound = $this->game->players->map(function($player){
            return $player->uuid;
        })->every(function($value) use ($newHandRequests) {
            return $newHandRequests->contains($value);
        });

        if($allFound && $newHandRequests->count() == $this->game->players->count()) {
            $this->createNewHand();
        }
        // figure out what street we are at
        $phase = 'WAITING';
        $playerCount = $this->game->players->count();
        if ($playerCount >= $this->game->min_seats && $playerCount <= $this->game->max_seats) {
            $phase = self::READY_TO_START;
        }

        $actions = $this->currentHand->actions;
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            $act = $action->data[self::KEY];
            if ($act == 'new_street_dealt') {
                $phase = $actions[$i]->data['value'];
            }
            if ($act == self::HAND_ENDED) {
                $phase = self::HAND_ENDED;
            }
        }
        $this->handPhase = $phase;

        $this->allCardsRevealed = $this->currentHand->actions->contains(function ($act) {
            return $act->data[self::KEY] == self::ALL_CARDS_REVEALED;
        });

        // Find pending actions
        $this->findPendingActions();

        // calculate card index
        $this->card_index = $this->currentHand->actions->filter(function ($action) {
            return array_key_exists('card_index', $action->data);
        })->count();
    }

    private function getStatus($playerUuid): array
    {
        $playerInGame = $this->players->contains(function ($p) use ($playerUuid) {
            return $playerUuid == $p->uuid;
        });
        if ($playerInGame) {
            return [];
        }

        $options = [];
        foreach ($this->pendingActions as $key => $value) {
            if ($value['playerUuid'] == $playerUuid) {
                foreach ($value['options'] as $opt) {
                    $opt['uuid'] = $key;
                    $options[] = $opt;
                }
            }
        }

        $cardsPerSeat = [];
        $seatByPlayerUuid = [];
        $communityCards = collect([]);
        for ($i = 0; $i < $this->game->players->count(); $i++) {
            $pl = $this->game->players[$i];
            $seatByPlayerUuid[$pl->uuid] = $pl->seat_number;
        }
        $actions = $this->currentHand->actions;
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            if ($action->data[self::KEY] == 'pocket_card') {
                $seat = $action->data['seat_number'];
                if (!array_key_exists($seat, $cardsPerSeat)) {
                    $cardsPerSeat[$seat] = collect([]);
                }
                $cardsPerSeat[$seat]->push([
                    'card_uuid' => $action->uuid,
                    'deck_index' => $action->data['card_index']]);
            }
            if (array_key_exists('community', $action->data) && $action->data['community'] == true) {
                $communityCards->push([
                    'card_uuid' => $action->uuid,
                    'deck_index' => $action->data['card_index']]);
            }
        }


        $handValues = $this->getHandValues($cardsPerSeat, $communityCards);
        $result = null;
        if ($this->handStatus == self::HAND_ENDED) {
            $result = $this->getResult($handValues);
        }
        return [
            'actions' => $this->currentHand->actions, // todo: remove this
            'mySeat' => $seatByPlayerUuid[$playerUuid],
            'cardsPerSeat' => $cardsPerSeat,
            'communityCards' => $communityCards,
            'options' => $options,
            'revealedCards' => $this->mapRevealedCards($playerUuid),
            'handValues' => $handValues,
            'handStatus' => $this->handStatus,
            'handResult' => $result,

            'handPhase' => $this->handPhase
        ];
    }

    private function createNewHand()
    {
        Hand::where('game_id', $this->game->id)
            ->update(['ended' => true]);
        $deck = new Deck();
        $deck->initialize();
        $deck->shuffle();
        $this->currentHand = Hand::create([
            'game_id' => $this->game->id,
            'uuid' => Uuid::uuid4(),
            'ended' => false,
            'deck' => $deck->toString()
        ]);
    }

    private function getHandValues($cardsPerSeat, $communityCards)
    {
        $evaluator = new evaluate();
        $bestHandsBySeat = [];
        if (sizeof($communityCards) < 3) {
            return [];
        }
        foreach ($cardsPerSeat as $seat => $cs) {
            $handCombinations = [];
            foreach (new Combinations($cs->toArray(), 2) as $c) {
                $handCombinations[] = $c;
            }

            $tableCombinations = [];
            foreach (new Combinations($communityCards->toArray(), 3) as $c) {
                $tableCombinations[] = $c;
            }

            $bestHand = null;
            for ($i = 0; $i < sizeof($handCombinations); $i++) {
                for ($j = 0; $j < sizeof($tableCombinations); $j++) {
                    $hand = array_merge($handCombinations[$i], $tableCombinations[$j]);
                    $cards = [];
                    foreach ($hand as $cardForThis) {
                        $cards[] = $this->currentHand->getDeck()->getIndex($cardForThis['deck_index']);
                    }
                    $value = $evaluator->getValue($cards);
                    $name = $evaluator->getHandName();
                    if ($bestHand == null || $bestHand['value'] > $value) {
                        $bestHand = [
                            'value' => $value,
                            'cards' => $hand,
                            'name' => $name
                        ];
                    }
                }
            }
            $bestHandsBySeat[$seat] = $bestHand;
        }

        return $bestHandsBySeat;
    }

    private function findPendingActions()
    {
        $actions = $this->getActions();
        $requiredActions = [];
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            $act = $action->data[self::KEY];
            if ($act == self::CONFIRM_REQUIRED) {
                $requiredActions[$action->uuid] = $action->data;
            } else if ($act == self::PLAYER_ACTION) {
                $key = $action->data[self::ACTION_UUID];
                unset($requiredActions[$key]);
            }
        }
        $this->pendingActions = $requiredActions;
    }

    private function mapRevealedCards($playerUuid): array
    {
        $values = [];
        $actions = $this->currentHand->actions;
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            $isCard = array_key_exists('card_index', $action->data);
            if (!$isCard) {
                continue;
            }
            $ownCard = array_key_exists('player_uuid', $action->data)
                && $playerUuid == $action->data['player_uuid'];
            $communityCard = array_key_exists('community', $action->data) && $action->data['community'] == true;
            if ($ownCard || $communityCard || $this->allCardsRevealed) {
                $values[$action->uuid] = $this->currentHand->getDeck()->getIndex($action->data['card_index'])->toString();
            }
        }
        return $values;
    }

    private function isPendingActions()
    {
        return sizeof($this->pendingActions) > 0;
    }

    public function proceedIfPossible()
    {
        if (in_array($this->handPhase, [self::WAITING_PLAYERS_TO_ACT, self::WAITING_PLAYERS])) {
            return;
        }
        if ($this->isPendingActions()) {
            return;
        }

        if ($this->handPhase == self::READY_TO_START) {
            $this->dealPocketCards();
        }

        if ($this->handPhase == self::PREFLOP) {
            $this->dealFlop();
        }

        if ($this->handPhase == self::FLOP) {
            $this->dealTurn();
        }

        if ($this->handPhase == self::TURN) {
            $this->dealRiver();
        }
    }

    private function dealPocketCards()
    {
        $card_index = 0;
        for ($cc = 0; $cc < self::POCKET_CARD_COUNT; $cc++) {
            for ($i = 0; $i < $this->game->players->count(); $i++) {
                $this->createAction([
                    'hand_id' => $this->currentHand->id,
                    'uuid' => Uuid::uuid4(),
                    'data' => [
                        self::KEY => 'pocket_card',
                        'card_index' => $card_index++,
                        'player_uuid' => $this->game->players[$i]->uuid,
                        'seat_number' => $this->game->players[$i]->seat_number
                    ]
                ]);
            }
        }

        $this->createAction([
            'hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                self::KEY => 'new_street_dealt',
                'value' => self::PREFLOP
            ]
        ]);
        $this->createAction([
            'hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                self::KEY => self::ALL_CARDS_REVEALED]
        ]);
        $this->addConfirmRequiredForAllPlayers();
        $this->broadcastMessage();
    }

    private function dealFlop()
    {
        $this->dealCommunityCards(self::FLOP, 3);
    }

    private function dealCommunityCards($streetName, $numberOfCards)
    {
        $card_index = $this->card_index;
        $key = $streetName . '_card';
        for ($i = 0; $i < $numberOfCards; $i++) {
            $this->createAction([
                'hand_id' => $this->currentHand->id,
                'uuid' => Uuid::uuid4(),
                'data' => [
                    self::KEY => $key,
                    'community' => true,
                    'card_index' => $card_index++]
            ]);
        }
        $this->createAction([
            'hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                self::KEY => 'new_street_dealt',
                'value' => $streetName
            ]
        ]);
        if ($streetName != 'river') {
            $this->addConfirmRequiredForAllPlayers();
        } else {
            $this->createAction([
                'hand_id' => $this->currentHand->id,
                'uuid' => Uuid::uuid4(),
                'data' => [
                    self::KEY => self::HAND_ENDED
                ]
            ]);
            $this->game->players->each(function ($player) use ($key) {
                $this->createAction([
                    'hand_id' => $this->currentHand->id,
                    'uuid' => Uuid::uuid4(),
                    'data' => [
                        'key' => 'new_game_option',
                        'options' => [
                            [
                                'key' => 'new_game',
                                'text' => 'New Game!'
                            ]
                        ],
                        'playerUuid' => $player->uuid
                    ]
                ]);
            });
        }
        $this->broadcastMessage();
    }

    private function dealTurn()
    {
        $this->dealCommunityCards(self::TURN, 1);
    }

    private function dealRiver()
    {
        $this->dealCommunityCards('river', 1);
    }

    private function addConfirmRequiredForAllPlayers($key = "Proceed, please!"): void
    {
        $this->game->players->each(function ($player) use ($key) {
            $this->createAction([
                'hand_id' => $this->currentHand->id,
                'uuid' => Uuid::uuid4(),
                'data' => [
                    'key' => 'confirm_required',
                    'options' => [
                        [
                            'key' => 'confirm',
                            'text' => $key
                        ]
                    ],
                    'playerUuid' => $player->uuid
                ]
            ]);
        });
    }

    private function getResult($handValues)
    {
        $handVals = collect($handValues);
        $bestRank = $handVals->map(function ($h) {
            return $h['value'];
        })->min();
        $bestHands = $handVals->filter(function ($h) use ($bestRank) {
            return $bestRank == $h['value'];
        });
        if ($bestHands->count() == 1) {
            $seat = $bestHands->keys()->first();
            $winner = $bestHands->keys();
        }
        return [
            'seats' => $bestHands->keys(),
            'hands' => $bestHands
        ];
    }

    private function createAction($data)
    {
        Action::create($data);
    }

    /**
     * @param $action
     */
    private function broadcastMessage(): void
    {
        GameStateChanged::dispatch($this->game, 'refresh');
    }

    /**
     * @return mixed
     */
    private function getActions()
    {
        $actions = $this->currentHand->actions;
        return $actions;
    }
}
