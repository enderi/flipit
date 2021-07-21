<?php

namespace Tests\Unit;

use App\Dealers\OmahaFlipDealer;
use App\Models\Player;
use PHPUnit\Framework\TestCase;

class OmahaFlipDealerTest extends TestCase
{

    // public function test_omaha_dealer_should_initialize()
    // {
    //     $dealer = new OmahaFlipDealer([],[]);
        
    //     $this->assertNotNull($dealer);
    // }

    // public function test_dealer_should_initialize_game() {
    //     $dealer = new OmahaFlipDealer([],[]);
        
    //     $game = $dealer->createGame();

    //     $this->assertNotNull($game);
    // }

    // public function test_dealer_should_accept_players_until_max_limit_is_reached() {
    //     $dealer = new OmahaFlipDealer([],[]);
    //     $game = $dealer->createGame();
    //     $game->setMaxPlayers(2);

    //     $game->join($player1);
    //     $game->join($player2);
        

    //     $this->assertNotNull($game);
    // }


    // public function test_omaha_dealer_should_offer_no_action_when_only_no_players() {
    //     $dealer = new OmahaFlipDealer([], []);

    //     $status = $dealer->getStatus('hep');
        
    //     $this->assertNotNull($status);
    //     $this->assertEmpty($status);
    // }

    // public function test_omaha_dealer_should_offer_no_action_when_only_one_player() {
    //     $player = new Player();
    //     $player->uuid = 'hiiohoi';
    //     $dealer = new OmahaFlipDealer([$player], []);

    //     $status = $dealer->getStatus('hep');

    //     $this->assertNotNull($status);
    //     $this->assertEmpty($status);
    // }

    // public function test_omaha_dealer_should_offer_start_as_initial_action_when_at_least_two_players() {
    //     $player1 = new Player();
    //     $player1->uuid = 'abc';
    //     $player2 = new Player();
    //     $player2->uuid = 'def';
    //     $dealer = new OmahaFlipDealer([$player1, $player2], []);

    //     $status = $dealer->getStatus($player1->uuid);
    //     $this->assertNotNull($status);
    //     $this->assertNotEmpty($status);
    //     $this->assertEquals(['name' => 'begin'], $status->first());
    // }

    // public function test_dealer_should_check_player_uuids_to_allow_starting_game() {
    //     $player1 = new Player();
    //     $player1->uuid = 'abc';
    //     $player2 = new Player();
    //     $player2->uuid = 'def';
    //     $dealer = new OmahaFlipDealer([$player1, $player2], []);

    //     $status = $dealer->getStatus('hep');
    //     $this->assertNotNull($status);
    //     $this->assertEmpty($status);
    // }
}
