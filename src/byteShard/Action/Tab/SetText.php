<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Tab;

use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\ContentClassFactory;

class SetText extends Action
{
    private string $tabName;
    private string $text;

    public function __construct(string $tabName, string $text = '')
    {
        $this->tabName = $tabName;
        $this->text    = $text;
    }

    protected function runAction(): ActionResultInterface
    {
        $action['state'] = HttpResponseState::ERROR->value;
        if (class_exists($this->tabName)) {
            $tab = ContentClassFactory::tab($this->tabName);
            $action = [
                Action\ActionTargetEnum::Tab->value => [$tab->getEncryptedId() => ['setText' => $this->text]],
                'state'                             => HttpResponseState::SUCCESS->value
            ];
        }
        return new Action\ActionResultMigrationHelper($action);
    }
}