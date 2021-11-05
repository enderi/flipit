<?php

namespace App\Services;

use App\Dealers\DealerBase;
use App\DomainObjects\GameAggregate;
use App\Events\PlayerJoined;
use App\Models\Game;
use App\Repositories\GameRepository;

class GameService {
    private DealerService $dealerService;
    private GameRepository $gameRepository;

    public function __construct(
        DealerService $dealerService,
        GameRepository $gameRepository)
    {
        $this->dealerService = $dealerService;
        $this->gameRepository = $gameRepository;
    }

    public function buildGameInfoForMappingUuid($gameUuid, $playerUuid) :GameAggregate {
        return $this->buildExistingGameAggregate($gameUuid, $playerUuid);
    }

    public function getDealer($gameUuid, $playerUuid) : DealerBase{
        $game = $this->buildExistingGameAggregate($gameUuid, $playerUuid);
        return $game->getDealer();
    }

    public function newGame($gameType) : GameAggregate{
        $g = $this->buildNewGameAggregate($gameType);
        $g->joinGame();
        $this->gameRepository->persistGameAggregate($g);
        return $g;
    }

    public function joinWithGameUuid($gameUuid) : GameAggregate{
        $g = $this->buildExistingGameAggregate($gameUuid, null);
        if($g->areSeatsFilled()) {
            throw new Exception('Seats already taken');
        }
        $g->joinGame();
        $this->gameRepository->persistGameAggregate($g);
        if($g->areSeatsFilled()) {
            PlayerJoined::dispatch($g->getUuid(), 'ALL_JOINED');
        }
        return $g;
    }

    public function playAlone($gameUuid)
    {
        $g = $this->buildExistingGameAggregate($gameUuid, null);
        if($g->areSeatsFilled()) {
            throw new Exception('Seats already taken');
        }
        $g->playAlone();
        $this->gameRepository->persistGameAggregate($g);
        if($g->areSeatsFilled()) {
            PlayerJoined::dispatch($g->getUuid(), 'ALL_JOINED');
        }
        return $g;
    }

    public function buildGameHandlerForUuid($uuid){
        return $this->dealerService->getDealerForUuid($uuid);
    }

    /**
     * @param $gameType
     * @return GameAggregate
     */
    protected function buildNewGameAggregate($gameType): GameAggregate
    {
        return GameAggregate::newGame($gameType);
    }

    /**
     * @param $gameType
     * @return GameAggregate
     */
    protected function buildExistingGameAggregate($gameUuid, $playerUuid): GameAggregate
    {
        $game = Game::firstWhere(['uuid' =>$gameUuid]);
        return new GameAggregate(
            $game->uuid,
            $game->game_type,
            $game->players,
            $game->invitation,
            $playerUuid);
    }
}
