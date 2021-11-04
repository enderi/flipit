<?php

namespace App\Dealers;

use App\Dealers\PokerGames\Traits\BroadcastStatus;
use App\Dealers\PokerGames\Traits\CreateAction;
use App\DomainObjects\Deck;
use App\DomainObjects\HandAggregate;
use App\Models\Game;
use App\Models\Hand;
use Ramsey\Uuid\Uuid;

abstract class DealerBase
{
    use CreateAction;
    use BroadcastStatus;

    protected Game $game;
    protected Hand $currentHand;
    protected HandAggregate  $handAggregate;

    public abstract function getGameType(): String;
    public abstract function addUserAction(String $actionKey, String $playerUuid);
    public abstract function addUserOption(String $actionKey, String $playerUuid);
    public abstract function tick(String $playerUuid, $forceBroadcast = false): array;

    public function initializeHand(): array
    {
        $deck = new Deck();
        $deck->initialize();
        $deck->shuffle();
        return [
            'data' => [],
            'uuid' => Uuid::uuid4(),
            'ended' => false,
            'deck' => $deck
        ];
    }

    protected abstract function getStatus($playerUuid): array;
    protected abstract function refreshState();

    public abstract function sync();

    public function initWithGame($game)
    {
        $this->game = $game;
        if($game->hand != null) {
            $this->currentHand = $game->hand;
            $this->handAggregate = new HandAggregate($game->hand);
        }
    }

    protected function createAction($data)
    {
        $action = [
            'uuid' => Uuid::uuid4(),
            'game_id' => $this->game->id,
            'hand_id' => $this->currentHand->id,
            'data' => $data
        ];
        //Action::create($action);
        $this->handAggregate->addAction($data);
    }
}
