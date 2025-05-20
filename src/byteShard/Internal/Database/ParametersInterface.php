<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database;

use byteShard\Database\Enum\ConnectionType;
use byteShard\Database\Struct\Parameters;

/**
 * Interface Parameters
 * @package byteShard\Internal\Database
 */
interface ParametersInterface
{
    public function getDatabase(?string $name = null): string;

    public function getDbParameters(ConnectionType $type, ?string $name = null): Parameters;
}
