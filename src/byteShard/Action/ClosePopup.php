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

/**
 * Class ClosePopup
 * @package byteShard\Action
 */
class ClosePopup extends Action
{
    private array $popups = [];

    /**
     * ClosePopup constructor.
     * @param string ...$cells
     */
    public function __construct(string ...$cells)
    {
        parent::__construct();
        foreach ($cells as $cell) {
            $cellName                = Cell::getContentCellName($cell);
            $this->popups[$cellName] = $cellName;
        }
        $this->addUniqueID($this->popups);
    }

    protected function runAction(): ActionResultInterface
    {
        if (!empty($this->popups)) {
            $cells = $this->getCells($this->popups);
        } else {
            $cells = [$this->getLegacyContainer()];
        }
        foreach ($cells as $cell) {
            if ($cell instanceof Cell) {
                $action['popup'][$cell->containerId()]['close'] = true;
            }
        }
        $action['state'] = HttpResponseState::SUCCESS->value;
        return new Action\ActionResultMigrationHelper($action);
    }
}
