<?php

namespace byteShard\Internal\Traits;

use byteShard\Cell;
use byteShard\Internal\ContentClassFactory;
use byteShard\Internal\Ribbon\RibbonClassInterface;
use byteShard\Internal\Ribbon\RibbonObjectInterface;
use byteShard\Internal\Struct\UiComponentInterface;
use byteShard\Ribbon\RibbonInterface;

trait Ribbon
{
    private RibbonClassInterface $ribbon;

    private function getRibbonComponent(Cell $cell): ?UiComponentInterface
    {
        if ($this instanceof RibbonInterface) {
            $this->ribbon = ContentClassFactory::getRibbon($cell);
            $this->defineRibbonContent();
            return $this->ribbon->getComponent();
        }
        return null;
    }

    protected function addRibbonObject(RibbonObjectInterface ...$ribbonObject): void
    {
        if (isset($this->ribbon)) {
            $this->ribbon->addRibbonObject(...$ribbonObject);
        }
    }

    protected function setRibbonAccessType(int $accessType): self
    {
        if (isset($this->ribbon)) {
            $this->ribbon->setAccessType($accessType);
        }
        return $this;
    }
}