<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\CellContent;
use byteShard\Internal\ContainerInterface;
use byteShard\Internal\Struct;
use byteShard\Locale;
use byteShard\Popup\Confirmation;
use byteShard\Popup\Enum\Message\Type;
use byteShard\Popup\Message;
use byteShard\Utils\Strings;

/**
 * Class ConfirmAction
 *
 * any nested actions will only be executed after the client confirms to proceed
 * @package byteShard\Action
 */
class ConfirmAction extends Action
{
    private string            $instanceName;
    private string            $fixedMessage;
    private string            $title;
    private string            $proceedButtonText;
    private string            $cancelButtonText;
    private int               $height;
    private int               $width;
    private string            $message                             = '';
    private string            $token                               = '';
    private null|array|object $localeReplacements                  = null;
    private bool              $showConfirmationDialogue            = true;
    private string            $verificationValue                   = '';
    private string            $verificationLabel                   = '';
    private bool              $continueWithoutConfirmationDialogue = false;
    private bool              $confirmed                           = false;
    private string            $confirmationPopupId                 = '';
    private string            $objectValue                         = '';

    /**
     * ConfirmAction constructor.
     *
     * if $message is null this will create an instance of the current cell_content and call the method defineConfirmationDialogue and the $instance_name will be passed
     * if $proceed_button_text is null, the locale for 'byteShard.popup.confirmation.button.proceed' will be used
     * if $cancel_button_text is null, the locale for 'byteShard.popup.confirmation.button.cancel' will be used
     *
     * @param string $instanceName
     * @param Action|null $action
     * @param string $title
     * @param string $message // if $message is not empty the defineConfirmationDialogue callback will be skipped and the confirmation will be displayed every single time
     * @param string $proceedButtonText
     * @param string $cancelButtonText
     */
    public function __construct(string $instanceName, ?Action $action = null, string $title = '', string $message = '', string $proceedButtonText = '', string $cancelButtonText = '')
    {
        parent::__construct();
        $this->addUniqueID($instanceName);
        $this->instanceName      = $instanceName;
        $this->fixedMessage      = $message;
        $this->title             = $title;
        $this->proceedButtonText = $proceedButtonText;
        $this->cancelButtonText  = $cancelButtonText;
        $this->height            = Confirmation::DEFAULT_HEIGHT;
        $this->width             = Confirmation::DEFAULT_WIDTH;
        if ($action !== null) {
            $this->addAction($action);
        }
    }

    /**
     * @API
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @API
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    private function getObjectValue(): string
    {
        return $this->objectValue;
    }

    /**
     * @param string $objectValue
     */
    public function setObjectValue(string $objectValue): void
    {
        $this->objectValue = $objectValue;
    }

    /**
     * display input field.
     * The user must enter $verificationValue to be able to continue
     * @param string $verificationValue
     * @param string $verificationLabel
     * @return $this
     * @API
     */
    public function setInputVerification(string $verificationValue, string $verificationLabel = ''): self
    {
        $this->verificationValue = $verificationValue;
        $this->verificationLabel = $verificationLabel;
        return $this;
    }

    /**
     * set a locale token for the message. It will be prepended by the localeBaseToken and appended by .noConfirmation.Label or .Label
     * @API
     */
    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @API
     */
    public function setLocaleReplacements(array|object $replacements): self
    {
        $this->localeReplacements = $replacements;
        return $this;
    }

    /**
     * @API
     */
    public function getInstanceName(): string
    {
        return $this->instanceName;
    }

    /**
     * @API
     */
    public function getActionId(): string
    {
        return $this->instanceName;
    }

    /**
     * @API
     */
    public function showConfirmationDialogue(bool $showDialogue): self
    {
        $this->showConfirmationDialogue = $showDialogue;
        return $this;
    }

    /**
     * @API
     */
    public function continueWithoutConfirmationDialogue(bool $continue = true): self
    {
        $this->continueWithoutConfirmationDialogue = $continue;
        return $this;
    }

    /**
     * set the height of the confirmation popup. Default height is 200
     * @API
     */
    public function setHeight(int $height): self
    {
        $this->height = $height;
        return $this;
    }

    /**
     * set the width of the confirmation popup. Default width is 400
     * @API
     */
    public function setWidth(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    /**
     * @param string $instanceName
     * @param string $confirmationPopupId
     * @return void
     * @internal
     * TODO: create method at parent Action level and inject/pass-through every action. Make method protected
     */
    public function setConfirmedInstance(string $instanceName, string $confirmationPopupId): void
    {
        if ($instanceName === $this->instanceName) {
            $this->confirmed           = true;
            $this->confirmationPopupId = $confirmationPopupId;
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $container = $this->getLegacyContainer();
        $id        = $this->getLegacyId();
        if (($id instanceof Struct\GetData) && ($container instanceof Cell)) {
            $container->setGetDataActionClientData($id);
        }

        // exit because user confirmed the dialogue, this is defined in the EventHandler
        if ($this->confirmed === true) {
            return $this->userClickedConfirmProceedWithNestedActions();
        }

        // initialize ConfirmAction and callback the method defineConfirmationDialogue in the user defined CellContent
        $this->init($container);

        // exit because this action skips the confirmation
        if ($this->continueWithoutConfirmationDialogue === true) {
            $this->setRunNested();
            return new Action\ActionResult();
        }

        [$message, $messageToken] = $this->getMessage();
        $title = $this->getConfirmationPopupTitle();
        if ($message === '') {
            // no message to display provided. Show the developer what's wrong
            return $this->undefinedConfirmationMessage($messageToken, $title);
        }

        if ($this->showConfirmationDialogue === true) {
            $confirmationPopup = new Confirmation($container, $this->getClientData(), $this->instanceName, $message, $title, $this->getProceedButtonLabel(), $this->getCancelButtonLabel(), $this->getGetData(), $this->getEventType(), $this->getObjectValue());

            if ($this->verificationValue !== '') {
                $confirmationPopup->setInputVerification($this->verificationValue, $this->getVerificationLabel());
            }
            $confirmationPopup->setHeight($this->height);
            $confirmationPopup->setWidth($this->width);
            return new Action\ActionResultMigrationHelper($confirmationPopup->createConfirmationPopup());
        }

        // no confirmation dialoge defined, show message popup with "ok" button
        $message = new Message($message, Type::NOTICE);
        $message->setLabel($title);
        return new Action\ActionResultMigrationHelper($message->getNavigationArray());
    }

    private function getProceedButtonLabel(): ?string
    {
        if ($this->proceedButtonText !== '') {
            return $this->proceedButtonText;
        }
        $proceedLocale = Locale::getArray($this->localeBaseToken.'.Action.ConfirmAction.'.$this->instanceName.'.Proceed');
        return ($proceedLocale['found'] === true) ? $proceedLocale['locale'] : null;
    }

    private function getCancelButtonLabel(): ?string
    {
        if ($this->cancelButtonText !== '') {
            return $this->cancelButtonText;
        }
        $cancelLocale = Locale::getArray($this->localeBaseToken.'.Action.ConfirmAction.'.$this->instanceName.'.Cancel');
        return ($cancelLocale['found'] === true) ? $cancelLocale['locale'] : null;
    }

    private function getVerificationLabel(): string
    {
        if ($this->verificationLabel !== '') {
            return $this->verificationLabel;
        }
        $verificationLocale = Locale::getArray($this->localeBaseToken.'.Action.ConfirmAction.'.$this->instanceName.'.InputVerification');
        return $verificationLocale['found'] === true ? $verificationLocale['locale'] : '';
    }

    private function userClickedConfirmProceedWithNestedActions(): ActionResultInterface
    {
        $this->setRunNested();
        $result['state']                                      = 2;
        $result['popup'][$this->confirmationPopupId]['close'] = true;
        $this->confirmed                                      = false;
        $this->confirmationPopupId                            = '';
        return new Action\ActionResultMigrationHelper($result);
    }

    private function init(ContainerInterface $container): void
    {
        // reset show confirmation dialogue every time
        $this->showConfirmationDialogue = false;
        $this->setRunNested(false);
        $this->message = '';

        $class       = $container->getContentClass();
        if (class_exists($class)) {
            $cellContent = new $class($container);
            if ($cellContent instanceof CellContent) {
                $cellContent->setProcessedClientData($this->getClientData());
                // callback to cell content class
                $cellContent->getConfirmationDialogue($this);
            }
        }
    }

    private function getMessage(): array
    {
        if ($this->fixedMessage !== '') {
            return [$this->fixedMessage, ''];
        }
        $message = '';

        // message was defined in callback, return it
        if ($this->message !== '') {
            return [$this->message, ''];
        }

        // no message was explicitly defined in callback, try to query the locale (with or without defined token) and return the text if found
        $messageToken = $this->localeBaseToken.'.Action.ConfirmAction.'.$this->instanceName.($this->token !== '' ? '.'.$this->token : '').($this->showConfirmationDialogue === true ? '' : '.noConfirmation').'.Label';
        $tmpMessage   = Locale::getArray($messageToken);
        if ($tmpMessage['found'] === true) {
            $message = !empty($this->localeReplacements) ? Strings::vksprintf($tmpMessage['locale'], $this->localeReplacements) : $tmpMessage['locale'];
        }
        $this->token = '';
        return [$message, $messageToken];
    }

    private function getConfirmationPopupTitle(): string
    {
        $title = $this->title;
        if ($title === '') {
            $tmpTitle = Locale::getArray($this->localeBaseToken.'.Action.ConfirmAction.'.$this->instanceName.'.Title');
            if ($tmpTitle['found'] === true) {
                $title = $tmpTitle['locale'];
            }
        }
        return $title;
    }

    private function undefinedConfirmationMessage(string $searchedToken, string $title): ActionResultInterface
    {
        $message = new Message(Strings::vksprintf(Locale::get('byteShard.action.confirmAction.noMessageDefined'), array('token' => $searchedToken)), Type::NOTICE);
        $message->setLabel($title);
        return new Action\ActionResultMigrationHelper($message->getNavigationArray());
    }
}
