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
    private string $id;

    /**
     * DownloadFile constructor.
     * @param string $id
     */
    public function __construct(string $id)
    {
        parent::__construct(ExportType::DOWNLOAD, 180);
        $this->id = $id;
    }

    protected function runAction(): ActionResultInterface
    {
        $xid = $this->getXID();
        if ($xid !== null) {
            //TODO: check string length since _GET is restricted.
            //if certain length is exceeded, dump serialized clientData in a datastore (aka db/ redis etc)
            $action['global']['export'] = [
                'xid'  => $xid,
                'id'   => $this->getEventId(),
                'cd'   => Session::encrypt(serialize($this->getClientData())),
                'gd'   => Session::encrypt(serialize($this->getGetData())),
                'type' => 'download'
            ];
            $this->resetEventId();
        }
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }

}
