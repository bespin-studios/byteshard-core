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
interface GetIndexArrayInterface
{
    /**
     * if at least one record is found getArray will return an array of objects like
     * <br>$result[columnValue1]->columnA
     * <br>$result[columnValue1]->columnB
     * <br>$result[columnValue2]->columnA
     * <br>$result[columnValue2]->columnB
     *
     * @param string $query
     * @param string $indexColumn
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @param string|null $classMap
     * @param bool $fetchPropsLate
     * @return array
     */
    public static function getIndexArray(string $query, string $indexColumn, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): array;
}
