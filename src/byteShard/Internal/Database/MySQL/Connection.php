<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\MySQL;

use byteShard\Database\Enum\ConnectionType;
use byteShard\Internal\Database\BaseConnection;
use byteShard\Internal\Database\ParametersInterface;

/**
 * Class mssql_connection
 *
 * This class is only used as an additional layer to easily identify the database type
 * since every different mysql implementation has to be a subclass of this
 * you can always check is_subclass_of
 */
abstract class Connection extends BaseConnection
{
    protected string        $escapeStart       = '`';
    protected string        $escapeEnd         = '`';
    protected static string $escapeStaticStart = '`';
    protected static string $escapeStaticEnd   = '`';

    public function __construct(ConnectionType $type = ConnectionType::READ, ?ParametersInterface $connectionParameters = null)
    {
        parent::__construct($type, $connectionParameters);
    }
}
