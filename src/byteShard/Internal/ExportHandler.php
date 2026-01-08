<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Cell;
use byteShard\Enum;
use byteShard\Event\OnClickInterface;
use byteShard\Internal\Action\ExportInterface;
use byteShard\Internal\Export\ClientExport\DirectExport;
use byteShard\Internal\Export\HandlerInterface;
use byteShard\Internal\Struct\ClientData;
use byteShard\Internal\Struct\GetData;
use byteShard\ID;
use byteShard\Locale;
use PhpOffice\PhpSpreadsheet\Exception;

//TODO: use Message es error message instead of state 0
class ExportHandler
{
    /** the array key that is used in the session to store export progress */
    const SESSION_INDEX = 'export_state';

    private ErrorHandler           $errorHandler;
    private ?Cell                  $cell;
    private Export\ExportInterface $cellContent;
    private int                    $timeout       = 600;
    private string                 $appName;
    private ?string                $contentId;
    private ?ExportInterface       $exportAction  = null;
    private Enum\Export\ExportType $exportType;
    private string                 $exportId;
    private bool                   $sessionClosed = false;
    private string                 $label;
    private string                 $eventId;
    private ?ClientData            $clientData;
    private ?GetData               $getData;
    private ?HandlerInterface      $exportHandler;

    /**
     * ExportHandler constructor.
     * @param ErrorHandler $errorHandler
     * @param string $xid
     * @param string $eventId
     * @param string $appName
     * @param string $exportId
     * @param string $eventName
     * @param ClientData|null $clientData
     * @param GetData|null $getData
     */
    public function __construct(ErrorHandler $errorHandler, string $xid, string $eventId, string $appName, string $exportId, string $eventName, ?ClientData $clientData = null, ?GetData $getData = null)
    {
        $this->errorHandler = $errorHandler;
        $this->errorHandler->setExportID($exportId);
        $this->errorHandler->setSessionIndexOfExports(self::SESSION_INDEX);
        $this->errorHandler->setResultObject(ErrorHandler::RESULT_OBJECT_EXPORT);

        $id             = ID\ID::decryptFinalImplementation($xid);
        $this->exportId = $exportId;
        $decrypted      = json_decode(\byteShard\Session::decrypt($eventId));
        $this->eventId  = $decrypted->i;
        $this->initSession();
        $this->clientData = $clientData;
        $this->getData    = $getData;

        $this->appName = ($appName !== '') ? $appName : 'defaultAppName';
        $actions       = [];
        if ($id->isCellId() === true) {
            $this->cell  = \byteShard\Session::getCell($id);
            $this->label = $this->cell->getLabel();
            //TODO: evaluate $eventName and chose the correct eventInterface
            //TODO: add timezone
            //TODO: pass object properties from Request
            $actions = ActionCollector::getEventActions($this->cell, $id, OnClickInterface::class, $this->eventId, '', '', $clientData, $getData, null, [], $eventName, $this->getCellContent());
        } else {
            //TODO: implement getEventActions to be compatible with tabs, change getCellContentCallback to expect return type of some common interface and so on
            /*$this->tab          = \byteShard\Session::getTab($id);
            $this->label        = $this->tab->getLabel();
            if ($this->cell instanceof EventStorageInterface) {
                $actions = $this->tab->getActionsForEvent($this->eventId);
            }*/
        }
        foreach ($actions as $action) {
            if ($action instanceof ExportInterface) {
                $this->exportAction = $action;
                $this->exportType   = $action->getType();
                $this->timeout      = $action->getTimeout();
                $this->contentId    = $action->getContentId();
                //TODO: check if there are multiple export actions defined for this event.
                // better yet, check both here and during action assignment of event in the respective classes. this should never happen.
                //TODO: check for nested exprto actions, e.g. confirmation
                break;
            }
        }
        $this->getExportHandler();
    }

    private function getCellContent(): Export\ExportInterface
    {
        if (!isset($this->cellContent)) {
            $className         = $this->cell->getContentClass();
            $this->cellContent = ContentClassFactory::cellContent($className, '', $this->cell);
            if ($this->clientData !== null) {
                $this->cellContent->setProcessedClientData($this->clientData);
            }
        }
        return $this->cellContent;
    }

    /**
     * set the timeout for the export to be finished
     * default is 600s
     * @param int $int
     * @return $this
     * @api
     */
    public function setTimeout(int $int): self
    {
        $this->timeout = $int;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function getExport(Enum\Export\Action $action): void
    {
        if ($this->exportAction !== null) {
            switch ($action) {
                case Enum\Export\Action::GET_FILE:
                    $this->getFile();
                    break;
                case Enum\Export\Action::GET_STATE:
                    $this->getState();
                    break;
            }
        }
    }

    /**
     * get the export state.
     * This is polled every 500ms to check if the export has finished or an error occurred
     */
    private function getState(): void
    {
        // return an error if the export time exceeds the predefined timeout
        if (time() - $_SESSION[self::SESSION_INDEX][$this->exportId]['start_time'] > $this->timeout) {
            $this->displayClientError(Locale::get('byteShard.bs_export.timeout'));
        }

        $response['state'] = $_SESSION[self::SESSION_INDEX][$this->exportId]['state'];
        if (isset($_SESSION[self::SESSION_INDEX][$this->exportId]['description'])) {
            $response['description'] = $_SESSION[self::SESSION_INDEX][$this->exportId]['description'];
        }

        // if the file has been created or an error occurred, clean session info
        $this->clearSession(Enum\HttpResponseState::tryFrom($response['state']));

        $httpResponse = new HttpResponse(Enum\HttpResponseType::JSON);
        $httpResponse->setResponseContent($response);
        $httpResponse->printHTTPResponse();
    }

    private function displayClientError(string $message): never
    {
        $response['state']       = Enum\HttpResponseState::ERROR->value;
        $response['description'] = $message;
        $this->clearSession(Enum\HttpResponseState::ERROR);
        $httpResponse = new HttpResponse(Enum\HttpResponseType::JSON);
        $httpResponse->setResponseContent($response);
        $httpResponse->printHTTPResponse();
    }

    private function getFile(): void
    {
        $this->closeSession();
        $documentTitle  = $this->getComponentName();
        $documentAuthor = \byteShard\Session::getUsername() ?? '';
        switch ($this->exportType) {
            case Enum\Export\ExportType::XLS:
                $this->getXLSExport($documentTitle, $documentAuthor);
                break;
            case Enum\Export\ExportType::CSV:
                $this->getXLSExport($documentTitle, $documentAuthor, 'CSV');
                break;
            case Enum\Export\ExportType::PDF:
                $this->getPDFExport();
                break;
            case Enum\Export\ExportType::CUSTOM_XLS:
                $this->getCustomXLSExport();
                break;
            case Enum\Export\ExportType::CUSTOM_PPT:
                $this->getCustomPPTExport();
                break;
            case Enum\Export\ExportType::CUSTOM:
            case Enum\Export\ExportType::DOWNLOAD:
                $this->getCustomExport();
                break;
        }
    }

    /**
     * v1.1
     * @return string
     */
    private function getComponentName(): string
    {
        $actionName = $this->exportAction->getName();
        if ($actionName !== null) {
            $componentName = $actionName;
        } else {
            if ($this->label !== '') {
                $componentName = $this->label;
            } else {
                $componentName = Locale::get('byteShard.action.custom_export.default_name');
            }
        }
        return $componentName;
    }

    /**
     * v1.1
     * @return string
     */
    public function getFilename(): string
    {
        $fileName   = [];
        $actionName = $this->exportAction->getName();
        if ($this->exportAction->getUseDateInFilename() === true) {
            $fileName[] = date("Ymd");
        }
        if ($this->exportAction->getUseApplicationNameInFilename() === true) {
            $fileName[] = $this->appName;
        }
        if ($actionName !== null) {
            $fileName[] = $actionName;
        } else {
            if ($this->label !== '') {
                $fileName[] = $this->label;
            } else {
                $fileName[] = Locale::get('byteShard.action.custom_export.default_name');
            }
        }
        $fileName = implode('_', $fileName);
        $fileName = str_replace("/", "_", $fileName);
        $fileName = str_replace("\\", "_", $fileName);
        return str_replace(" ", "_", $fileName);
    }

    private function getXLSExport(string $documentTitle, string $documentAuthor, string $type = 'Excel'): void
    {
        if ($this->exportHandler !== null) {
            $this->exportHandler->getXLSExport($documentTitle, $documentAuthor, $type);
        } else {
            $this->updateSession(Enum\HttpResponseState::ERROR, 'getXLSExport not implemented');
        }
    }

    private function getPDFExport(): void
    {
        if ($this->exportHandler !== null) {
            $this->exportHandler->getPDFExport();
        } else {
            $this->updateSession(Enum\HttpResponseState::ERROR, 'getPDFExport not implemented');
        }
    }

    private function getCustomXLSExport(): void
    {
        $exportInterface = $this->getCellContent();
        $data            = $exportInterface->getXlsExport($this->contentId);
        $dirExport       = new DirectExport($data['format'], $data['content']);
        $dirExport->setFileName($this->getFilename());
        $dirExport->setSheetName("EE System Matrix");
        $dirExport->createXLS();
        $dirExport->getExportFile();
        $this->updateSession(Enum\HttpResponseState::SUCCESS);
    }

    private function getCustomExport(): void
    {
        $cellContent = $this->getCellContent();
        try {
            $result = $cellContent->defineDownloadParent();
        } catch (\byteShard\Exception $exception) {
            \byteShard\Debug::error('Export failed: '.$exception->getMessage().' ('.$exception->getCode().')');
            $this->displayClientError($exception->getClientMessage() ?? $exception->getMessage());
        } catch (\Exception $exception) {
            \byteShard\Debug::error('Export failed: '.$exception->getMessage().' ('.$exception->getCode().')');
            $this->displayClientError($exception->getMessage());
        }

        $this->updateSession(Enum\HttpResponseState::SUCCESS);
        // TODO: pass the output buffer to the error handler
        global $output_buffer;
        $output_buffer = ob_get_clean();
        // first we write the default headers
        header('Content-Type: '.$result->getContentType());
        header('Content-Disposition: attachment; filename="'.$result->getName().'.'.$result->getFileExtension().'"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
        header('Content-Transfer-Encoding: binary');
        $contentLength = $result->getContentLength();
        if ($contentLength !== null) {
            header('Content-Length: '.$contentLength);
        }

        // now we loop over the returned by defineDownload
        // this will replace the default headers
        $headers = $result->getHeaders();
        foreach ($headers as $header) {
            header($header);
        }
        $result->getContent();
    }

    /**
     * initialize the session state and start_time for this particular export
     */
    private function initSession(): void
    {
        if (!array_key_exists(self::SESSION_INDEX, $_SESSION)) {
            $_SESSION[self::SESSION_INDEX] = [];
        }
        if (!array_key_exists($this->exportId, $_SESSION[self::SESSION_INDEX])) {
            $_SESSION[self::SESSION_INDEX][$this->exportId]['start_time'] = time();
            $_SESSION[self::SESSION_INDEX][$this->exportId]['state']      = Enum\HttpResponseState::RUNNING->value;
        }
    }

    /**
     * cleanup session
     * @param ?Enum\HttpResponseState $state
     */
    private function clearSession(?Enum\HttpResponseState $state): void
    {
        if ($state === Enum\HttpResponseState::ERROR || $state === Enum\HttpResponseState::SUCCESS) {
            $this->startSession();
            unset($_SESSION[self::SESSION_INDEX][$this->exportId]);
            $this->closeSession();
        }
    }

    /**
     * update the export state
     * @param Enum\HttpResponseState $state
     * @param string $description
     */
    public function updateSession(Enum\HttpResponseState $state, string $description = ''): void
    {
        $this->startSession();
        $_SESSION[self::SESSION_INDEX][$this->exportId]['state']       = $state->value;
        $_SESSION[self::SESSION_INDEX][$this->exportId]['description'] = $description;
        $this->closeSession();
    }

    /**
     * start the session in case it has been closed
     */
    private function startSession(): void
    {
        if ($this->sessionClosed === true) {
            session_start();
            $this->sessionClosed = false;
            $this->errorHandler->setSessionClosed(false);
        }
    }

    /**
     * close the session and update the current session state
     */
    private function closeSession(): void
    {
        $this->sessionClosed = true;
        $this->errorHandler->setSessionClosed(true);
        session_write_close();
    }


    private function getCustomPPTExport(): void
    {
        //$debugLog->toFile("getDataSrc");
        //$debugLog->toFile($_GET['dataSrc']);
        //if (class_exists($_GET['dataSrc']) && checkValidFunctionNames($_GET['dataSrc'])) {
        //require_once(libPath.'/clientExport/lib/PHPPowerpoint.php');
        //require_once(libPath.'/clientExport/lib/PHPPowerpoint/IOFactory.php');
        //require_once(libPath.'/clientExport/lib/PHPExcel.php');
        //require_once(libPath.'/clientExport/lib/PHPExcel/IOFactory.php');
        //require_once(libPath.'/clientExport/ppt.class.php');
        //$classname                                                     = $_GET['dataSrc'];
        //$dirExport                                                     = new $classname($content_id);
        //$this->updateSession(self::FINISHED);
        //} else {
        //$this->updateSession(self::ERROR, getcwd);

        /*$tmp = $content_id.'#'.$export_action.'#'.$export_type.'#'.$component_name.'#';
        foreach ($data as $key => $val) {
           $tmp .= $key;
        }*/

        //$_SESSION['export_state'][$export_type.$component_name]['description']= 'The export function '.$_GET['dataSrc'].' is invalid';
        //}
        //Requested user_func valid?
        /*if(function_exists($_GET['dataSrc']) && checkValidFunctionNames($_GET['dataSrc'])){
           $data=call_user_func($_GET['dataSrc'],$content_id);

           require_once(libPath.'/clientExport/lib/PHPExcel.php');
           require_once(libPath.'/clientExport/lib/PHPExcel/IOFactory.php');
           require_once(libPath.'/clientExport/directExport.class.php');
           $dirExport=new directExport($data['format'],$data['content']);
           $dirExport->setFileName($filename);
           $dirExport->setSheetName("EE System Matrix");
           $dirExport->createXLS();
           $dirExport->getExportFile();
           $_SESSION['export_state'][$export_type.$component_name]['state']=Enum\HttpResponseState::SUCCESS->value;
        }else{
           $_SESSION['export_state'][$export_type.$component_name]['state']=Enum\HttpResponseState::ERROR->value;
           $_SESSION['export_state'][$export_type.$component_name]['description']= 'The export function '.$_GET['dataSrc'].' is invalid';
        }*/
    }

    public function getAppName(): string
    {
        return $this->appName;
    }

    private function getExportHandler(): void
    {
        $this->exportHandler = $this->getCellContent()?->getExportHandler($this);
    }
}
