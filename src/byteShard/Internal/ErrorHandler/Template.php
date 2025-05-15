<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\ErrorHandler;

class Template
{
    /**
     * @param string $directory
     * @param string|null $user
     * @return void
     * @API
     */
    public static function printNoPermissionTemplate(string $directory, ?string $user = null): void
    {
        $error_message = $user !== null ? 'The User "'.$user.'"' : 'The process';
        $error_message .= ' has no permission to write to the defined log dir: '.$directory.'<br>Hint: Define "protected $log_path" in the private config.php';

        // $error_message should be set in error.php, but this seems to be legacy
        if (file_exists(BS_FILE_PRIVATE_ROOT.'/error.php')) {
            include_once BS_FILE_PRIVATE_ROOT.'/error.php'; /** @phpstan-ignore includeOnce.fileNotFound */
        }

        print self::getHtml($error_message, 'no_permission');
    }

    /**
     * @return void
     * @API
     */
    public static function printInvalidEnvironmentTemplate(): void
    {
        print self::getHtml('config::getEnvironment must return a child object of byteShard\Environment', 'invalid_environment');
    }

    /**
     * @param string $message
     * @return void
     * @API
     */
    public static function printGenericExceptionTemplate(string $message): void
    {
        print self::getHtml($message);
    }

    private static function getHead(): string
    {
        $path = defined('BS_WEB_ROOT_DIR') ? BS_WEB_ROOT_DIR.'/' : '';
        $head = [
            '<head>',
            '<meta content="text/html; charset=utf-8" http-equiv="Content-Type">',
            '<title>byteShard</title>',
            '<link href="'.$path.'bs/css/error.css" type="text/css" rel="stylesheet">',
            '</head>'
        ];
        return implode("\n", $head);
    }

    private static function getHtml(string $message, string $cssClass = 'generic_exception'): string
    {
        $html = [
            '<html lang="en">',
            self::getHead(),
            '<body>',
            '<div id="ContentFrame">',
            '<div id="MessageFrame">',
            '<div id="Gears"></div>',
            '<div class="'.$cssClass.'"><p>',
            $message,
            '</p></div>',
            '</div>',
            '</div>',
            '</body>',
            '</html>'
        ];
        return implode("\n", $html);
    }
}
