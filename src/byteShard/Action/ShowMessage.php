<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Locale;
use byteShard\Popup\Enum\Message\Type;
use byteShard\Popup\Message;

/**
 * Class ShowMessage
 * @package byteShard\Action
 */
class ShowMessage extends Action
{
    const POPUP         = 'popup';
    const POPUP_ERROR   = 'popupError';
    const POPUP_WARNING = 'popupWarning';
    const POPUP_NOTICE  = 'popupNotice';
    const POPIN         = 'popin';

    private string $message;
    private int    $duration;
    private string $type;

    /**
     * ShowMessage constructor.
     * @param string $message
     * @param int $duration milliseconds
     * @param string $type
     * @param bool $token
     */
    public function __construct(string $message, int $duration = 1000, string $type = self::POPIN, bool $token = false)
    {
        $this->message  = $message;
        $this->duration = $duration;
        $this->type     = $type;
        if ($token === true) {
            $this->message = Locale::get($message);
        }
    }

    protected function runAction(): ActionResultInterface
    {
        if ($this->type === self::POPIN) {
            return new Action\ActionResultMigrationHelper([Action\ActionTargetEnum::Message->value => [
                'duration' => $this->duration,
                'text'     => $this->message
            ]]);
        }
        $type = match ($this->type) {
            self::POPUP_WARNING => Type::WARNING,
            self::POPUP_ERROR   => Type::ERROR,
            default             => Type::NOTICE,
        };
        return new Action\ActionResultMigrationHelper(Message::getClientResponse($this->message, $type, false));
    }
}
