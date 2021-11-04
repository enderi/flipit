<?php

namespace App\Services;

use App\Models\GamePlayerMapping;

class GameMappingService {
    public function getMappingForUuid($uuid): GamePlayerMapping {
        return GamePlayerMapping::firstWhere('uuid', $uuid);
    }

}
