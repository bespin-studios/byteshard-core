<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Enum\Access;
use byteShard\Event\OnPopupCloseInterface;
use byteShard\Internal\ClientData\EventContainerInterface;
use byteShard\Internal\Event\Event;
use byteShard\Internal\Layout;
use byteShard\Internal\LayoutContainer;
use byteShard\Internal\PopupInterface;
use byteShard\Internal\Struct\ClientData;
use byteShard\Internal\Struct\GetData;
use Closure;

/**
 * Class Popup
 * @package byteShard
 */
class Popup extends LayoutContainer implements PopupInterface, EventContainerInterface
{
    private bool          $eventOnPopupClose      = false;
    private array         $popup                  = [];
    private array         $conditionArgs          = [];
    private string        $conditionFailedMessage = '';
    private int           $conditionFailedHeight;
    private int           $conditionFailedWidth;
    private Closure       $conditionCallback;
    protected ?ClientData $clientData;
    private ?GetData      $getData;

    private ?\byteShard\Layout $content = null;

    /**
     * Popup constructor.
     * @param string $id
     * @throws Exception
     */
    public function __construct(string $id = '')
    {
        $this->popup['height'] = 300;
        $this->popup['width']  = 550;
        if ($id === '') {
            $id = get_class($this);
            if ($id === self::class) {
                throw new Exception('Using new Popup is deprecated. Please create a class which inherits from Popup under App\Popup\ namespace', 14560001);
            }
        }
        $id = '\\'.trim($id, '\\');
        if (str_starts_with(strtolower($id), '\\app\\cell')) {
            $id = substr($id, 9);
        }
        parent::__construct($id);
    }

    public function setProcessedClientData(?ClientData $clientData): void
    {
        if ($clientData !== null) {
            $this->clientData = $clientData;
        }
    }

    public function setProcessedGetCellDataResponse(?GetData $getData): void
    {
        if ($getData !== null) {
            $this->getData = $getData;
        }
    }

    public function getClientData(): ?ClientData
    {
        return $this->clientData ?? null;
    }

    public function setContent(\byteShard\Layout $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): ?\byteShard\Layout
    {
        return $this->content;
    }

    public function definePopup(): void
    {
        // implement in app
    }

    public function getName(): string
    {
        return $this->getNewId()->getPopupId();
    }

    public function getNonce(): string
    {
        // TODO: Implement getNonce() method.
        return '';
    }

    public function addCell(Cell ...$cells): LayoutContainer
    {
        if (get_class($this) === Popup::class) {
            return parent::addCell(...$cells);
        }
        $namespace               = $this->meta['namespace'];
        $this->meta['namespace'] = $this->getNewId()->getPopupId();
        $result                  = parent::addCell(...$cells);
        $this->meta['namespace'] = $namespace;
        return $result;
    }

    /**
     * @return string
     * @deprecated use getScopeLocaleToken() instead
     */
    public function getBaseLocale(): string
    {
        trigger_error(__METHOD__.': is deprecated. Use getScopeLocaleToken instead', E_USER_DEPRECATED);
        return $this->getScopeLocaleToken();
    }

    /**
     * @return string
     */
    public function getScopeLocaleToken(): string
    {
        return str_replace('\\', '_', trim($this->layout->getName(), '\\')).'::Popup.';
    }

    /**
     * @param Event ...$events
     * @return static
     */
    public function addEvents(Event ...$events): static
    {
        foreach ($events as $event) {
            if ($event instanceof Popup\Event\OnClose) {
                $name = $event->getContentEventName();
                if (!isset($this->event['content'][$name])) {
                    $this->event['content'][$name] = $event;
                }
                if ($this->eventOnPopupClose === false) {
                    $this->eventOnPopupClose = true;
                }
            }
        }
        return $this;
    }

    public function getActionId(): string
    {
        //TODO:
        return '';
    }

    /**
     * this looks like the only part that is actually stored in the current session implementation (meta/layout)
     * and it depends on the tab where the popup is initialized
     * since the tabId can be decrypted by the client data, we should be able to have consistent Ids
     * see if this information can be evaluated during runtime, if yes, we don't have to store the popup in the session at all
     * only the cells
     * -> this method is deprecated and only called on popups which are opened on the tab toolbar
     * @param Tab $tab
     * @return string
     * @throws Exception
     */
    public function generateID(Tab $tab): string
    {
        $this->meta['ID'] = $tab->getIDForPopup($this->meta['name']);
        if ($this->layout instanceof Layout) {
            $this->layout->setID($this->meta['ID']);
        }
        return $this->meta['ID'];
    }

    /**
     * @param bool $bool
     * @return $this
     * @API
     */
    public function setModal(bool $bool = true): self
    {
        $this->popup['modal'] = $bool;
        return $this;
    }

    /**
     * Default height: 300
     * @param int $height
     * @return $this
     * @API
     */
    public function setHeight(int $height): self
    {
        $this->popup['height'] = $height;
        return $this;
    }

    /**
     * Default width: 550
     * @param int $width
     * @return $this
     */
    public function setWidth(int $width): self
    {
        $this->popup['width'] = $width;
        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getNavigationArray(): array
    {
        $id                    = $this->getNewId()->getEncryptedContainerId();
        $result['setup']['id'] = $id;
        if (isset($this->popup['height'])) {
            $result['setup']['height'] = $this->popup['height'];
        }
        if (isset($this->popup['width'])) {
            $result['setup']['width'] = $this->popup['width'];
        }
        if (isset($this->popup['modal']) && $this->popup['modal'] === true) {
            $result['setup']['modal'] = true;
        }
        if ($this instanceof OnPopupCloseInterface || $this->eventOnPopupClose === true) {
            $result['setup']['closeEvent'] = true;
        }
        if ($this->content instanceof \byteShard\Layout) {
            $result['content'][] = $this->content->getItemConfig(Access::from($this->getAccessType()));
            $result['type']      = 'ByteShardPopup';
        } else if ($this->layout instanceof Layout) {
            $result['content'][] = $this->layout->getItemConfig();
            $result['type']      = 'ByteShardPopup';
        } else {
            $e = new Exception(__METHOD__.': no Layout attached to popup');
            $e->setLocaleToken('byteShard.popup.getNavigationArray.no_layout');
            throw $e;
        }
        return [$id => ['open' => $result]];
    }

    public function conditionsMet(): array
    {
        $conditionsMet = ['state' => true];
        if (isset($this->conditionCallback)) {
            $callbackResult = ($this->conditionCallback)(...$this->conditionArgs);
            if ($callbackResult === false) {
                if ($this->conditionFailedMessage === '') {
                    $failedMessage = Locale::get($this->getScopeLocaleToken().'Condition.Failed');
                } elseif (str_contains($this->conditionFailedMessage, '::') && !str_contains($this->conditionFailedMessage, ' ')) {
                    $failedMessage = Locale::get($this->conditionFailedMessage);
                } else {
                    $failedMessage = $this->conditionFailedMessage;
                }
                $conditionsMet['state']  = false;
                $conditionsMet['text']   = $failedMessage;
                $conditionsMet['height'] = $this->conditionFailedHeight ?? 200;
                $conditionsMet['width']  = $this->conditionFailedWidth ?? 400;
                return $conditionsMet;
            }
        }
        return $conditionsMet;
    }

    /**
     * The Closure has to return bool
     * @API
     */
    public function condition(Closure $callable, string $conditionFailedMessage = '', ...$args): self
    {
        $this->conditionCallback      = $callable;
        $this->conditionArgs          = $args;
        $this->conditionFailedMessage = $conditionFailedMessage;
        return $this;
    }

    /**
     * @return $this
     */
    public function resetCondition(): self
    {
        unset($this->conditionCallback);
        unset($this->conditionArgs);
        unset($this->conditionFailedMessage);
        return $this;
    }

    /**
     * @API
     */
    public function setConditionFailedHeight(int $height): void
    {
        $this->conditionFailedHeight = $height;
    }

    /**
     * @API
     */
    public function setConditionFailedWidth(int $width): void
    {
        $this->conditionFailedWidth = $width;
    }
}
