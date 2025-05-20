<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database;

/**
 * Interface GetArrayInterface
 * @package byteShard\Internal\Database
 */
interface GetArrayInterface
{
    /**
     * if at least one record is found getArray will return an array of objects like
     * <br>$result[0]->columnA
     * <br>$result[0]->columnB
     * <br>$result[1]->columnA
     * <br>$result[1]->columnB
     *
     * @param string $query
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return array
     */
    public static function getArray(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): array;
}
