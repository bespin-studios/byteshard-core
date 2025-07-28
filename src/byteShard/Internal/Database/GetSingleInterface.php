<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database;

interface GetSingleInterface
{
    /**
     * if exactly one record is found, getSingle will return an object like:
     * <br>$result->dbfield1
     * <br>$result->dbfield2
     * @param string $query The SQL query to be executed
     * @param array $parameters array of a key-value pair where column name is key and column value is value
     * @param BaseConnection|null $connection an existing connection can be passed to be reused for the query
     * @return object|null if no record is found for the query, null is returned
     * @api
     */
    public static function getSingle(string $query, array $parameters = [], ?BaseConnection $connection = null, ?string $classMap = null, bool $fetchPropsLate = false): ?object;
}