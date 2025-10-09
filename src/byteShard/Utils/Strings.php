<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Utils;

use byteShard\Debug;
use byteShard\Locale;
use Error;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Class Strings
 * @package byteShard\Utils
 */
class Strings
{
    private static ?HTMLPurifier $instance = null;

    /**
     * works like vsprintf but matches keys
     * args can be of type array or object
     * @param string $str
     * @param Object|array $args
     * @return string
     */
    public static function vksprintf(string $str, array|object $args): string
    {
        $formatSpecifier = 'bcdefgosuxGEFX';
        if (is_object($args)) {
            $args = get_object_vars($args);
        }
        if (is_array($args)) {
            $map    = array_flip(array_keys($args));
            $newStr = preg_replace_callback('/(^|[^%])%([a-zA-Z0-9_-]+)(\$.*?['.$formatSpecifier.'])/',
                function ($m) use ($map) {
                    /**@var array $map */
                    if (array_key_exists($m[2], $map)) {
                        return $m[1].'%'.($map[$m[2]] + 1).$m[3];
                    }
                    return $m[1].Locale::get('byteShard.utils.string.vksprintf');
                },
                $str);
            $newStr = preg_replace_callback('/(^|[^%])%([0-9]+)(?![0-9$'.$formatSpecifier.'])/', function ($m) {
                return $m[1].'%%'.$m[2];
            }, $newStr);
            try {
                return vsprintf($newStr, $args);
            } catch (Error $e) {
                Debug::error('Error using vsprintf on $newStr: '.var_export($newStr, true).', $args: '.var_export($args, true).' Error: '.$e->getMessage());
            }
        }
        return $str;
    }

    /**
     * @param string $string
     * @param array $array
     * @return string
     */
    public static function replace(string $string, array $array): string
    {
        $map = array_flip(array_keys($array));
        return vsprintf(
            preg_replace_callback(
                '/(^|[^%])%([a-zA-Z0-9_-]+)(\$.*?[bcdefgosuxGEFX])/',
                function ($m) use ($map) {
                    if (array_key_exists($m[2], $map)) {
                        return $m[1].'%'.($map[$m[2]] + 1).$m[3];
                    }
                    return $m[1].Locale::get('byteShard.utils.string.vksprintf');
                },
                $string
            ),
            $array
        );
    }

    /**
     * @param string $string
     * @param object $object
     * @return string
     */
    public static function oreplace(string $string, object $object): string
    {
        return self::replace($string, get_object_vars($object));
    }

    public static function escape(string $string, string $tagWhitelist = 'pre|b|img|em|u|ul|li|ol|br|div|h1|h2|h3|h4|i|strike|sub|sup|blockquote|p'): string
    {
        return preg_replace('#&lt;(/?(?:'.$tagWhitelist.')(?:.*?)?)&gt;#', '<\1>', htmlspecialchars($string, ENT_QUOTES, 'UTF-8'));
    }

    public static function purify(string $string, bool $htmlSpecialChars = false, ?HTMLPurifier $purifier = null): string
    {
        if (strpbrk($string, '<>&\'"()') === false) {
            return $string;
        }

        if ($purifier === null) {
            if (self::$instance === null) {
                $config = HTMLPurifier_Config::createDefault();
                $config->set('Attr.AllowedFrameTargets', ['_blank']);
                $config->set('Cache.SerializerPath', sys_get_temp_dir());
                $config->set('URI.AllowedSchemes', [
                    'http'   => true,
                    'https'  => true,
                    'mailto' => true,
                    'ftp'    => true,
                    'nntp'   => true,
                    'news'   => true,
                    'tel'    => true,
                    'sip'    => true
                ]);
                self::$instance = new HTMLPurifier($config);
            }
            $purifier = self::$instance;
        }
        if ($htmlSpecialChars === true) {
            return htmlspecialchars($purifier->purify($string), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        }
        return $purifier->purify($string);
    }
}
