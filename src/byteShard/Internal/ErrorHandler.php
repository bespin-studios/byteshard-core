<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use ArgumentCountError;
use byteShard\Enum\ContentType;
use byteShard\Enum\HttpResponseState;
use byteShard\Enum\LogLevel;
use byteShard\Enum\LogLocation;
use byteShard\Internal\Exception\ExceptionInterface;
use byteShard\Internal\Struct\ClientCell;
use byteShard\Internal\Struct\ClientCellProperties;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Locale;
use byteShard\Internal\ErrorHandler\Template;
use byteShard\Popup\Message;
use byteShard\Exception;
use Error;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class ErrorHandler
 * @package byteShard\Internal
 */
class ErrorHandler
{
    const RESULT_OBJECT_CELL_CONTENT = 'cellContent';
    const RESULT_OBJECT_POPUP        = 'popup';
    const RESULT_OBJECT_HTML         = 'html';
    const RESULT_OBJECT_LOGIN        = 'login';
    const RESULT_OBJECT_EXPORT       = 'export';
    const RESULT_OBJECT_REST         = 'rest';

    private ?string $resultObjectType = null;
    /** @var LoggerInterface[] */
    private array       $loggers               = [];
    private LogLocation $logLocation;
    private string      $logDir;
    private string      $appUrl;
    private string      $printLogFilename      = 'print';
    private string      $errorLogFilename      = 'error';
    private string      $exceptionLogFilename  = 'exception';
    private string      $deprecatedLogFilename = 'deprecated';
    private bool        $exception             = false;
    private int         $num                   = 0;
    private string      $exportId;
    private bool        $sessionClosed         = false;
    private string      $sessionIndexOfExports;
    private bool        $debugBacktrace        = false;
    private ?string     $outOfMemoryHelper;

    public function __construct(string $logDir, string $appUrl, ?string $resultObjectType = null, LogLocation $logLocation = LogLocation::FILE)
    {
        $this->logDir            = $logDir;
        $this->appUrl            = $appUrl;
        $this->logLocation       = $logLocation;
        $this->outOfMemoryHelper = str_repeat('*', 1024 * 1024);
        if ($this->logLocation === LogLocation::FILE && !is_writable($this->logDir)) {
            ini_set('display_errors', 'on');
            error_reporting(E_ALL);
            $processUser = getenv('username');
            if (function_exists('posix_geteuid') === true && function_exists('posix_getpwuid') === true) {
                $processUser = posix_getpwuid(posix_geteuid());
            }
            if (str_contains(strtolower(php_uname('s')), 'windows')) {
                $processUser = get_current_user();
            }
            if (!empty($processUser)) {
                if (is_array($processUser)) {
                    $processUser = array_key_exists('name', $processUser) ? $processUser['name'] : '';
                }
                Template::printNoPermissionTemplate($this->logDir, $processUser);
                exit;
            }
            Template::printNoPermissionTemplate($this->logDir);
            exit;
        }
        ini_set('display_errors', 'off');
        error_reporting(E_ALL);
        ob_start();
        $this->resultObjectType = $resultObjectType;
        set_error_handler($this->callbackErrorHandler(...));
        set_exception_handler($this->callback_exception_handler(...));
        register_shutdown_function($this->callback_shutdown_function(...));
    }

    /**
     * @param string $name
     * @param LoggerInterface $logger
     */
    public function addLogger(string $name, LoggerInterface $logger): void
    {
        $this->loggers[$name] = $logger;
    }

    public function setResultObject(string $resultObjectType): void
    {
        $this->resultObjectType = $resultObjectType;
    }

    public function setExportID(string $exportId): void
    {
        $this->exportId = $exportId;
    }

    public function setSessionClosed(bool $bool): void
    {
        $this->sessionClosed = $bool;
    }

    public function setSessionIndexOfExports(string $sessionIndex): void
    {
        $this->sessionIndexOfExports = $sessionIndex;
    }

    /** @noinspection MoreThanThreeArgumentsInspection
     * @throws Exception
     */
    public function callbackErrorHandler(int $type, string $message, string $file, int $line): void
    {
        $channel = 'default';
        if (stripos($file, BS_FILE_PRIVATE_ROOT) !== false) {
            $path  = trim(str_replace(BS_FILE_PRIVATE_ROOT, '', $file), DIRECTORY_SEPARATOR);
            $paths = explode(DIRECTORY_SEPARATOR, $path);
            if (array_key_exists(0, $paths) && array_key_exists($paths[0], $this->loggers)) {
                $channel = $paths[0];
            }
        } elseif (stripos($file, BS_FILE_PUBLIC_ROOT) !== false) {
            $path  = trim(str_replace(BS_FILE_PUBLIC_ROOT, '', $file), DIRECTORY_SEPARATOR);
            $paths = explode(DIRECTORY_SEPARATOR, $path);
            if (array_key_exists(0, $paths) && array_key_exists($paths[0], $this->loggers)) {
                $channel = $paths[0];
            }
        }
        $context = ['callback_type' => 'error', 'file' => $file, 'line' => $line];
        if ($this->debugBacktrace === true) {
            $context['debug'] = debug_backtrace();
        }
        switch ($type) {
            case E_USER_NOTICE:
            case E_NOTICE:
                $this->sendMessageToLogger($channel, LogLevel::NOTICE, $message, $context);
                break;
            case E_USER_WARNING:
            case E_WARNING:
                $this->sendMessageToLogger($channel, LogLevel::WARNING, $message, $context);
                break;
            case E_USER_DEPRECATED:
            case E_DEPRECATED;
                $traces       = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
                $found        = false;
                $calledInFile = '';
                $calledInLine = '';
                foreach ($traces as $trace) {
                    if ($found === true) {
                        $calledInFile = array_key_exists('file', $trace) ? $trace['file'] : '';
                        $calledInLine = array_key_exists('line', $trace) ? $trace['line'] : '';
                        break;
                    }
                    if ($trace['function'] === 'trigger_error') {
                        $found = true;
                    }
                }
                $content = '['.date('Y-m-d H:i:s').'] '.$message.' '.json_encode(array('file' => $file, 'line' => $line, 'called_in_file' => $calledInFile, 'called_in_line' => $calledInLine));
                $this->printToFile($content, $this->deprecatedLogFilename, false, false);
                break;
            default:
                $e = new Exception($message);
                $e->setLocaleToken('byteShard.errorHandler.error');
                throw $e;
        }
    }

    private function sendMessageToLogger(string $channel, LogLevel $logLevel, string $message, array $context = []): void
    {
        switch ($logLevel) {
            case LogLevel::EMERGENCY:
                $this->loggers[$channel]->emergency($message, $context);
                break;
            case LogLevel::ALERT:
                $this->loggers[$channel]->alert($message, $context);
                break;
            case LogLevel::CRITICAL:
                $this->loggers[$channel]->critical($message, $context);
                break;
            case LogLevel::ERROR:
                $this->loggers[$channel]->error($message, $context);
                break;
            case LogLevel::WARNING:
                $this->loggers[$channel]->warning($message, $context);
                break;
            case LogLevel::NOTICE:
                $this->loggers[$channel]->notice($message, $context);
                break;
            case LogLevel::INFO:
                $this->loggers[$channel]->info($message, $context);
                break;
            default:
                $this->loggers[$channel]->debug($message, $context);
                break;
        }
    }

    /**
     * @param Throwable $e
     */
    public function callback_exception_handler(Throwable $e): void
    {
        global $output_buffer;
        // process exit before eof without triggering an error beforehand
        $phpCanWriteToLog = true;
        if ($output_buffer === null) {
            $output_buffer = ob_get_clean();
        }
        if (!empty($output_buffer)) {
            $phpCanWriteToLog = $this->printToFile($output_buffer, $this->printLogFilename);
        }
        if ($phpCanWriteToLog === true) {
            if ($e instanceof ExceptionInterface) {
                $channel = $e->getLogChannel();
                if (!array_key_exists($channel, $this->loggers)) {
                    $channel = 'default';
                }
                if (defined('LOGLEVEL') && LOGLEVEL === LogLevel::DEBUG) {
                    $this->sendMessageToLogger($channel, $e->getLogLevel(), $e->getMessage(), array('callback_type' => 'exception', 'code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getStackTrace()));
                } else {
                    $this->sendMessageToLogger($channel, $e->getLogLevel(), $e->getMessage(), array('callback_type' => 'exception', 'code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()));
                }
            } else {
                if ($this->logLocation === LogLocation::STDERR) {
                    $this->sendMessageToLogger('default', LogLevel::ERROR, $e->getMessage(), array('callback_type' => 'exception', 'code' => '00000000', 'file' => $e->getFile(), 'line' => $e->getLine()));
                } else {
                    $this->printToFile(print_r($e, true), $this->exceptionLogFilename);
                }
            }
        }
        $this->exception = true;
        if ($this->resultObjectType !== null) {
            switch ($this->resultObjectType) {
                case self::RESULT_OBJECT_CELL_CONTENT:
                    $this->printCellContent(
                        ($e instanceof ExceptionInterface) ? $e->getClientMessage() : '',
                        ($e instanceof ExceptionInterface) ? $e->getCode() : '');
                    break;
                case self::RESULT_OBJECT_POPUP:
                    $this->printPopupContent(
                        ($e instanceof ExceptionInterface) ? $e->getClientMessage() : '',
                        ($e instanceof ExceptionInterface) ? $e->getCode() : '',
                        ($e instanceof ExceptionInterface) ? $e->getUploadFileName() : '');
                    break;
                case self::RESULT_OBJECT_HTML:
                    Template::printGenericExceptionTemplate($e->getMessage());
                    exit;
                case self::RESULT_OBJECT_EXPORT:
                    if (isset($this->sessionIndexOfExports, $this->exportId)) {
                        if ($this->sessionClosed === true) {
                            session_start();
                        }
                        $_SESSION[$this->sessionIndexOfExports][$this->exportId]['state']       = HttpResponseState::ERROR->value;
                        $_SESSION[$this->sessionIndexOfExports][$this->exportId]['description'] = 'An  error during export has occurred';
                    }
                    break;
                case self::RESULT_OBJECT_REST:
                    $this->printRestApiError($e);
                case self::RESULT_OBJECT_LOGIN:
                default:
                    $this->printLoginContent($phpCanWriteToLog);
            }
        }
    }

    /**
     * this is called on shutdown of the php process
     * we use this to catch any remaining output buffers and redirect them to file
     */
    public function callback_shutdown_function(): void
    {
        // free up emergency memory
        $this->outOfMemoryHelper = null;
        $error                   = error_get_last();
        if ($error !== null) {
            if (headers_sent() === true) {
                print '!#bs#!';
            }
            if (ob_get_status()) {
                $tmp = ob_get_clean();
                if ($tmp !== false) {
                    $this->printToFile($tmp, $this->printLogFilename);
                }
            }
            if ($this->debugBacktrace === true) {
                $error[] = debug_backtrace();
            }
            $phpCanWriteToLog = $this->printToFile('Shutdown - '.print_r($error, true), $this->errorLogFilename);
            if ($this->resultObjectType !== null) {
                $message = '';
                if (is_array($error) && array_key_exists('file', $error) && array_key_exists('message', $error)) {
                    if (stripos($error['file'], 'autoload') !== false) {
                        $message = sprintf(Locale::get('byteShard.errorHandler.shutdown.autoload_failed'), $error['message']);
                    } elseif (stripos($error['message'], 'class') !== false) {
                        if (stripos($error['message'], 'not found')) {
                            $message = sprintf(Locale::get('byteShard.errorHandler.shutdown.class_not_found'), $error['message']);
                        }
                    }
                }
                switch ($this->resultObjectType) {
                    case self::RESULT_OBJECT_CELL_CONTENT:
                        $this->printCellContent($message);
                        break;
                    case self::RESULT_OBJECT_POPUP:
                        $this->printPopupContent($message);
                        break;
                    case self::RESULT_OBJECT_HTML:
                        Template::printGenericExceptionTemplate('An error occurred');
                        exit;
                    case self::RESULT_OBJECT_EXPORT:
                        if (isset($this->sessionIndexOfExports, $this->exportId)) {
                            if ($this->sessionClosed === true) {
                                session_start();
                            }
                            $_SESSION[$this->sessionIndexOfExports][$this->exportId]['state'] = HttpResponseState::ERROR->value;
                        }
                        break;
                    case self::RESULT_OBJECT_REST:
                        $this->printRestApiError();
                    case self::RESULT_OBJECT_LOGIN:
                    default:
                        $this->printLoginContent($phpCanWriteToLog);
                }
            }
        } elseif ($this->exception === false && !empty($GLOBALS['output_buffer'])) {
            // no exception was caught. Any print/echo/var_dump will be in output_buffer
            // reroute output_buffer to file
            $this->printToFile($GLOBALS['output_buffer'], $this->printLogFilename);
        }
    }

    private function printRestApiError(?Throwable $exception = null): never
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(500);
        $message = 'Internal Server Error';
        if ($exception instanceof ArgumentCountError) {
            $message = 'Required parameter missing';
            http_response_code(400);
        } elseif ($exception instanceof Error && str_starts_with($exception->getMessage(), 'Unknown named parameter')) {
            http_response_code(400);
            $message = 'Unknown parameter';
        }
        print $message;
        exit;
    }

    /**
     * @param string $message
     * @param string $error_code
     */
    private function printCellContent(string $message = '', string $error_code = ''): void
    {
        // if an error occurs while loading the content of a cell, this returns a generic DHTMLXForm content with a generic error message
        if ($message === '') {
            // this will lead to "unknown error" in case no message was passed
            $message = Locale::get('byteShard.errorHandler.print_cell_content.no_message');
        }
        $result = new ClientCell(
            new ClientCellProperties(),
            new ContentComponent(
                type   : ContentType::DhtmlxForm,
                content: '<?xml version="1.0" encoding="utf-8"?><items><item type="label" name="Error" label="'.$message.(($error_code !== '' && $error_code !== null && $error_code !== '0') ? ' ('.$error_code.')' : '').'"/></items>'
            )
        );
        $result->setState(HttpResponseState::SUCCESS);
        if (!headers_sent()) {
            header('Status: 200');
            header('HTTP/1.0  200');
        }
        print json_encode($result);
    }

    /**
     * @param string $message
     * @param string $error_code
     * @param string $uploadFileName
     */
    private function printPopupContent(string $message = '', string $error_code = '', string $uploadFileName = ''): void
    {
        // if the byteShard framework is loaded, returns the message to the client to open a new popup delivering the error message (not used for loading cell data)
        if ($message === '') {
            // this will lead to "unknown error" in case no message was passed
            $message = Locale::get('byteShard.errorHandler.print_popup_content.no_message');
        }
        $message       .= (($error_code !== '' && $error_code !== null && $error_code !== '0') ? ' ('.$error_code.')' : '');
        $error_message = new Message($message);

        $result = array();
        if ($uploadFileName !== '') {
            $result['state'] = false;
            $result['name']  = $uploadFileName;
            $result['extra'] = $error_message->getNavigationArray();
        } else {
            $result = $error_message->getNavigationArray();
        }
        if (!headers_sent()) {
            header('Status: 200');
            header('HTTP/1.0  200');
        }
        print json_encode($result);
    }

    /**
     * @param bool $phpCanWriteToLog
     */
    private function printLoginContent(bool $phpCanWriteToLog): never
    {
        // if an error occurs before the byteShard framework is loaded, the user will be redirected to the login page, ERROR = true is saved in the session to be evaluated by the login form
        if ($this->appUrl !== '') {
            $path = rtrim($this->appUrl, '/').'/';
        } else {
            $path = rtrim(Server::getProtocol().'://'.Server::getHost(), '/').'/';
        }
        if (session_id()) {
            session_unset();
            session_destroy();
        }
        //TODO: check if some cookies have to be unset
        if (!headers_sent()) {
            $parameter = $phpCanWriteToLog ? '/' : '/?error=nolog';
            header('Location: '.$path.'login'.$parameter);
        }
        exit;
    }

    /**
     * @param string $string
     * @param string $filename
     * @param bool $date
     * @param bool $reformat
     * @return bool
     */
    private function printToFile(string $string, string $filename, bool $date = true, bool $reformat = true): bool
    {
        if ($this->logLocation === LogLocation::FILE) {
            if ($reformat === true) {
                $filename     .= '.html';
                $filename     = $this->logDir.DIRECTORY_SEPARATOR.(($date !== false) ? date('YmdHis').'_' : '').$filename;
                $insertScript = true;
                if (preg_match('//u', $string)) {
                    $string = mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
                }

                if (is_writable($this->logDir)) {
                    if (file_exists($filename)) {
                        $cont = file_get_contents($filename);
                        if ($cont !== false && str_contains($cont, '<b>Logged on:</b>')) {
                            $insertScript = false;
                            $count        = substr_count($cont, 'href="javascript: toggleDiv');
                            if ($count > 0) {
                                $this->num = $count;
                            }
                        }
                        unset($cont);
                    }
                    $file_handle = fopen($filename, 'a+b');
                    if ($file_handle !== false) {
                        fwrite($file_handle, $this->formatString($string, $insertScript));
                        fclose($file_handle);
                    }
                    return true; // log file is writable
                }
            } else {
                $filename .= '.log';
                $filename = $this->logDir.DIRECTORY_SEPARATOR.(($date !== false) ? date('YmdHis').'_' : '').$filename;
                if (preg_match('//u', $string)) {
                    $string = mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
                }
                if (is_writable($this->logDir)) {
                    $file_handle = fopen($filename, 'a+b');
                    if ($file_handle !== false) {
                        fwrite($file_handle, $string."\n");
                        fclose($file_handle);
                    }
                    return true; // log file is writable
                }
            }

        } elseif ($this->logLocation === LogLocation::STDERR) {
            $this->sendMessageToLogger('default', LogLevel::NOTICE, $string);
            return true; // we can always write to stderr
        }
        return false; // log file is NOT writable
    }

    /**
     * @param string $input_string
     * @param bool $script
     * @return string
     */
    private function formatString(string $input_string, bool $script = true): string
    {
        // this formats the output of arrays and objects so that data is more readable
        $output_string = '';
        if ($script === true) {
            $output_string .= "<script>function toggleDiv(num){var a=document.getElementById('d'+num);var b=document.getElementById('a'+num);var c=a.style.display;if(c==='none'){b.innerHTML='-';a.style.display='inline';}else{b.innerHTML='+';a.style.display='none';}}</script>
<style type=\"text/css\"><!--.arr {color:#0033FF}.ass {color:#C0C0C0}.ind {color:#00CC00}.pri {color:#CC0000}.pro {color:#9900CC}--></style>
";
        }
        $user = '';
        if (!empty($_SESSION) && is_array($_SESSION)) {
            foreach ($_SESSION as $val) {
                if ($val instanceof Session) {
                    $username = $val->getUsername();
                    if (!empty($username)) {
                        $user = ' - by: '.$val->getUsername();
                    }
                    break;
                }
            }
        }
        $output_string .= '<b>Logged on:</b> '.date('d.m.y G:i:s').$user."\n";
        $output_string .= "<pre>\n";
        $captured      = preg_split("/\r?\n/", $input_string);
        if ($captured !== false) {
            foreach ($captured as $line) {
                $output_string .= preg_replace(
                        "/(\s+)\)$/",
                        '$1)</span>',
                        preg_replace_callback("/(\s+)\($/", $this->n_div(...), $line))."\n";
            }
        }
        $output_string .= "</pre>\n";
        //Format the output
        $output_string = preg_replace("/\[(\d*)\]/i", '[<span class="ind">$1</span>]', $output_string);
        if (!is_string($output_string)) {
            return $input_string;
        }
        return str_replace(['=> Array', ':protected', ':private', '=>'], ['=&gt; <span class="arr">Array</span>', ':<span class="pro">protected</span>', ':<span class="pri">private</span>', '<span class="ass">=&gt;</span>'], $output_string);
    }

    /**
     * @param array{1: string} $matches
     * @return string
     */
    private function n_div(array $matches): string
    {
        $this->num++;
        return $matches[1].'<a id=a'.$this->num.' href="javascript: toggleDiv('.$this->num.')">+</a><span id=d'.$this->num.' style="display:none">(';
    }
}
