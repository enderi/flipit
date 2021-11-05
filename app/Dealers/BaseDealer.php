<?php

namespace App\Dealers;

use App\Dealers\DealerUtils\ActionHandler;
use App\Dealers\DealerUtils\Table;
use App\Dealers\PokerGames\Traits\BroadcastStatus;
use App\Dealers\PokerGames\Traits\CreateAction;
use App\DomainObjects\Card;
use App\DomainObjects\Deck;
use Ramsey\Uuid\Uuid;

abstract class BaseDealer
{
    use CreateAction;
    use BroadcastStatus;

    const DEALER_MAY_PROCEED = "DEALER_MAY_PROCEED";
    const KEY = "key";
    const POCKET_CARD = "pocket_card";

    protected Table $table;
    protected int $dealtCards;
    protected $state;
    private array $actions = [];
    private array $dealtCardsByTarget = [];

    protected Deck $deck;

    private array $actionsToSave = [];
    protected ActionHandler $blockingActions;
    protected ActionHandler $nonBlockingActions;
    private array $cardsInDealOrder = [];

    abstract public function refreshState();

    abstract protected function processAction($data);

    public function __construct()
    {
        $this->state = self::DEALER_MAY_PROCEED;
        $this->dealtCards = 0;
    }

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

    public function initWithHand($players, $actions, Deck $deck)
    {
        $this->table = new Table($players);
        $this->blockingActions = new ActionHandler($this->getNonComputerControlledSeats());
        $this->nonBlockingActions = new ActionHandler($this->getNonComputerControlledSeats());
        $this->deck = $deck;

        foreach ($actions as $action) {
            $this->handleAction($action);
        }
    }

    public function getSeatNumbers()
    {
        return $this->table->getSeatNumbers();
    }

    protected function getSeatForPlayerUuid($playerUuid)
    {
        return $this->table->getSeatForUuid($playerUuid);
    }

    protected function dealCard($cardIndex, $target): Card
    {
        $card = $this->getCardWithIndex($cardIndex);
        $this->dealtCards++;
        $this->pushCardToTarget($target, $card);
        return $card;
    }

    public function dealNextCard($cardIndex, $target): Card
    {
        $card = $this->getCardWithIndex($cardIndex);
        $this->pushCardToTarget($target, $card);
        return $card;
    }

    protected function addNewAction($data)
    {
        $this->actionsToSave[] = $data;
        $this->processAction($data);
    }

    public function getActionsToSave()
    {
        return $this->actionsToSave;
    }

    private function handleAction($action)
    {
        $this->processAction($action->data);
        $this->actions[] = $action;
    }

    public function getPlayerCount()
    {
        return $this->table->getPlayerCount();
    }

    public function getNonComputerControlledSeats() {
        return $this->table->getNonBotSeats();
    }

    public function getStatus($playerUuid): array
    {
        $mySeat = $this->table->getSeatForUuid($playerUuid);
        return [
            'mySeat' => $mySeat,
            'actions' => $this->blockingActions->getOptions(),
            'options' => $this->nonBlockingActions->getOptions(),
            'myPlayerUuid' => $playerUuid,
            'handStatus' => $this->state,
            'cardsInDealOrder' => $this->getCardsInDealOrder($mySeat),
//            'result' => $this->game->hand->result,
            'handValues' => []
        ];
    }

    /**
     * @param $target
     */
    protected function initializeCardArrayForTarget($target): void
    {
        if (!key_exists($target, $this->dealtCardsByTarget)) {
            $this->dealtCardsByTarget[$target] = [];
        }
    }

    /**
     * @return mixed
     */
    protected function getCardWithIndex($cardIndex)
    {
        return $this->deck->getIndex($cardIndex);
    }

    /**
     * @param $target
     * @param $card
     */
    public function pushCardToTarget($target, Card $card): void
    {
        $this->initializeCardArrayForTarget($target);
        array_push($this->dealtCardsByTarget[$target], $card);
        array_push($this->cardsInDealOrder, [
            'card' => $card->toString(),
            'target' => $target,
            'card_index' => count($this->cardsInDealOrder)
        ]);
    }

    private function getCardsInDealOrder($mySeat)
    {
        return $this->cardsInDealOrder;
    }
}
