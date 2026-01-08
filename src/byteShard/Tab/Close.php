<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Tab;

use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Database;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\ContainerInterface;
use byteShard\Internal\Session;
use byteShard\Internal\Struct;
use byteShard\ID;

/**
 * Class Close
 * @package byteShard\Tab
 */
class Close extends Action
{
    private array    $result = [];
    protected object $id;
    protected string $field;
    protected string $table;

    public function __construct(array|Struct\ID|null|string $id)
    {
        if (!$id instanceof Struct\ID) {
            $this->id = ID::explode($id);
        } else {
            $this->id = $id;
        }
        $this->result['state'] = HttpResponseState::SUCCESS->value;
    }

    public function getResult(): array
    {
        if (isset($this->field, $this->table, $this->id->{$this->field})) {
            if ($_SESSION[MAIN] instanceof Session) {
                $rs = Database::getRecordset($cn = Database::getConnection(Database\Enum\ConnectionType::WRITE));
                $rs->open("SELECT Active FROM ".$this->table." WHERE ".$this->field."=".$this->id->{$this->field}." AND User_ID='".$_SESSION[MAIN]->getUserID()."'");
                if ($rs->recordcount() === 1) {
                    $rs->fields['Active'] = 0;
                    $rs->update();
                }
                $rs->close();
                $cn->disconnect();
            }
        } else {
            $this->result['state'] = HttpResponseState::ERROR->value;
        }
        return $this->result;
    }

    protected function runAction(): ActionResultInterface
    {
        // TODO: Implement runAction() method.
        return new Action\ActionResult();
    }
}
