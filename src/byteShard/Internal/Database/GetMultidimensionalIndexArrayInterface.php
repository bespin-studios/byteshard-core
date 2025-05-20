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
interface GetMultidimensionalIndexArrayInterface
{
    /**
     * if at least one record is found getArray will return an array of objects like
     * <br>$result[columnValue1][columnValue2]->columnA
     * <br>$result[columnValue1][columnValue2]->columnB
     *
     * @param string $query
     * @param array $indexColumns
     * @param array $parameters
     * @param BaseConnection|null $connection
     * @return array
     */
    public static function getMultidimensionalIndexArray(string $query, array $indexColumns, array $parameters = [], ?BaseConnection $connection = null): array;
}
