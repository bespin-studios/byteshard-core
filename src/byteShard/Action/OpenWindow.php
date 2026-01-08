<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\Enum\LinkTarget;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;

/**
 * Class OpenWindow
 * @package byteShard\Action
 */
class OpenWindow extends Action
{
    /**
     * @var string equivalent to the action attribute of a html form
     */
    private string $action;

    /**
     * @var LinkTarget equivalent to the target attribute of a html form
     */
    private LinkTarget $target;

    /**
     * @var string equivalent to the method attribute of a html form
     */
    private string $method;

    /**
     * @var array parameters which are passed as hidden fields in the html form
     */
    private array $data = [];

    /**
     * OpenWindow constructor.
     * @param string $action
     * @param string|LinkTarget $target
     * @param string $method
     */
    public function __construct(string $action, string|LinkTarget $target = LinkTarget::BLANK, string $method = 'post')
    {
        if (is_string($target)) {
            trigger_error('Using a string target in OpenWindow Action is deprecated. Use '.LinkTarget::class.' instead');
            $target = LinkTarget::tryFrom($target);
            if ($target === null) {
                $target = LinkTarget::BLANK;
            }
        }
        $this->action = $action;
        $this->target = $target;
        $this->method = $method;
    }

    /**
     * @param array $array
     * @return $this
     */
    public function setData(array $array = []): self
    {
        $this->data = $array;
        return $this;
    }

    protected function runAction(): ActionResultInterface
    {
        $action               = new Action\CellActionResult(Action\ActionTargetEnum::Window);
        $cells                = parent::getUniqueCellNameArray($this->getActionInitDTO()->cell);
        $parameters['target'] = $this->target->value;
        $parameters['action'] = $this->action;
        $parameters['method'] = $this->method;
        foreach ($this->data as $key => $val) {
            $parameters[$key] = $val;
        }
        $action->addCellCommand($cells, 'openWindow', $parameters);
        return $action;
    }
}
