<?php

namespace byteShard\Internal\Struct;

use byteShard\Enum\ContentFormat;
use byteShard\Enum\ContentType;
use JsonSerializable;

class ContentComponent implements JsonSerializable, UiComponentInterface
{
    public readonly array $events;

    public function __construct(
        public ContentType         $type,
        public array|object|string $content,
        array                      $events = [],
        public array               $setup = [],
        public array               $update = [],
        public ContentFormat       $format = ContentFormat::XML
    )
    {
        $this->events = ClientCellEvent::getUniqueEvents(...$events);
    }

    public function jsonSerialize(): array
    {
        $result           = array_filter(get_object_vars($this), fn($value) => !empty($value));
        $result['events'] = $this->events;
        $result['setup']  = $this->setup;
        $result['update'] = $this->update;
        return $result;
    }
}