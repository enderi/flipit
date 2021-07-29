<?php

namespace App\Dealers\TrickGame;

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
use Ramsey\Uuid\Uuid;

class LastTrickDealer
{
    const LAST_TRICK = 'LAST-TRICK';
    const MIN_SEATS = 2;
    const MAX_SEATS = 2;
    const POCKET_CARD_COUNT = 5;
    const CONFIRM_REQUIRED = 'confirm_required';
    const PLAYER_ACTION = 'player_action';
    const ACTION_UUID = 'actionUuid';
    const KEY = 'key';
    const ALL_CARDS_REVEALED = 'ALL_CARDS_REVEALED';
    const WAITING_PLAYERS_TO_ACT = 'WAITING_PLAYERS_TO_ACT';
    const WAITING_PLAYERS = 'WAITING_PLAYERS';
    const HAND_ENDED = 'HAND_ENDED';
    const CARDS_DEALT = 'CARDS_DEALT';
    const READY_TO_START = 'READY_TO_START';

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
    public static function of($game): LastTrickDealer
    {
        $result = new LastTrickDealer();
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
            'game_type' => self::LAST_TRICK,
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
        return [
            'actions' => $this->currentHand->actions, // todo: remove this
            'mySeat' => $seatByPlayerUuid[$playerUuid],
            'cardsPerSeat' => $cardsPerSeat,
            'revealedCards' => $this->mapRevealedCards($playerUuid),

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
            'data' => [],
            'uuid' => Uuid::uuid4(),
            'ended' => false,
            'deck' => $deck->toString()
        ]);
    }

    private function getHandValues($cardsPerSeat, $communityCards)
    {
        $bestHandsBySeat = [];
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
                'value' => self::CARDS_DEALT
            ]
        ]);
        $this->broadcastMessage();
    }

    private function createAction($data)
    {
        $data['game_id'] = $this->game->id;
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
