<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Action\CellActionResult;

/**
 * Class HideLoader
 * @package byteShard\Action
 */
class HideLoader extends Action
{
    private array $id    = [];
    private array $name  = [];
    private array $cells;

    /**
     * HideLoader constructor.
     * @param string ...$cells
     */
    public function __construct(string ...$cells)
    {
        parent::__construct();
        $this->cells = array_map(function ($cell) {
            return Cell::getContentCellName($cell);
        }, array_unique($cells));
        $this->addUniqueID($this->id, $this->name);
    }

    public function hide(): array
    {
        if (empty($this->id) && empty($this->name)) {
            $_SESSION['loaderState']['global']['state'] = HttpResponseState::SUCCESS->value;
        }
        return ['state' => HttpResponseState::SUCCESS->value];
    }

    protected function runAction(): ActionResultInterface
    {
        $result = new CellActionResult('layout');
        return $result->addCellCommand($this->cells, 'hideLoader', true);
    }
}
