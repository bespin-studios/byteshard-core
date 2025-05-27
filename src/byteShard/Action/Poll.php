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
 * Class Poll
 * @package byteShard\Action
 */
class Poll extends Action
{

    private array $cells = [];
    private string $id;

    public function __construct(string $id, string ...$cells)
    {
        parent::__construct();
        $this->id = $id;
        foreach ($cells as $cell) {
            $cellName = Cell::getContentCellName($cell);
            if ($cellName !== '' && !in_array($cellName, $this->cells)) {
                $this->cells[] = $cellName;
            }
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $container       = $this->getLegacyContainer();
        $id              = $this->getLegacyId();
        $result['state'] = HttpResponseState::SUCCESS->value;
        $cells           = $this->getCells($this->cells);
        $mergeArray      = [];
        foreach ($cells as $cell) {
            $actions = $cell->getContentActions('onPoll');
            if (!empty($actions)) {
                foreach ($actions as $action) {
                    if (($action instanceof PollMethod) && $action->getId() === $this->id) {
                        $mergeArray[] = $action->getResult($container, $id);
                    }
                }
            }
        }
        $result = array_merge_recursive($result, ...$mergeArray);
        return new Action\ActionResultMigrationHelper($result);
    }
}
