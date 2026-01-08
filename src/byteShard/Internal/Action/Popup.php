<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Action;

use byteShard\Internal\Action;
use byteShard\Popup\Enum\Message\Type;
use byteShard\Popup\Message;

abstract class Popup extends Action
{
    private string   $id;
    private string   $message;
    protected Type   $type         = Type::ERROR;
    protected string $localeSuffix = '';

    public function __construct(string $id, string $message = '')
    {
        $this->id      = $id;
        $this->message = $message;
    }

    protected function runAction(): ActionResultInterface
    {
        $container = $this->getActionInitDTO()->cell;
        if ($this->message !== '') {
            return new ActionResultMigrationHelper(Message::getClientResponse($this->message, $this->type, false));
        }
        return new ActionResultMigrationHelper(Message::getClientResponse($container->getScopeLocaleToken().'.Popup.'.$this->localeSuffix.'.'.$this->id, $this->type));
    }
}
