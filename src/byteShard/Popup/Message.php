<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Popup;

use byteShard\Enum\ContentFormat;
use byteShard\Enum\ContentType;
use byteShard\Enum\HttpResponseState;
use byteShard\Form\Enum\Label\Align;
use byteShard\Internal\SimpleXML;
use byteShard\Internal\Struct\ClientCell;
use byteShard\Internal\Struct\ClientCellEvent;
use byteShard\Internal\Struct\ClientCellProperties;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Layout\Enum\Pattern;
use byteShard\Locale;
use byteShard\Popup\Enum\Message\Type;
use byteShard\Utils\Strings;
use SimpleXMLElement;

/**
 * Class Message
 * @package byteShard\Popup
 */
class Message
{
    private string $headerImageUrl;
    private string $label;
    private Align  $labelAlign = Align::CENTER;
    private string $labelToken;
    private array  $messages   = [];
    private Type   $type;
    private int    $height     = 200;
    private int    $width      = 400;

    public function __construct(string $message = '', Type $type = Type::ERROR, bool $useToken = false)
    {
        if ($useToken === true) {
            $text = Locale::getArray($message);
            if ($text['found'] === true) {
                $this->messages[] = $text['locale'];
            } else {
                $this->messages[] = Strings::vksprintf(Locale::get('byteShard.popup.message.noMessageDefined'), ['token' => $message]);
            }
        } else {
            $this->messages[] = $message;
        }
        $this->type = $type;
    }

    public function setMessage(string|array $message): self
    {
        if (!is_array($message)) {
            $message = [$message];
        }
        $this->messages = $message;
        return $this;
    }

    public function setType(Type $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @param string $token
     * @return $this
     * @API
     */
    public function setLabelToken(string $token): self
    {
        $this->labelToken = $token;
        return $this;
    }

    /**
     * set the height of the message popup. Default 200
     * @param int $height
     * @return $this
     */
    public function setHeight(int $height): self
    {
        $this->height = $height;
        return $this;
    }

    /**
     * set the width of the message popup. Default 400
     * @param int $width
     * @return $this
     */
    public function setWidth(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    /**
     * @param Align $enum
     * @return $this
     * @API
     */
    public function setLabelAlign(Align $enum): self
    {
        $this->labelAlign = $enum;
        return $this;
    }

    /**
     * @param string $url
     * @return $this
     * @API
     */
    public function setHeaderImage(string $url): self
    {
        $this->headerImageUrl = $url;
        return $this;
    }

    private function addChild(?SimpleXMLElement $parent, array $item): void
    {
        if ($parent === null) {
            return;
        }
        $child = $parent->addChild('item');
        if ($child !== null) {
            foreach ($item as $name => $value) {
                if ($name === 'list') {
                    foreach ($value as $nestedItem) {
                        $this->addChild($child, $nestedItem);
                    }
                } elseif ($name === 'userdata') {
                    foreach ($value as $userdataName => $userdataValue) {
                        SimpleXML::addAttribute(SimpleXML::addChild($child, 'userdata', $userdataValue), 'name', $userdataName);
                    }
                } else {
                    SimpleXML::addAttribute($child, $name, $value);
                }
            }
        }
    }

    private function getMessageString(string $message, ContentFormat $format = ContentFormat::XML): string
    {
        if ($format === ContentFormat::JSON) {
            return str_replace('"', '&quot;', Strings::purify($message));
        }
        return Strings::purify($message, true);
    }

    public function getNavigationArray(ContentFormat $format = ContentFormat::XML): array
    {
        $className = $this->type->value.($this->labelAlign === Align::CENTER ? '_center' : '').'_label';
        $list      = [];
        foreach ($this->messages as $messageId => $message) {
            $list[] = ['type' => 'label', 'name' => 'Message'.$messageId, 'label' => $this->getMessageString($message, $format), 'className' => $className, 'position' => 'label-left'];
        }
        $items[] = ['type' => 'block', 'className' => 'bs_message_block', 'list' => $list];
        $items[] = ['type' => 'button', 'name' => 'close', 'userdata' => ['clientClose' => true], 'value' => Locale::get('byteShard.popup.message.button.ok'), 'className' => 'bs_message_button', 'offsetTop' => '10'];

        if ($format === ContentFormat::JSON) {
            $content = json_encode($items);
        } else {
            SimpleXML::initializeDecode();
            $xmlElement = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><items/>');
            foreach ($items as $item) {
                $this->addChild($xmlElement, $item);
            }
            $content = SimpleXML::asString($xmlElement);
        }
        $label = Strings::purify('<img src='.$this->getHeaderImage().' class="bs_message_header_image">'.$this->getHeaderLabel());

        $result['state'] = HttpResponseState::SUCCESS->value;
        $result['popup'] = [
            'bs_message_popup' => [
                'open' => [
                    'setup'   => [
                        'height' => $this->height,
                        'class'  => 'popup_bs_message_popup',
                        'width'  => $this->width
                    ],
                    'type'    => 'ByteShardPopup',
                    'content' => [
                        [
                            'type'    => ContentType::DhtmlxLayout,
                            'setup'   => [
                                'pattern' => Pattern::PATTERN_1C
                            ],
                            'content' => [
                                [
                                    'type'    => ContentType::DhtmlxLayoutCell,
                                    'setup'   => [
                                        'patternId' => 'a',
                                        'label'     => $label
                                    ],
                                    'content' => [new ContentComponent(
                                        type   : ContentType::DhtmlxForm,
                                        content: $content,
                                        events : [new ClientCellEvent('onButtonClick', 'doOnCloseButtonClick')],
                                        format : $format
                                    )]
                                ]
                            ]
                        ]
                    ],
                ]
            ]
        ];
        return $result;
    }

    /**
     * @param string $token
     * @param Type $type
     * @param bool $useToken
     * @param ContentFormat $contentFormat
     * @param int|null $height
     * @return array
     */
    public static function getClientResponse(string $token = '', Type $type = Type::ERROR, bool $useToken = true, ContentFormat $contentFormat = ContentFormat::XML, ?int $height = null): array
    {
        $localClass = static::class;
        if ($useToken === true) {
            $text = Locale::getArray($token);
            if ($text['found'] === true) {
                $message = new $localClass($text['locale'], $type);
            } else {
                $message = new $localClass(Strings::vksprintf(Locale::get('byteShard.popup.message.noMessageDefined'), ['token' => $token]), $type);
            }
        } else {
            $message = new $localClass($token, $type);
        }
        if ($height !== null) {
            $message->setHeight($height);
        }
        return $message->getNavigationArray($contentFormat);
    }

    /**
     * @param string $token
     * @param int|null $height
     * @param bool $useToken
     * @param string $contentType
     * @return array
     */
    public static function error(string $token = '', ?int $height = null, bool $useToken = true, string $contentType = 'XML'): array
    {
        $format     = ContentFormat::from($contentType);
        $localClass = static::class;
        if ($useToken === true) {
            $text = Locale::getArray($token);
            if ($text['found'] === true) {
                $error = $text['locale'];
            } else {
                $error = Strings::vksprintf(Locale::get('byteShard.popup.message.noMessageDefined'), ['token' => $token]);
            }
        } else {
            $error = $token;
        }
        $message = new $localClass(htmlentities($error), Type::ERROR);
        if ($height === null) {
            $error = str_replace(['<br/>', '<br />'], '<br>', $error);
            if (str_contains($error, '<br>')) {
                $lines = 0;
                $parts = explode('<br>', $error);
                foreach ($parts as $part) {
                    $length = strlen(strip_tags(html_entity_decode($part)));
                    $lines  += ($length - $length % 48) / 48;
                    if ($length % 48 > 0) {
                        $lines++;
                    }
                }
            } else {
                $length = strlen(strip_tags(html_entity_decode($error)));
                $lines  = ($length - $length % 48) / 48;
                if ($length % 48 > 0) {
                    $lines++;
                }
            }
            $message->setHeight(20 * $lines + 160);
        } else {
            $message->setHeight($height);
        }
        return $message->getNavigationArray($format);
    }

    /**
     * returns the url to the image of the popup header
     * @return string
     */
    private function getHeaderImage(): string
    {
        if (isset($this->headerImageUrl)) {
            return $this->headerImageUrl;
        }
        return match ($this->type) {
            Type::ERROR   => Locale::get('byteShard::popup.message.image.error'),
            Type::WARNING => Locale::get('byteShard.popup.message.image.warning'),
            Type::NOTICE  => Locale::get('byteShard.popup.message.image.notice')
        };
    }

    /**
     * returns the label in the following order:
     * 1) the label set through Message::setLabel
     * 2) the label set through Message::setLabelToken
     * 3) the locale depending on the message type error|warning|notice
     *
     * @return string
     */
    private function getHeaderLabel(): string
    {
        if (isset($this->label)) {
            return $this->label;
        }
        if (isset($this->labelToken)) {
            return Locale::get($this->labelToken);
        }
        return match ($this->type) {
            Type::ERROR   => Locale::get('byteShard.popup.message.error'),
            Type::WARNING => Locale::get('byteShard.popup.message.warning'),
            Type::NOTICE  => Locale::get('byteShard.popup.message.notice')
        };
    }
}
