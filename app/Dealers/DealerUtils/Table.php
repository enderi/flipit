<?php

namespace App\Dealers\DealerUtils;


class Table
{
    private array $seats;
    private array $seatByUuid;

    public function __construct($players) {
        foreach ($players as $player) {
            $this->addPlayer($player);
        }
    }

    private function addPlayer($player)
    {
        $this->seats[$player['seat_number']] = $player['uuid'];
        $this->seatByUuid[$player['uuid']] = $player['seat_number'];
    }

    public function getSeatForUuid($playerUuid)
    {
        return $this->seatByUuid[$playerUuid];
    }

    public function getPlayerCount()
    {
        return count($this->seatByUuid);
    }

    public function getSeatNumbers() {
        return array_keys($this->seats);
    }
}
