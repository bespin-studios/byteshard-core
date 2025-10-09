<?php

namespace byteShard\Internal\Ribbon;

use byteShard\Enum\Event;
use byteShard\Internal\Permission\PermissionImplementation;
use byteShard\Locale;
use byteShard\Session;
use byteShard\Utils\Strings;

class RibbonControl implements RibbonObjectInterface
{
    use PermissionImplementation;

    /**
     * @var array<Event>
     */
    private array $events = [];
    /**
     * @var array<RibbonObjectInterface>
     */
    private array   $nested     = [];
    protected array $attributes = [];
    private string  $encryptedId = '';
    private string  $baseLocale = '';

    public function __construct(private readonly string $id)
    {

    }

    public function generateEncryptedId(string $nonce): void
    {
        $name        = $this->getObjectName();
        $objectNonce = substr(md5($nonce.$name), 0, 24);
        $objectClass = $this::class;
        if (str_starts_with($objectClass, 'byteShard\\Ribbon\\Control\\')) {
            $objectClass = '!r'.substr($objectClass, 25);
        }
        $encrypted         = [
            'i' => $name,
            'a' => $this->getAccessType(),
            't' => $objectClass
        ];
        $this->encryptedId = Session::encrypt(json_encode($encrypted), $objectNonce);
    }

    protected function addEvents(Event ...$events): self
    {
        foreach ($events as $event) {
            if (!in_array($event, $this->events)) {
                $this->events[] = $event;
            }
        }
        return $this;
    }

    public function setBaseLocale(string $baseLocale): void
    {
        $this->baseLocale = $baseLocale;
    }

    public function getObjectName(): string
    {
        return $this->id;
    }

    public function getContents(): array
    {
        $contents       = $this->attributes;
        $contents['id'] = $this->encryptedId;
        if (array_key_exists('label', $contents)) {
            $contents['text'] = $contents['label'];
            unset($contents['label']);
        } else {
            $contents['text'] = Strings::purify(Locale::get($this->baseLocale.'.Ribbon.'.$this->id.'.Label'));
        }
        if (array_key_exists('img', $contents)) {
            $contents['img'] = 'app/img/'.$contents['img'];
        } else {
            $contents['img'] = 'app/img/'.$this->getObjectName().'.svg';
        }
        if (array_key_exists('imgdis', $contents)) {
            $contents['imgdis'] = 'app/img/'.$contents['imgdis'];
        } else {
            $contents['imgdis'] = $contents['img'];
        }
        return $contents;
    }

    public function getNestedItems(): array
    {
        return $this->nested;
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}