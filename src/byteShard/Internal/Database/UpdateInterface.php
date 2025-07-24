<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database;

interface UpdateInterface
{
    /**
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return int|array
     */
    public static function update(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): int|array;
}
