<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use Exception;

/**
 * Class Encrypt
 * @package byteShard\Internal
 */
class Encrypt
{
    /**
     * @param string|null $name
     * @param bool $addRandomBytes
     * @return string
     * @throws Exception
     */
    public static function encrypt(?string $name = null, bool $addRandomBytes = true): string
    {
        if ($name === null) {
            return md5(random_bytes(64));
        }
        if ($addRandomBytes === true) {
            return substr(md5($name), 0, 10).substr(md5(random_bytes(64)), 0, 14);
        }
        return substr(md5($name), 0, 24);
    }
}
