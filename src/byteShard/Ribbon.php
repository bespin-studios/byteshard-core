<?php

namespace byteShard;

use byteShard\Enum\AccessType;
use byteShard\Enum\ContentFormat;
use byteShard\Enum\ContentType;
use byteShard\Enum\Event;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Internal\Ribbon\RibbonClassInterface;
use byteShard\Internal\Ribbon\RibbonObjectInterface;
use byteShard\Internal\SimpleXML;
use byteShard\Internal\Struct\ClientCellEvent;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Internal\Struct\UiComponentInterface;
use SimpleXMLElement;

class Ribbon implements RibbonClassInterface
{

    use PermissionImplementation;

    /**
     * @var array<RibbonObjectInterface>
     */
    private array $ribbonObjects = [];
    /**
     * @var array<string, array<string>>
     */
    private array  $events        = [];
    private string $outputCharset = 'utf-8';

    public function __construct(private readonly Cell $cell)
    {
        $this->setParentAccessType($cell->getAccessType());
    }

    public function addRibbonObject(RibbonObjectInterface ...$ribbonObjects): RibbonClassInterface
    {
        foreach ($ribbonObjects as $ribbonObject) {
            $this->ribbonObjects[] = $ribbonObject;
        }
        return $this;
    }


    public function getComponent(): ?UiComponentInterface
    {
        if ($this->getAccessType() > AccessType::NONE && !empty($this->ribbonObjects)) {
            foreach ($this->ribbonObjects as $object) {
                $this->evaluateRibbonObject($object);
            }
            return new ContentComponent(
                type   : ContentType::DhtmlxRibbon,
                content: $this->getXML(),
                events : $this->getEvents(),
                setup  : [],
                update : [],
                format : ContentFormat::XML
            );
        }
        return null;
    }

    private function getEvents(): array
    {
        $events = [];
        foreach ($this->events as $event => $handlers) {
            $uniqueHandlers = array_unique($handlers);
            foreach ($uniqueHandlers as $handler) {
                $events[] = new ClientCellEvent($event, $handler);
            }
        }
        return $events;
    }

    private function evaluateRibbonObject(RibbonObjectInterface $object): void
    {
        $object->setParentAccessType($this->getAccessType());
        $nonce = $this->cell->getNonce();
        $object->setBaseLocale($this->cell->createLocaleBaseToken('Cell'));

        $object->generateEncryptedId($nonce);

        if ($object->getAccessType() === AccessType::READWRITE) {
            foreach ($object->getEvents() as $event) {
                switch ($event) {
                    case Event::OnClick:
                        $this->events['onClick'][] = 'doOnClick';
                        break;
                }
            }
        }
        foreach ($object->getNestedItems() as $item) {
            $this->evaluateRibbonObject($item);
        }
    }

    private function getXML(): string
    {
        SimpleXML::initializeDecode();
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="'.$this->outputCharset.'" ?><ribbon/>');

        foreach ($this->ribbonObjects as $object) {
            if ($object->getAccessType() > AccessType::NONE) {
                $object->addObjectToXml($xml);
            }
        }
        return SimpleXML::asString($xml);
    }
}