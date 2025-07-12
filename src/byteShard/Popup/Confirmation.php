<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Popup;

use byteShard\Enum\AccessType;
use byteShard\Enum\ContentType;
use byteShard\Enum\HttpResponseState;
use byteShard\Exception;
use byteShard\Form\Control\Button;
use byteShard\Form\Control\Hidden;
use byteShard\Form\Control\Input;
use byteShard\ID\IDElement;
use byteShard\Internal\ContainerInterface;
use byteShard\Internal\ContentClassFactory;
use byteShard\Internal\Form\FormObject;
use byteShard\Internal\Form\FormObject\Proxy;
use byteShard\Internal\PopupInterface;
use byteShard\Internal\Request\EventType;
use byteShard\Internal\Struct\ClientCell;
use byteShard\Internal\Struct\ClientCellEvent;
use byteShard\Internal\Struct\ClientCellProperties;
use byteShard\Internal\Struct\ClientData;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Internal\Struct\GetData;
use byteShard\Locale;
use byteShard\Session;
use byteShard\Utils\Strings;

class Confirmation implements PopupInterface
{

    const DEFAULT_HEIGHT        = 200;
    const DEFAULT_WIDTH         = 400;
    const BUTTON_ID             = '!#confirmationConfirm';
    const GET_DATA_FIELD        = '!#getData';
    const CONFIRMATION_ID_FIELD = '!#confirmationId';
    const CLIENT_DATA_FIELD     = '!#clientData';
    const ACTION_FIELD          = '!#action';
    const EVENT_TYPE            = '!#eventType';
    const OBJECT_VALUE          = '!#objectValue';

    private string      $confirmationPopupId;
    private string      $message;
    private string      $label;
    private bool        $selection         = false;
    private ?string     $proceedButtonValue;
    private ?string     $cancelButtonValue;
    private string      $verificationValue = '';
    private string      $verificationLabel = '';
    private ?int        $height            = null;
    private ?int        $width             = null;
    private ?ClientData $clientData;
    private ?GetData    $getData;
    private string      $actionId;
    private string      $confirmActionId;
    private string      $containerNonce;
    private array       $objectProperties  = [];
    private string      $eventType;
    private string      $objectValue;

    public function __construct(ContainerInterface $container, ?ClientData $clientData, string $confirmActionId, string $message, string $label = '', ?string $proceedButtonText = null, ?string $cancelButtonText = null, ?GetData $getData = null, string $eventType = '', string $objectValue = '')
    {
        $this->message             = $message;
        $this->label               = $label;
        $this->proceedButtonValue  = $proceedButtonText;
        $this->cancelButtonValue   = $cancelButtonText;
        $this->confirmationPopupId = $this->getConfirmationPopupId($container, $confirmActionId);
        $this->containerNonce      = $container->getNonce();
        $this->clientData          = $clientData;
        $this->actionId            = $container->getActionId();
        $this->confirmActionId     = $confirmActionId;
        $this->getData             = $getData;
        $this->eventType           = $eventType;
        $this->objectValue         = $objectValue;
    }

    private function getConfirmationPopupId(ContainerInterface $container, string $confirmActionId): string
    {
        $confirmationId2 = clone $container->getNewId();
        $confirmationId2->addIdElement(new IDElement('!#aid', $confirmActionId));
        return $confirmationId2->getEncryptedId();
    }

    /**
     * display input field.
     * The user must enter $verification_value to be able to confirm
     * @param string $verificationValue the text that must be entered to enable the proceed button
     * @param string $verificationLabel displayed as an input note
     * @return $this
     */
    public function setInputVerification(string $verificationValue, string $verificationLabel = ''): self
    {
        $this->verificationValue = $verificationValue;
        $this->verificationLabel = $verificationLabel;
        return $this;
    }

    /**
     * set the height of the confirmation popup. Default height is 200
     * @param int $height
     * @return $this
     */
    public function setHeight(int $height): self
    {
        $this->height = $height;
        return $this;
    }

    /**
     * set the width of the confirmation popup. Default width is 400
     * @param int $width
     * @return $this
     */
    public function setWidth(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    /**
     * returns true if the user agreed to the confirmation
     * return false if the user canceled the confirmation or closed the popup
     * @return bool|null
     */
    public function selection(): ?bool
    {
        return $this->selection;
    }

    /**
     * used by the session to register this confirmation popup
     *
     * @return string
     * @internal
     */
    public function getID(): string
    {
        return $this->confirmationPopupId;
    }

    /**
     * returns the data to create a generic confirmation popup
     * @return array
     */
    public function createConfirmationPopup(): array
    {
        $message              = Strings::purify($this->message, true);
        $confirmButtonEventId = $this->getObjectId(new Button(self::BUTTON_ID));

        $getDataField = $this->getData !== null ? $this->getHiddenField(self::GET_DATA_FIELD, serialize($this->getData)) : '';
        $eventType    = $this->eventType === '' ? EventType::OnClick->value : $this->eventType;
        $content      = '<?xml version="1.0" encoding="utf-8"?>
                <items>
                    <item type="label" name="Message" label="'.$message.'" className="message_notice_label" position="label-left"/>
                    '.$this->getInputVerification().'
                    <item type="block" className="bs_confirmation_block">
                        '.$this->getCancelButton().'
                        <item type="newcolumn"/>
                        '.$this->getProceedButton($confirmButtonEventId).'
                    </item>
                    '.$this->getHiddenField(self::CONFIRMATION_ID_FIELD, $this->confirmActionId, false).'
                    '.$this->getHiddenField(self::CLIENT_DATA_FIELD, serialize($this->clientData)).'
                    '.$getDataField.'
                    '.$this->getHiddenField(self::ACTION_FIELD, $this->actionId, false).'
                    '.$this->getHiddenField(self::EVENT_TYPE, $eventType, false).'
                    '.$this->getHiddenField(self::OBJECT_VALUE, $this->objectValue, false).'
                </items>';
        $post         = [];
        if ($this->verificationValue !== '') {
            $post = [
                'bs_confirmation_id' => $confirmButtonEventId,
                'bs_confirmation'    => $this->verificationValue
            ];
        }
        $result['popup'][$this->confirmationPopupId] = [
            'height' => $this->height !== null ? $this->height : self::DEFAULT_HEIGHT,
            'width'  => $this->width !== null ? $this->width : self::DEFAULT_WIDTH,
            'modal'  => true,
            'class'  => 'confirmationPopup',
            'layout' => [
                'pattern' => '1C',
                'cells'   => [
                    'a' => new ClientCell(
                        new ClientCellProperties(nonce: '', label: $this->label, id: $this->confirmationPopupId),
                        new ContentComponent(
                            type   : ContentType::DhtmlxForm,
                            content: $content,
                            events : [new ClientCellEvent('onButtonClick', 'doOnButtonClick'), new ClientCellEvent('onButtonClick', 'doOnCloseButtonClick')],
                            setup  : ['op' => $this->getObjectProperties()],
                            update : $post
                        )
                    )
                ]
            ]
        ];
        $result['state']                             = HttpResponseState::SUCCESS->value;
        return $result;
    }

    /**
     * @throws Exception
     */
    private function getObjectProperties(): string
    {
        return call_user_func([ContentClassFactory::getFormClass(), 'getObjectProperties'], ($this->objectProperties));
    }

    private function getInputVerification(): string
    {
        if ($this->verificationValue === '') {
            return '';
        }
        $inputId   = $this->getObjectId(new Input('!#inputVerification'));
        $inputNote = !empty($this->verificationLabel) ? '<note>'.Strings::purify($this->verificationLabel, true).'</note>' : '';
        return '<item type="input" name="'.$inputId.'" width="350" offsetLeft="5">'.$inputNote.'<userdata name="inputVerification">1</userdata></item>';
    }

    private function getHiddenField(string $name, string $value, bool $compress = true): string
    {
        if ($compress === true && extension_loaded('zlib') === true) {
            $hiddenValue = Session::encrypt(gzcompress($value, 9));
        } else {
            $hiddenValue = Session::encrypt($value);
        }
        $name = $this->getObjectId(new Hidden($name, ''));
        return '<item type="hidden" name="'.$name.'" value="'.$hiddenValue.'"/>';
    }

    private function getProceedButton(string $confirmButtonEventId): string
    {
        $proceedButtonLabel = ($this->proceedButtonValue === null) ? Locale::get('byteShard.popup.confirmation.button.proceed') : $this->proceedButtonValue;
        $formObjectClass    = Button::class;
        if (str_starts_with($formObjectClass, 'byteShard\\Form\\Control\\')) {
            $formObjectClass = '!f'.substr($formObjectClass, 23);
        }
        $this->objectProperties[] = (object)['i' => self::BUTTON_ID, 't' => $formObjectClass];
        return '<item type="button" name="'.$confirmButtonEventId.'" value="'.$proceedButtonLabel.'" '.($this->verificationValue !== '' ? 'disabled="true"' : '').'/>';
    }

    private function getCancelButton(): string
    {
        $cancelButtonLabel = ($this->cancelButtonValue === null) ? Locale::get('byteShard.popup.confirmation.button.cancel') : $this->cancelButtonValue;
        return '<item type="button" name="close" value="'.$cancelButtonLabel.'"><userdata name="clientClose">1</userdata></item>';
    }

    private function getObjectId(FormObject $formObject): string
    {
        $this->objectProperties[] = Proxy::getProperties($formObject, $formObject->getName(), '', AccessType::READWRITE);
        return Proxy::getEncryptedClientName($formObject->getName(), $this->containerNonce);
    }
}
