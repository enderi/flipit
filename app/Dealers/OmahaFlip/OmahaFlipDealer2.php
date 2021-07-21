<?php

namespace App\Dealers\OmahaFlip;

use App\Dealers\TexasFlip\Combinations;
use App\Dealers\OmahaFlip\TexasFlipDealer;
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

class OmahaFlipDealer2
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
    private $currentHand;

    private $game;
    private $players;

    private $pendingActions;
    private $card_index;
    private $allCardsRevealed;
    private $handStatus;

    public function __construct()
    {
        $this->players = collect([]);
        $this->pendingActions = [];
        $this->allCardsRevealed = false;
    }

    public static function of($game): OmahaFlipDealer2
    {
        $dealer = new OmahaFlipDealer2();
        $dealer->setGame($game);

        return $dealer;
    }

    public function setGame($game) {
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
            'code' => random_int(100, 999), //Uuid::uuid4(),
            'expires_at' => Carbon::now()->addHour()
        ]);
        $this->game->invitation()->save($invitation);
    }

    public function joinAsPlayer()
    {
        $player = Player::create([
            'uuid' => Uuid::uuid4(),
            'game_id' => $this->game['id'],
            'seat_number' => $this->game->players->count() +1
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

    private function isPlayerCountValid(): bool
    {
        $playerCount = 0;
        $actions = $this->currentHand->actions;
        for($i=0; $i<$actions->count(); $i++){
            if($actions[$i]->data[self::KEY] == 'player_joined'){
                $playerCount++;
            }
        }
        if($playerCount < $this->game->min_seats || $playerCount > $this->game->max_seats){
            return false;
        }
        return true;
    }

    private function createNewHand()
    {
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

    public function addUserAction($actionKey, $actionUuid, $userUuid)
    {
        $this->createAction([
            'hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                self::KEY => self::PLAYER_ACTION,
                'playerUuid' => $userUuid,
                'action' => $actionKey,
                self::ACTION_UUID => $actionUuid
            ]
        ]);
    }

    public function tick($playerUuid) {
        $this->proceedIfPossible();
        return $this->getStatus($playerUuid);
    }

    public function getStatus($playerUuid): array
    {
        $playerInGame = $this->players->contains(function ($p) use ($playerUuid) {
            return $playerUuid == $p->uuid;
        });
        if($playerInGame) {
            return [];
        }

        $options = [];
        foreach($this->pendingActions as $key=>$value) {
            if($value['playerUuid'] == $playerUuid){
                foreach($value['options'] as $opt) {
                    $opt['uuid'] = $key;
                    $options[] = $opt;
                }
            }
        }

        $cardsPerSeat =  [];
        $seatByPlayerUuid = [];
        $communityCards = collect([]);
        for($i=0; $i<$this->game->players->count(); $i++){
            $pl = $this->game->players[$i];
            $seatByPlayerUuid[$pl->uuid] = $pl->seat_number;
        }
        $actions = $this->currentHand->actions;
        for($i=0; $i<$actions->count(); $i++){
            $action = $actions[$i];
            if($action->data[self::KEY] == 'pocket_card'){
                $seat = $action->data['seat_number'];
                if(!array_key_exists( $seat, $cardsPerSeat)){
                    $cardsPerSeat[$seat] = collect([]);
                }
                $cardsPerSeat[$seat]->push([
                    'card_uuid'=>$action->uuid,
                    'deck_index' => $action->data['card_index']]);
            }
            if(array_key_exists('community', $action->data) && $action->data['community'] == true){
                $communityCards->push([
                    'card_uuid' => $action->uuid,
                    'deck_index' => $action->data['card_index']]);
            }
        }


        $handValues = $this->getHandValues($cardsPerSeat, $communityCards);
        $result = null;
        if($this->handStatus == self::HAND_ENDED) {
            $result = $this->getResult($handValues);
        }
        return [
            'mySeat' =>$seatByPlayerUuid[$playerUuid],
            'cardsPerSeat' => $cardsPerSeat,
            'communityCards' => $communityCards,
            'options' => $options,
            'revealedCards' => $this->mapRevealedCards($playerUuid),
            'handValues' => $handValues,
            'handStatus' => $this->handStatus,
            'handResult' => $result
        ];
    }

    private function getHandValues($cardsPerSeat, $communityCards) {
        $evaluator = new evaluate();
        $bestHandsBySeat = [];
        if(sizeof($communityCards) <3) {
            return [];
        }
        foreach($cardsPerSeat as $seat=>$cs) {
            $handCombinations = [];
            foreach(new Combinations($cs->toArray(), 2) as $c) {
                $handCombinations[] = $c;
            }

            $tableCombinations = [];
            foreach(new Combinations($communityCards->toArray(), 3) as $c) {
                $tableCombinations[] = $c;
            }

            $bestHand = null;
            for($i = 0; $i < sizeof($handCombinations); $i++){
                for($j = 0; $j < sizeof($tableCombinations); $j++) {
                    $hand = array_merge($handCombinations[$i], $tableCombinations[$j]);
                    $cards = [];
                    foreach($hand as $cardForThis) {
                        $cards[] = $this->currentHand->getDeck()->getIndex($cardForThis['deck_index']);
                    }
                    $value = $evaluator->getValue($cards);
                    $name = $evaluator->getHandName();
                    if($bestHand == null || $bestHand['value'] > $value) {
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

    private function findPendingActions() {
        $actions = $this->getActions();
        $requiredActions = [];
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            $act = $action->data[self::KEY];
            if($act == self::CONFIRM_REQUIRED) {
                $requiredActions[$action->uuid] = $action->data;
            } else if($act == self::PLAYER_ACTION) {
                $key = $action->data[self::ACTION_UUID];
                unset($requiredActions[$key]);
            }
            if($act == self::ALL_CARDS_REVEALED){
                $this->allCardsRevealed = true;
            }
        }
        $this->pendingActions = $requiredActions;
    }

    private function figureHandStatus()
    {
        if(!$this->isPlayerCountValid()){
            return self::WAITING_PLAYERS;
        }
        if($this->isPendingActions()){
            return self::WAITING_PLAYERS_TO_ACT;
        }

        $status = 'NOT_STARTED';
        $actions = $this->currentHand->actions;
        $card_index = 0;
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            $act = $action->data[self::KEY];
            if(array_key_exists('card_index', $action->data)){
                $card_index++;
            }
            if($act == 'new_street_dealt'){
                $status = $actions[$i]->data['value'];
            }
            if($act == self::HAND_ENDED){
                $status = self::HAND_ENDED;
            }
            if($act == self::ALL_CARDS_REVEALED){
                $status = self::ALL_CARDS_REVEALED;
            }
        }
        $this->card_index = $card_index;
        return $status;
    }

    private function mapRevealedCards($playerUuid): array
    {
        $values = [];
        $actions = $this->currentHand->actions;
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            $isCard = array_key_exists('card_index', $action->data);
            if(!$isCard){
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

    private function isPendingActions(){
        return sizeof($this->pendingActions) > 0;
    }

    public function proceedIfPossible()
    {
        $this->findPendingActions();
        $this->handStatus = $this->figureHandStatus();

        if(in_array($this->handStatus, [self::WAITING_PLAYERS_TO_ACT, self::WAITING_PLAYERS])){
            return;
        }
        if($this->handStatus == 'NOT_STARTED'){
            $this->dealPocketCards();
            return;
        }
        if($this->handStatus == 'pocket_cards'){
            $this->revealPocketCards();
        }
        if($this->handStatus == self::ALL_CARDS_REVEALED){
            $this->dealFlop();
        }

        if($this->handStatus == 'flop'){
            $this->dealTurn();
        }

        if($this->handStatus == 'turn'){
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
                'value' => 'pocket_cards'
            ]
        ]);
        $this->addConfirmRequiredForAllPlayers();
        $this->broadcastMessage();
    }

    private function revealPocketCards()
    {
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
        $this->dealCommunityCards('flop', 3);
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
            $this->game->players->each(function ($player) use ($key){
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
        $this->dealCommunityCards('turn', 1);
    }

    private function dealRiver()
    {
        $this->dealCommunityCards('river', 1);
    }

    private function addConfirmRequiredForAllPlayers($key = "Let's go!"): void
    {
        $this->game->players->each(function ($player) use ($key){
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
        $bestRank = $handVals->map(function($h){
            return $h['value'];
        })->min();
        $bestHands = $handVals->filter(function($h) use ($bestRank){
            return $bestRank == $h['value'];
        });
        if($bestHands->count() == 1){
            $seat = $bestHands->keys()->first();
            $winner = $bestHands->keys();
        }
        return [
            'seats' => $bestHands->keys(),
            'hands' => $bestHands
        ];
    }

    private function createAction($data){
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
