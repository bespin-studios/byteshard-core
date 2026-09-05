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

class DownloadFile extends Action\ExportAction implements Action\ExportInterface
{
    /**
     * DownloadFile constructor.
     */
    public function __construct(private readonly string $context = '')
    {
        parent::__construct(ExportType::DOWNLOAD, 180);
    }

    protected function runAction(): ActionResultInterface
    {
        //$xid = $this->getXID();
        $xid = $this->getActionInitDTO()->id->getEncryptedCellId();
        if ($xid !== null) {
            $context = $this->context !== '' ? Session::encrypt($this->context) : '';
            //TODO: check string length since _GET is restricted.
            //if certain length is exceeded, dump serialized clientData in a datastore (aka db/ redis etc)
            $action[Action\ActionTargetEnum::Global->value]['export'] = [
                'xid'  => $xid,
                'id'   => $this->getEventId(),
                'cd'   => Session::encrypt(serialize($this->getClientData())),
                'gd'   => Session::encrypt(serialize($this->getGetData())),
                'type' => 'download',
                'ctx'  => $context
            ];
            $this->resetEventId();
        }
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }

}
