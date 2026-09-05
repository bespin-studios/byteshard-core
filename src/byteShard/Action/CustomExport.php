<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Enum\Export\ExportType;
use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Session;

/**
 * Class CustomExport
 * @package byteShard\Action
 */
class CustomExport extends Action\ExportAction implements Action\ExportInterface
{
    public function __construct(ExportType $type, private string $context = '')
    {
        parent::__construct($type, 600);
    }

    protected function runAction(): ActionResultInterface
    {
        $xid = $this->getXID();
        if ($xid !== null) {
            $context = $this->context !== '' ? Session::encrypt($this->context) : '';
            $action[Action\ActionTargetEnum::Global->value]['export'] = [
                'xid'  => $xid,
                'id'   => $this->getEventId(),
                'type' => $this->getType()->value,
                'ctx'  => $context,
                'cd'   => null,
                'gd'   => null
            ];
            $this->resetEventId();
        }
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }
}
