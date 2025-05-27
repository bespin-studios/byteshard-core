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
 * Class ShowLoader
 * @package byteShard\Action
 */
class ShowLoader extends Action
{
    /** @var Action[] */
    private array   $asyncNested    = [];
    private array   $id             = [];
    private array   $name           = [];
    private array   $cells          = [];
    private int     $time;
    private bool    $async          = false;
    private ?string $url            = null;
    private bool    $proxy          = true;
    private int     $asyncTimeout   = 1;
    private string  $asyncContainer = '';

    /*public function __construct($cells = null, int $timeInSeconds = 1)
    {
        parent::__construct();

        if ($cells !== null) {
            if (!is_array($cells)) {
                $cells = array($cells);
            }
            foreach ($cells as $cell) {
                if ($cell instanceof Cell) {
                    $this->id[] = ID\ID::CellIdHelper($cell->getLayoutContainerID(), $cell->getID());
                } else {
                    $this->name[] = $cell;
                }
            }
        }
        $this->time = $timeInSeconds;
    }*/

    public function __construct(int $timeInSeconds = 1)
    {
        parent::__construct();
        $this->time = $timeInSeconds;
    }

    /**
     * @param bool $bool
     * @return ShowLoader
     * @API
     */
    public function setAsync(bool $bool = true): self
    {
        $this->async = $bool;
        return $this;
    }

    /**
     * @param string $url
     * @return $this
     * @API
     */
    public function setAsyncURL(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return $this
     * @API
     */
    public function disableProxy(): self
    {
        $this->proxy = false;
        return $this;
    }

    /**
     * @param Action ...$actions
     * @return ShowLoader
     * @API
     */
    public function addAsyncAction(Action ...$actions): ShowLoader
    {
        foreach ($actions as $action) {
            if (!in_array($action, $this->asyncNested)) {
                $this->asyncNested[] = $action;
            }
        }
        return $this;
    }

    /**
     * @param string $containerName
     * @return $this
     * @API
     */
    public function setAsyncContainer(string $containerName): ShowLoader
    {
        $this->asyncContainer = $containerName;
        return $this;
    }

    protected function runAction(): ActionResultInterface
    {
        $container = $this->getLegacyContainer();
        $id        = $this->getLegacyId();
        //TODO: implement this like ConfirmAction, pack the current clientData and so on into a field, send it to the client, have the client show the loader and re-call the same bs_event stack but this time with the specially prepped client data
        // in the event handler unpack the loader-client data and overwrite the regular client data, skip this action and execute all nested
        if ($container instanceof Cell) {
            //TODO: check if __toString is an option for tabs and popup objects
            $_SESSION['loaderState'] = [
                'global' => [
                    'state'     => HttpResponseState::RUNNING->value,
                    'startTime' => time()
                ],
                'action' => [
                    'nested'       => $this->getNestedActions(),
                    'cell'         => ($this->asyncContainer === '') ? $container : $this->asyncContainer,
                    'id'           => $id,
                    'async'        => $this->async,
                    'asyncUrl'     => $this->url,
                    'asyncNested'  => $this->asyncNested,
                    'asyncProxy'   => $this->proxy,
                    'asyncTimeout' => $this->asyncTimeout
                ]
            ];
            $this->setRunNested(false);
            return new Action\ActionResultMigrationHelper(['global' => ['showLoader' => $this->time]]);
        }
        return new Action\ActionResult();
    }
}
