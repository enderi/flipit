<?php

namespace App\DomainObjects;

use App\Dealers\BaseDealer;
use App\Dealers\DealerBase;
use App\Models\Hand;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class GameAggregate
{
    private String $uuid;
    private String $gameType;
    private int $requiredPlayers;
    private Collection $players;
    private $invitationCode;
    private $initialHand;

    private $uuidOfRequestPlayer;

    public function __construct($uuid, $gameType, $players, $invitationCode, $uuidOfRequestPlayer)
    {
        $this->uuid = $uuid;
        $this->gameType = $gameType;
        $this->requiredPlayers = Defaults::$gameInfo[$gameType]['seats'];
        $this->players = collect($players);
        $this->invitationCode = $invitationCode;
        $this->uuidOfRequestPlayer = $uuidOfRequestPlayer;
    }

    public static function newGame($gameType): GameAggregate
    {
        return new GameAggregate(
            Uuid::uuid4(),
            $gameType,
            collect([]),
            Uuid::uuid4(),
            null);
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'game_type' => $this->gameType,
            'seats' => $this->requiredPlayers,
            'information' => array(),
            'invitationCode' => $this->invitationCode
        ];
    }

    public function areSeatsFilled(): bool
    {
        return $this->players->count() == $this->requiredPlayers;
    }

    public function joinGame()
    {
        $playerUuid = Uuid::uuid4();
        $this->players->push([
            'uuid' => $playerUuid,
            'seat_number' => $this->players->count() + 1
        ]);
        $this->uuidOfRequestPlayer = $playerUuid;
        if($this->areSeatsFilled()) {
            $this->initializeGame();
        }
    }

    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function getUuidOfRequestedPlayer()
    {
        return $this->uuidOfRequestPlayer;
    }

    public function isOfType(string $type): bool
    {
        return $this->gameType == $type;
    }

    public function getType() : string {
        return $this->gameType;
    }

    public function getDealer(): BaseDealer
    {
        return new Defaults::$gameInfo[$this->gameType]['dealer'];
    }

    public function initializeGame()
    {
        $dealer = $this->getDealer();
        $this->initialHand = $dealer->initializeHand();
    }
    public function getInitialHand() {
        return $this->initialHand;
    }
}
