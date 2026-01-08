<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Enum\HttpResponseState;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;

/**
 * Class OpenTab
 * @package byteShard\Action
 */
class OpenTab extends Action
{
    private string  $className;
    private ?string $parentName;

    /**
     * OpenTab constructor.
     * className must implement Tab\Open
     * @param string $className
     * @param string|null $parentName
     */
    public function __construct(string $className, ?string $parentName = null)
    {
        $this->className  = $className;
        $this->parentName = $parentName;
    }

    protected function runAction(): ActionResultInterface
    {
        //$id              = $this->getLegacyId();
        $action['state'] = HttpResponseState::SUCCESS->value;
        //TODO: use getItemConfig of TabNew to get tab properties.
        //if parent name is empty, top level tabbar is the target, otherwise check if parentName instanceof SideBarCell or TabNew and generate parentId from there
        $action[Action\ActionTargetEnum::TabBar->value]['addTab'][] = [
            'selected' => true,
            'parentID' => ''
        ];
        /*if (class_exists($this->className) && is_subclass_of($this->className, Tab\Open::class)) {
            $tab = new $this->className($id);
            if (($_SESSION[MAIN] instanceof Session) && ($tab instanceof Tab\Open) && $tab->isValid()) {
                $tabId = \byteShard\Session::getIdByName($tab->getID());
                if (is_array($tabId) && isset($tabId[0])) {
                    $action['state']                        = HttpResponseState::SUCCESS->value;
                    $action['tabBar']['selectTab'][0]['ID'] = $tabId[0];
                } else {
                    $parentId = null;
                    if ($this->parentName === null) {
                        $parentTab = $_SESSION[MAIN];
                        //TODO open tab on main tab bar
                    } else {
                        $parentId  = \byteShard\Session::getIdByName($this->parentName);
                        $parentTab = $_SESSION[MAIN]->getTab($parentId);
                    }
                    $tabData             = $tab->getResult($parentTab);
                    $tabData['selected'] = true;
                    if ($parentId !== null) {
                        $tabData['parentID'] = $parentId[0];
                    }
                    $action['tabBar']['addTab'][] = $tabData;
                }
            }
        }*/
        return new Action\ActionResultMigrationHelper($action);
    }
}
