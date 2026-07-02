<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Form\Control;

use byteShard\ID\ContainerIDElement;
use byteShard\ID\ID;
use byteShard\ID\PopupIDElement;
use byteShard\ID\TabIDElement;
use byteShard\Internal\Form;

/**
 * Class Container
 * @package byteShard\Form\Control
 */
class Container extends Form\FormObject implements Form\InputWidthInterface
{
    protected string             $type                   = 'container';
    protected string             $displayedTextAttribute = 'label';
    private \byteShard\Container $component;
    use Form\ClassName;
    use Form\Hidden;
    use Form\Info;
    use Form\InputHeight;
    use Form\InputWidth;
    use Form\InputLeft;
    use Form\InputTop;
    use Form\Label;
    use Form\LabelLeft;
    use Form\LabelTop;
    use Form\LabelWidth;
    use Form\Name;
    use Form\Note;
    use Form\OffsetLeft;
    use Form\OffsetTop;
    use Form\Position;
    use Form\Required;
    use Form\Style;
    use Form\Tooltip;
    use Form\Userdata;

    public function attachComponent(\byteShard\Container $container): static
    {
        $id = $this->cell->getNewId();
        if ($id->isPopupId()) {
            $elements[] = new PopupIDElement($id->getPopupId());
        }
        if ($id->isTabId()) {
            $elements[] = new TabIDElement($id->getTabId());
        }
        $elements[] = new ContainerIDElement($container::class);
        $xid = ID::factory(...$elements);
        $this->setUserdata(['xid' => $xid->getEncryptedId()]);
        return $this;
    }
}
