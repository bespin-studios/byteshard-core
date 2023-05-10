<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Form\Control;

use byteShard\Form\Event\OnButtonClick;
use byteShard\Internal\Action;
use byteShard\Internal\Form\ButtonInterface;
use byteShard\Internal\Form\CollectionInterface;
use byteShard\Internal\Form\FormObject;

class ButtonGroup extends FormObject implements CollectionInterface
{
    protected Block $container;
    protected ButtonInterface $cancelButton;
    protected ButtonInterface $approveButton;

    public function __construct(string $id = '', ?Action $cancelAction = null, Action ...$approveActions)
    {
        parent::__construct($id);
        $this->container     = new Block();
        $this->cancelButton  = new Button(implode('.', array_filter([$id, 'cancel'])));
        $this->approveButton = new Button(implode('.', array_filter([$id, 'approve'])));

        if ($cancelAction) {
            $cancelEvent = new OnButtonClick();
            $cancelEvent->addActions($cancelAction);
            $this->cancelButton->addEvents($cancelEvent);
        }

        $approveEvent = new OnButtonClick();
        if ($approveActions) {
            $approveEvent->addActions(...$approveActions);
        }
        $this->approveButton->addEvents($approveEvent);
    }

    /**
     * @return Block[]|FormObject[]
     */
    public function getElements(): array
    {
        $this->container->setName($this->getName())->setOffsetLeft(0);
        $this->container->addFormObject($this->cancelButton);
        $this->container->addFormObject(new NewColumn());
        $this->container->addFormObject($this->approveButton);
        return [$this->container];
    }

    /**
     * @return $this
     */
    public function setClosePopupButton(): self
    {
        if (isset($this->cancelButton)) {
            $this->cancelButton = new ClosePopupButton();
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function setStyled(): self
    {
        if (isset($this->cancelButton)) {
            $this->cancelButton->setClassName('bs_cancel');
        }
        if (isset($this->approveButton)) {
            $this->approveButton->setClassName('bs_approve');
        }
        return $this;
    }

    /**
     * @param Action ...$actions
     * @return $this
     */
    public function setCancelActions(Action ...$actions): self
    {
        $this->cancelButton->addEvents($event = new OnButtonClick());
        $event->addActions(...$actions);
        return $this;
    }

    /**
     * @param Action ...$actions
     * @return $this
     */
    public function setApproveActions(Action ...$actions): self
    {
        $this->approveButton->addEvents($event = new OnButtonClick());
        $event->addActions(...$actions);
        return $this;
    }

    /**
     * @return Block
     */
    public function getContainer(): Block
    {
        return $this->container;
    }

    /**
     * @param Block $container
     * @return ButtonGroup
     */
    public function setContainer(Block $container): self
    {
        $this->container = $container;
        return $this;
    }

    /**
     * @return ButtonInterface
     */
    public function getCancelButton(): ButtonInterface
    {
        return $this->cancelButton;
    }

    /**
     * @param ButtonInterface $cancelButton
     * @return ButtonGroup
     */
    public function setCancelButton(ButtonInterface $cancelButton): self
    {
        $this->cancelButton = $cancelButton;
        return $this;
    }

    /**
     * @return ButtonInterface
     */
    public function getApproveButton(): ButtonInterface
    {
        return $this->approveButton;
    }

    /**
     * @param ButtonInterface $approveButton
     * @return ButtonGroup
     */
    public function setApproveButton(ButtonInterface $approveButton): self
    {
        $this->approveButton = $approveButton;
        return $this;
    }
}
