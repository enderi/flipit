<?php

namespace App\Dealers\DealerUtils;


class Table
{
    private array $nonBotSeats;
    private array $seats;
    private array $seatByUuid;

    public function __construct($players) {
        $this->nonBotSeats = array();
        foreach ($players as $player) {
            $this->addPlayer($player);
        }
    }

    private function addPlayer($player)
    {
        $this->seats[$player['seat_number']] = $player['uuid'];
        $this->seatByUuid[$player['uuid']] = $player['seat_number'];
        if($player->data == null || $player->data['computer'] != true) {
            $this->nonBotSeats[] = $player['seat_number'];
        }
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

    public function getNonBotSeats() {
        return $this->nonBotSeats;
    }
}
