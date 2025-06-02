<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Session;
use byteShard\Tab;

/**
 * Class CloseTab
 * @package byteShard\Action
 */
class CloseTab extends Action
{
    private string $className;
    private int    $id;

    /**
     * CloseTab constructor.
     * className must implement Tab\Close
     * @param string $className
     * @param int $id
     */
    public function __construct(string $className, int $id)
    {
        parent::__construct();
        $this->className = $className;
        $this->id        = $id;
    }

    protected function runAction(): ActionResultInterface
    {
        $container = $this->getLegacyContainer();
        $id        = $this->getLegacyId();
        //TODO: add app namespace
        $action['state'] = HttpResponseState::SUCCESS->value;
        if (class_exists($this->className) && is_subclass_of($this->className, Tab\Close::class)) {
            print 'is subclass';
            $tab = new $this->className($this->id);
            if ($tab instanceof Tab\Close) {
                $action = array_merge_recursive($action, $tab->getResult($container, $id));
                if ($_SESSION[MAIN] instanceof Session) {
                    $_SESSION[MAIN]->removeTab($id);
                }
            }
        }
        return new Action\ActionResultMigrationHelper($action);
    }
}
