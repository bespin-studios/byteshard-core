<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Cell;

use byteShard\Cell;
use byteShard\Enum\AccessType;
use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Session;

/**
 * Class ContinuePolling
 * @package byteShard\Action
 */
class ContinuePolling extends Action
{
    /**
     * part of action uid
     * @var array
     */
    private array $cells;
    private int   $time;

    /**
     * CollapseCell constructor.
     * @param string ...$cells
     */
    public function __construct(int $time, string ...$cells)
    {
        parent::__construct();
        $this->cells = array_map(function ($cell) {
            return Cell::getContentCellName($cell);
        }, array_unique($cells));
        $this->time  = $time;
        $this->addUniqueID($this->cells);
    }

    protected function runAction(): ActionResultInterface
    {
        $action = [];
        $cells  = $this->getCells($this->cells);
        foreach ($cells as $cell) {
            $action['layout'][$cell->containerId()][$cell->cellId()]['poll'] = ['interval' => $this->time, 'id' => self::getPollId($cell->getNonce())];
        }
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }

    public static function getPollId(string $cellNonce): string
    {
        $time      = microtime(true);
        $encrypted = [
            'i' => 'pollOn:'.(string)$time,
            'a' => AccessType::RW,
            't' => 'poll'
        ];

        // the nonce should be unique per object, but we need to be able to recreate it for object access in actions.
        // The solution is to take a part of the stored nonce and add the object name, generate a md5 from this and use the first 24 characters
        // The nonce will be unique per client rendering as the cell nonce is recycled whenever content is reloaded.
        // That way we can manipulate objects of an existing client form, but we're also in compliance with security recommendations
        $nonce = substr(md5($cellNonce.$encrypted['i']), 0, 24);
        return Session::encrypt(json_encode($encrypted), $nonce);
    }
}