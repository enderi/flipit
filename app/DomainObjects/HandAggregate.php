<?php

namespace App\DomainObjects;

use App\Dealers\DealerBase;
use App\Models\Action;
use App\Models\Hand;
use Ramsey\Uuid\Uuid;

class HandAggregate
{
    private $id;
    private Deck $deck;
    /** @var Action[] */
    private $actions;
    private DealerBase $dealerBase;

    public function __construct(DealerBase $dealer, ?Hand $hand) {
        $this->id = $hand->id;
        $this->deck = $hand->getDeck();
        $this->actions = $hand->actions()->get();
        $this->dealerBase = $dealer;
    }

    public function addAction($data) {
        $actionData = [
            'uuid' => Uuid::uuid4(),
            'hand_id' => $this->id,
            'data' => $data
        ];
        $action = new Action();
        $action->fill($actionData);
        $this->actions->push($action);
    }

    public function getDealer()
    {
        return $this->dealerBase;
    }
}
