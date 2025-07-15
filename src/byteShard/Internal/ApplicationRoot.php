<?php

namespace byteShard\Internal;

use byteShard\Crypto\Symmetric;
use byteShard\Enum\HttpResponseState;
use byteShard\Environment;

class ApplicationRoot
{
    private ?string $dhtmlxCssImagePath;
    private bool    $debug;
    private string  $locale;
    private array   $locales;
    private string  $selectedId;

    public function __construct(Environment $environment)
    {
        $this->dhtmlxCssImagePath = $environment->getDhtmlxCssImagePath();
        $this->debug              = $environment->getDebug();
        $this->locale             = $environment->getLocale();
        $this->locales            = $environment->getLocales();
        $this->selectedId         = $environment->getLastTab(\byteShard\Session::getUserId());
    }

    public function getRootArray(ApplicationRootInterface $rootObject): array
    {
        $rootContent              = $rootObject->getRootParameters($this->selectedId);
        $rootContent->setup['id'] = 0;
        $result['content'][]      = $rootContent;
        $result['locale']         = $this->getLocale();
        $result['debug']          = $this->debug;
        $result['sn']             = $this->getNonce();
        if ($this->dhtmlxCssImagePath !== null) {
            $result['dhtmlxCssImgPath'] = $this->dhtmlxCssImagePath;
        }
        $result['state'] = HttpResponseState::SUCCESS->value;
        return $result;
    }

    private function getNonce(): string
    {
        $nonce = Symmetric::deriveNonce(\byteShard\Session::getCryptoKey(), 'topLevelNonce');
        return \byteShard\Session::encrypt(\byteShard\Session::getTopLevelNonce(), $nonce);
    }

    private function getLocale(): array
    {
        $interfaceLocale = new SessionLocale($this->locale);
        $interfaceLocale->setSupportedApplicationLocales($this->locales);
        return $interfaceLocale->getInterfaceLocale();
    }
}