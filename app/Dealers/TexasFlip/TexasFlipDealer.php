<?php

namespace App\Dealers\TexasFlip;

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

class TexasFlipDealer
{
    const TEXAS_FLIP = 'texas-flip';
    const MIN_SEATS = 2;
    const MAX_SEATS = 2;
    const POCKET_CARD_COUNT = 2;
    private $currentHand;

    private $game;
    private $players;

    private $pendingActions;
    private $card_index;
    private $allCardsRevealed;

    public function __construct()
    {
        $this->players = collect([]);
        $this->pendingActions = [];
        $this->allCardsRevealed = false;
    }

    public static function of($game): TexasFlipDealer
    {
        $dealer = new TexasFlipDealer();
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
            'game_type' => self::TEXAS_FLIP,
            'min_seats' => self::MIN_SEATS,
            'max_seats' => self::MAX_SEATS,
            'information' => array()]);
        $this->createNewHand();
        $invitation = new Invitation([
            'code' => Uuid::uuid4(),
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
                'key' => 'player_joined'
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
            if($actions[$i]->data['key'] == 'player_joined'){
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
                'key' => 'player_action',
                'playerUuid' => $userUuid,
                'action' => $actionKey,
                'actionUuid' => $actionUuid
            ]
        ]);
    }

    public function tick($playerUuid) {
        $this->proceedIfPossible();
        $result = $this->getStatus($playerUuid);
        return $result;
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
            if($action->data['key'] == 'pocket_card'){
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

        return [
            'mySeat' =>$seatByPlayerUuid[$playerUuid],
            'cardsPerSeat' => $cardsPerSeat,
            'communityCards' => $communityCards,
            'options' => $options,
            'revealedCards' => $this->mapRevealedCards($playerUuid),
            'handValues' => $this->getHandValues($cardsPerSeat, $communityCards)
        ];
    }

    private function getHandValues($cardsPerSeat, $communityCards) {
        $evaluator = new evaluate();
        $bestHandsBySeat = [];
        foreach($cardsPerSeat as $seat=>$cs) {
            $playerCardsInUse = [];
            foreach($communityCards as $c) {
                $playerCardsInUse[] = $c;
            }
            foreach($cs as $c) {
                $playerCardsInUse[] = $c;
            }
            $combinations = [];
            $bestHand = null;
            if(sizeof($playerCardsInUse) < 5){
                return [];
            }
            foreach(new Combinations($playerCardsInUse, min(sizeof($playerCardsInUse), 5)) as $c) {
                $combinations[] = $c;
                $cards = [];
                foreach($c as $cardForThis) {
                    $cards[] = $this->currentHand->getDeck()->getIndex($cardForThis['deck_index']);
                }
                $value = $evaluator->getValue($cards);
                $name = $evaluator->getHandName();
                if($bestHand == null || $bestHand['value'] > $value) {
                    $bestHand = [
                        'value' => $value,
                        'cards' => $c,
                        'name' => $name
                    ];
                }
            }
            $bestHandsBySeat[$seat] = $bestHand;
        }

        return $bestHandsBySeat;
    }

    private function findPendingActions() {
        $actions = $this->currentHand->actions;
        $requiredActions = [];
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            $act = $action->data['key'];
            if($act == 'confirm_required') {
                $requiredActions[$action->uuid] = $action->data;
            } else if($act == 'player_action') {
                $key = $action->data['actionUuid'];
                unset($requiredActions[$key]);
            }
            if($act == 'all_cards_revealed'){
                $this->allCardsRevealed = true;
            }
        }
        $this->pendingActions = $requiredActions;
    }

    private function figureHandStatus()
    {
        if($this->currentHand->actions->count() == 0) {
            return 'WAITING_PLAYERS';
        }
        $requiredActions = [];

        $status = 'NOT_STARTED';
        $actions = $this->currentHand->actions;
        $card_index = 0;
        for ($i = 0; $i < $actions->count(); $i++) {
            $action = $actions[$i];
            $act = $action->data['key'];
            if(array_key_exists('card_index', $action->data)){
                $card_index++;
            }
            if($act == 'new_street_dealt'){
                $status = $actions[$i]->data['value'];
            } else if($act == 'confirm_required') {
                $requiredActions[$action->uuid] = $action->data;
            } else if($act == 'player_action') {
                $key = $action->data['action'];
                unset($requiredActions[$key]);
            }
            if($act == 'all_cards_revealed'){
                $status = 'all_cards_revealed';
            }
        }
        $this->pendingActions = $requiredActions;
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
        if(!$this->isPlayerCountValid() || $this->isPendingActions()){
            return;
        }
        $status = $this->figureHandStatus();

        if($status == 'NOT_STARTED'){
            $this->dealPocketCards();
            return;
        }
        if($status == 'pocket_cards'){
            $this->revealPocketCards();
        }
        if($status == 'all_cards_revealed'){
            $this->dealFlop();
        }

        if($status == 'flop'){
            $this->dealTurn();
        }

        if($status == 'turn'){
            $this->dealRiver();
        }

        if($status == 'river') {
            $this->decideWinner();
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
                        'key' => 'pocket_card',
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
                'key' => 'new_street_dealt',
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
                'key' => 'all_cards_revealed']
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
                    'key' => $key,
                    'community' => true,
                    'card_index' => $card_index++]
            ]);
        }
        $this->createAction([
            'hand_id' => $this->currentHand->id,
            'uuid' => Uuid::uuid4(),
            'data' => [
                'key' => 'new_street_dealt',
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
                    'key' => 'hand_ended'
                ]
            ]);
            $this->addConfirmRequiredForAllPlayers('New Hand!');
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

    private function decideWinner()
    {

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
}
