<?php

namespace App\Dealers\FourStreetGames;

use App\Dealers\OmahaFlip\OmahaFlipDealer;
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
use DASPRiD\Enum\Exception\IllegalArgumentException;
use Ramsey\Uuid\Uuid;

abstract class HoldemBaseDealer_old {
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
    const CARDS_REVEALED = 'CARDS_REVEALED';

    private $cards;
    private $actions;
    private $allCardsRevealed;
    private $options;
    private $waitingActions;
    private $gameStatus;

    private $currentHand;
    private $game;
    private $players;

    private $card_index;
    private $handPhase;


    private $pendingActions;
    private $handStatus;

    public function initWithGame($game)
    {
        $this->setGame($game);
        $this->refreshState();
    }
    private function refreshState() {
        $this->actions = $this->getActions();
        $this->allCardRevealed = $this->areAllCardRevealed();
        $this->seatByPlayerUuid = $this->getPlayersBySeat();
        $this->parseActions();
    }

    private function setGame($game)
    {
        $this->game = $game;
        $this->currentHand = $game->getCurrentHand();
    }

    public abstract function getGameType();
    public abstract function getCardCount();

    public function newGame()
    {
        $this->game = Game::create([
            'uuid' => Uuid::uuid4(),
            'game_type' => $this->getGameType(),
            'min_seats' => self::MIN_SEATS,
            'max_seats' => self::MAX_SEATS,
            'information' => array()]);
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
            'seat_number' => $this->game->players->count() + 1,
            'ready' => 1
        ]);

        $mapping = GamePlayerMapping::create(
            [
                'uuid' => Uuid::uuid4(),
                'game_id' => $this->game->id,
                'player_id' => $player->id
            ]);

        $this->proceedIfPossible();
        return $mapping;
    }

    public function addUserAction($actionKey, $playerUuid)
    {
        $this->createAction([
            'hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                self::KEY => self::PLAYER_ACTION,
                'player_uuid' => $playerUuid,
                'action' => $actionKey
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
        $newHandRequests = $this->currentHand->actions->filter(function ($action) {
            return $action->data[self::KEY] == self::REQUEST_NEW_HAND;
        })->map(function ($action) {
            return $action->data['playerUuid'];
        });

        $allFound = $this->game->players->map(function ($player) {
            return $player->uuid;
        })->every(function ($value) use ($newHandRequests) {
            return $newHandRequests->contains($value);
        });

        if ($allFound && $newHandRequests->count() == $this->game->players->count()) {
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

    private function parseActions() {
        $this->gameStatus = 'initialized';
        if($this->game->players->count() != 2) {
            $this->gameStatus = 'waiting_for_opponent';
            return;
        }
        $cards = [];
        $playerUuids = collect([]);
        foreach($this->game->players as $p) {
            $cards[$p['seat_number']] = collect([]);
            $playerUuids->push($p['uuid']);
        }

        $waitingActions = collect([]);

        foreach($this->actions as $a) {
            $data = $a->data;
            if($data['key'] == 'pocket_card') {
                if(!$waitingActions->isEmpty()){
                    throw new IllegalArgumentException("Can't deal before players have acted");
                }
                $seatNo = $data['seat_number'];
                $cards[$seatNo]->push($data['card']);
            }
            if($data['key'] == 'new_street_dealt'){
                $waitingActions = collect($playerUuids);
                $this->gameStatus = $data['value'];
            }
            if($data['key'] == self::PLAYER_ACTION) {
                $waitingActions = $waitingActions->filter(function($a) use ($data){
                    return $a != $data['player_uuid'];
                });
            }
        }
        $this->cards = $cards;
        $options = [];
        if(!$waitingActions->isEmpty()){
            foreach($waitingActions as $a) {
                $seat = $this->seatByPlayerUuid[$a];
                if(!array_key_exists($seat, $options)){
                    $options[$seat] = collect([]);
                }
                $options[$seat]->push([
                    'text' => 'Go ahead',
                    'key' => 'confirm',
                ]);
            }
        } else {
        }

        $this->options = $options;
    }

    private function getStatus($playerUuid): array
    {
        $mySeat = $this->seatByPlayerUuid[$playerUuid];

        return [
            'cards' => $this->cards,
            'mySeat' => $mySeat,
            'options' => $this->options,
            'myPlayerUuid' => $playerUuid,
            'handStatus' => $this->gameStatus
        ];
    }


    private function getStatusBackup($playerUuid): array
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
        $seatByPlayerUuid = $this->getPlayersBySeat();

        $mySeat = $seatByPlayerUuid[$playerUuid];
        $opponentSeat = $mySeat == 1 ? 2 : 1;
        if($this->currentHand->result != null) {
            $resultMapped = [
                'me' => $this->currentHand->result[$mySeat],
                'villain' => $this->currentHand->result[$opponentSeat]
            ];
        } else {
            $resultMapped = [];
        }

        return [
            'results' => $resultMapped,
            'mySeat' => $mySeat,
            'options' => $options,
            'handPhase' => $this->handPhase,
            'handStatus' => $this->resolveHandStatus($playerUuid, $seatByPlayerUuid)
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
        foreach($this->game->players as $player) {
            $this->createAction([
                'hand_id' => $this->currentHand->id,
                'uuid' => Uuid::uuid4(),
                'data' => [
                    'player_uuid' => $player->uuid,
                    'seat_number' => $player->seat_number,
                    self::KEY => 'player_joined'
                ]
            ]);
        }

    }

    protected abstract function getHandValues($cs, $communityCardsItems);
    protected abstract function getBestHand($handCards, $communityCards): array;

    private function resolveHandStatus($playerUuid, $seatByPlayerUuid): array
    {
        $revealedCards = $this->mapRevealedCards($playerUuid);
        $actions = $this->currentHand->actions;
        $communityCards = collect([]);
        $myCards = collect([]);
        $opponentCards = collect([]);
        $mySeat = $seatByPlayerUuid[$playerUuid];
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            if ($action->data[self::KEY] == 'pocket_card') {
                $seat = $action->data['seat_number'];
                if ($mySeat == $seat) {
                    $myCards->push([
                        'card_uuid' => $action->uuid,
                        'deck_index' => $action->data['card_index'],
                        'card' => $revealedCards[$action->uuid]]);
                } else {
                    $opponentCards->push([
                        'card_uuid' => $action->uuid,
                        'deck_index' => $action->data['card_index'],
                        'card' => $revealedCards[$action->uuid] ?? null]);
                }
            }
            if (array_key_exists('community', $action->data) && $action->data['community'] == true) {
                $communityCards->push([
                    'card_uuid' => $action->uuid,
                    'deck_index' => $action->data['card_index'],
                    'card' => $revealedCards[$action->uuid] ?? null]);
            }
        }

        $myHandValue = $this->getHandValues($myCards, $communityCards);
        $opponentHandValue = $this->getHandValues($opponentCards, $communityCards);

        if($myHandValue == null || $opponentHandValue == null){
            return [
                'result' => null,
                'myCards' => $myCards,
                'opponentCards' => $opponentCards,
                'communityCards' => $communityCards,
                'myHandValue' => $myHandValue,
                'opponentHandValue' => $opponentHandValue,
            ];
        }
        $result = 'tie';
        if($myHandValue['value'] < $opponentHandValue['value']){
            $result = 'win';
        } else if($myHandValue['value'] > $opponentHandValue['value']){
            $result = 'loss';
        }
        return [
            'mySeat' => $seatByPlayerUuid[$playerUuid],
            'result' => $result,
            'myCards' => $myCards,
            'opponentCards' => $opponentCards,
            'communityCards' => $communityCards,
            'myHandValue' => $myHandValue,
            'opponentHandValue' => $opponentHandValue,
        ];
    }

    private function resolveResult() {
        $cardsByPosition = $this->mapCardsByPosition();

        $handValuesBySeat = [];
        $bestHandValue = null;
        foreach($cardsByPosition['handsBySeat'] as $seat=>$cards) {
            $currHandValue = $this->getBestHand($cards, $cardsByPosition['community']);
            $handValuesBySeat[$seat] = $currHandValue;
            if($bestHandValue == null || $bestHandValue > $currHandValue['value']){
                $bestHandValue = $currHandValue['value'];
            }
        }
        $result = [];
        foreach($handValuesBySeat as $seat=>$handValue) {
            $seatResult = ['handValue' => $handValue];
            if($handValue['value'] == $bestHandValue) {
                $seatResult['result'] = 'win';
            } else {
                $seatResult['result'] = 'lose';
            }
            $result[$seat] = $seatResult;
        }
        $this->currentHand->result = $result;
        $this->currentHand->save();
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

    private function mapCardsByPosition(): array
    {
        if(!$this->allCardsRevealed){
            return [];
        }
        $playersBySeat = $this->getPlayersBySeat();
        $values = [];
        $actions = $this->currentHand->actions;
        $communityCards = collect([]);
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            $isCard = array_key_exists('card_index', $action->data);
            if (!$isCard) {
                continue;
            }
            $communityCard = array_key_exists('community', $action->data) && $action->data['community'] == true;
            if($communityCard){
                $communityCards->push($this->currentHand->getDeck()->getIndex($action->data['card_index'])->toString());
            }
            if (!$communityCard && $this->allCardsRevealed) {
                $seat = $playersBySeat[$action->data['player_uuid']];
                if(!array_key_exists($seat,  $values)){
                    $values[$seat] = collect([]);
                }
                $values[$seat]->push($this->currentHand->getDeck()->getIndex($action->data['card_index'])->toString());
            }
        }
        return [
            'community' => $communityCards,
            'handsBySeat' => $values
        ];
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
        if($this->options != null) {
            return sizeof($this->options) > 0;
        } else {
            return false;
        }
    }

    public function proceedIfPossible() : bool
    {
        if($this->currentHand == null) {
            if($this->game->players->count() == 2){
                $this->createNewHand();
                $this->dealPocketCards();
                return true;
            } else {
                return false;
            }
        }

        if (in_array($this->handPhase, [self::WAITING_PLAYERS_TO_ACT, self::WAITING_PLAYERS])) {
            return false;
        }
        if ($this->isPendingActions()) {
            return false;
        }
        if ($this->handPhase == self::READY_TO_START) {
            $actions = $this->dealPocketCards();
            $actions->each(function($action) {
                $this->createAction($action);
            });
            $this->broadcastMessage();
        }

        if ($this->handPhase == self::PREFLOP) {
            $this->createAction([
                'hand_id' => $this->currentHand->id,
                'uuid' => Uuid::uuid4(),
                'data' => [
                    self::KEY => self::ALL_CARDS_REVEALED]
            ]);
            $this->createAction([
                'hand_id' => $this->currentHand->id,
                'uuid' => Uuid::uuid4(),
                'data' => [
                    self::KEY => 'cards_revealed'
                ]
            ]);
            $this->addConfirmRequiredForAllPlayers("Deal the flop!");
            $this->broadcastMessage();
        }

        if ($this->handPhase == self::CARDS_REVEALED) {
            $this->dealFLop();
        }

        if ($this->handPhase == self::FLOP) {
            $this->dealTurn();
        }

        if ($this->handPhase == self::TURN) {
            $this->dealRiver();
        }
        return false;
    }

    protected function getCurrentHand() {
        return $this->currentHand;
    }

    private function dealPocketCards()
    {
        $dealtCards = collect([]);
        $card_index = 0;
        for ($cc = 0; $cc < $this->getCardCount(); $cc++) {
            for ($i = 0; $i < $this->game->players->count(); $i++) {
                $action = [
                    'hand_id' => $this->currentHand->id,
                    'uuid' => Uuid::uuid4(),
                    'data' => [
                        self::KEY => 'pocket_card',
                        'card_index' => $card_index++,
                        'card' => $this->currentHand->getDeck()->getIndex($card_index)->toString(),
                        'player_uuid' => $this->game->players[$i]->uuid,
                        'seat_number' => $this->game->players[$i]->seat_number
                    ]
                    ];
                $dealtCards->push($action);
            }
        }

        $streetDealtAction = [
            'hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                self::KEY => 'new_street_dealt',
                'value' => self::PREFLOP
            ]
            ];
        $dealtCards->push($streetDealtAction);
        return $dealtCards;
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
            $this->resolveResult();

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

    private function broadcastStatus(): void
    {
        GameStateChanged::dispatch($this->game, 'refresh');
    }

    /**
     * @return mixed
     */
    private function getActions()
    {
        if($this->currentHand != null) {
            return $this->currentHand->actions;
        } else {
            return collect([]);
        }
    }

    /**
     * @return array
     */
    private function getPlayersBySeat(): array
    {
        $seatByPlayerUuid = [];
        for ($i = 0; $i < $this->game->players->count(); $i++) {
            $pl = $this->game->players[$i];
            $seatByPlayerUuid[$pl->uuid] = $pl->seat_number;
        }
        return $seatByPlayerUuid;
    }

    /**
     * @param $actions
     * @return mixed
     */
    private function areAllCardRevealed()
    {
        return $this
            ->actions
            ->map(function ($a) {
                return $a['key'];
            })->contains(self::ALL_CARDS_REVEALED);
    }
}
