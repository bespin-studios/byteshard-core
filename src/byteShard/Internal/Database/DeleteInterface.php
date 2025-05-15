<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database;

interface DeleteInterface
{
    /**
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return int
     */
    public static function delete(string $query, array $parameters = [], ?BaseConnection $connection = null): int;
}
