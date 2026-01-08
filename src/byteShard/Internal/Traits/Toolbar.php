<?php

namespace byteShard\Internal\Traits;

use byteShard\Internal\ContentClassFactory;
use byteShard\Internal\Struct\UiComponentInterface;
use byteShard\Internal\Toolbar\ToolbarClassInterface;
use byteShard\Internal\Toolbar\ToolbarContainer;
use byteShard\Toolbar\ToolbarInterface;
use byteShard\Toolbar\ToolbarObjectInterface;

trait Toolbar
{
    private ToolbarClassInterface $toolbar;

    private function getToolbarComponent(): ?UiComponentInterface
    {
        if ($this instanceof ToolbarInterface && $this instanceof ToolbarContainer) {
            $this->toolbar = ContentClassFactory::getToolbar($this);
            $this->defineToolbarContent();
            return $this->toolbar->getComponent();
        }
        return null;
    }

    protected function addToolbarObject(ToolbarObjectInterface ...$toolbar_objects): void
    {
        if (isset($this->toolbar)) {
            $this->toolbar->addToolbarObject(...$toolbar_objects);
        }
    }

    protected function setToolbarAccessType(int $accessType): self
    {
        if (isset($this->toolbar)) {
            if (method_exists($this->toolbar, 'setAccessType')) {
                $this->toolbar->setAccessType($accessType);
            }
        }
        return $this;
    }
}