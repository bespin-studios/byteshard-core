<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Cell;
use byteShard\Event\OnUploadInterface;
use byteShard\Form\Control\Hidden;
use byteShard\ID\ID;
use byteShard\Internal\Form\FormObject\Proxy;
use byteShard\Exception;
use byteShard\Internal\Sanitizer\File;
use byteShard\Enum;
use byteShard\Internal\Struct\ClientData;
use byteShard\Session;
use Throwable;

class Upload
{
    /**
     * @throws Exception
     */
    public function getClientResult(?string $type, ?array $files): array
    {
        if ($type === null) {
            $exception = new Exception('No parameter "type" set in GET', 0);
            $exception->setLocaleToken('byteShard.upload.type.notFound');
            throw $exception;
        }
        if ($files === null) {
            $exception = new Exception('unspecified index "file" in $_FILES', 0);
            $exception->setLocaleToken('byteShard.upload.files.hashFileNotFound');
            throw $exception;
        }
        if (array_key_exists('error', $files) && $files['error'] !== 0) {
            $message   = match ($files['error']) {
                1       => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                2       => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                3       => 'The uploaded file was only partially uploaded',
                4       => 'No file was uploaded',
                6       => 'Missing a temporary folder',
                7       => 'Failed to write file to disk',
                8       => 'File upload stopped by extension',
                default => 'unspecified index "error" in $_FILES',
            };
            $token     = match ($files['error']) {
                1       => 'byteShard.upload.file.size.ini',
                2       => 'byteShard.upload.file.size.form',
                3       => 'byteShard.upload.file.partial',
                4       => 'byteShard.upload.file.notUploaded',
                6       => 'byteShard.upload.file.tempMissing',
                7       => 'byteShard.upload.file.failedWrite',
                8       => 'byteShard.upload.file.extension',
                default => 'byteShard.upload.files.hashErrorNotFound',
            };
            $exception = new Exception($message, $files['error']);
            $exception->setLocaleToken($token);
            throw $exception;
        }
        return $this->runUpload(urlencode($type), $files);
    }

    /**
     * @throws \Exception
     */
    private function eventCallback(CellContent&OnUploadInterface $class, Cell $cell, string $objectId, string $fqfn, string $clientName): array
    {
        $cell->setActionId($objectId);
        $clientData = new ClientData();
        $row        = $clientData->addRow();
        $row->addField($objectId, [new \byteShard\Upload\File($fqfn, $clientName)]);
        $class->setProcessedClientData($clientData);
        $eventResult   = $class->onUpload();
        $resultActions = $eventResult->getResultActions($objectId, '');
        $mergeArray    = [];
        $actionData    = [];
        $clearUpload   = false;
        foreach ($resultActions as $action) {
            if (is_a($action, '\\byteShard\\Action\\Form\\ClearUpload')) {
                $clearUpload = true;
            }
            $mergeArray[] = $action->getResult();
        }
        $resultArray          = array_merge_recursive([], ...$mergeArray);
        $resultArray['clear'] = $clearUpload;
        return $resultArray;
    }

    /**
     * @throws \Exception
     */
    private function legacyEventCallback(CellContent $class, string $method, array $fileInfo): ?array
    {
        $result = $class->{$method}($fileInfo);
        if ($result !== null && !is_array($result)) {
            throw new \Exception('invalid return type');
        }
        return $result;
    }

    /**
     * @param string $type
     * @param array $file
     * @return array
     * @throws Exception
     * @throws \Exception
     */
    private function runUpload(string $type, array $file): array
    {
        try {
            $requestData = json_decode(Session::decrypt(urldecode($type)));
        } catch (\Exception) {
            $exception = new Exception('ID not registered in SESSION. Possible CSRF', 14345001);
            $exception->setLocaleToken('byteShard.upload.generic.error');
            $exception->setUploadFileName($file['name']);
            throw $exception;
        }
        try {
            $fileTypes           = $requestData->{'!#f'} ?? [];
            $targetPath          = $requestData->{'!#p'} ?? null;
            $targetFileName      = $requestData->{'!#n'} ?? null;
            $encryptedObjectName = $requestData->{'!#o'};
            $decryptedObjectName = json_decode(Session::decrypt($encryptedObjectName));
            $objectName          = $decryptedObjectName->{'i'};
            $method              = $requestData->{'!#m'} ?? null; // deprecated
            $clearAfterUpdate    = $requestData->{'!#u'} ?? false;
            $className           = $requestData->{'!#c'};
            $cellId              = ID::decryptFinalImplementation($requestData->{'!#i'});
        } catch (\Exception) {
            $exception = new Exception('Error with accessing object properties', 14345002);
            $exception->setLocaleToken('byteShard.upload.generic.error');
            $exception->setUploadFileName($file['name']);
            throw $exception;
        }

        if (!empty($fileTypes)) {
            $sanitizer = new File($file, $fileTypes, $targetPath, $targetFileName);
            if ($sanitizer->hasErrors() === false) {
                $fileInfo = [
                    'path' => $sanitizer->getServerFilepath(),
                    'name' => $sanitizer->getServerFilename(),
                    'fqfn' => $sanitizer->getServerFileFQFN(),
                    'id'   => $objectName
                ];
                try {
                    $cell  = Session::getCell($cellId);
                    $class = new $className($cell, null);
                    $extra = null;
                    if (isset(class_implements($className)[OnUploadInterface::class])) {
                        $extra = $this->eventCallback($class, $cell, $objectName, $sanitizer->getServerFileFQFN(), $file['name']);
                    } elseif ($method !== null && method_exists($class, $method)) {
                        // old implementation
                        $extra = $this->legacyEventCallback($class, $method, $fileInfo);
                    }
                    if (is_array($extra)) {
                        $result['extra'] = $extra;
                    }
                    $result['state']                 = true;
                    $result['name']                  = urlencode(Session::encrypt($sanitizer->getServerFilename()));
                    $result['extra']['state']        = Enum\HttpResponseState::SUCCESS->value;
                    $result['extra']['clear']        = isset($result['extra']['clear']) && $result['extra']['clear'] ? true : $clearAfterUpdate;
                    $result['extra']['uploaderName'] = $encryptedObjectName;
                    $hidden                          = new Hidden(
                        '!#up='.json_encode(
                            [
                                'f' => $sanitizer->getServerFilename(),
                                'p' => $sanitizer->getServerFilepath(),
                                'c' => $file['name']
                            ]), ''
                    );

                    $proxy = new Proxy($hidden, $cell, Enum\AccessType::R, null, $cell->getNonce());
                    $proxy->register($cell);

                    $result['extra']['LCell'][$cell->containerId()][$cell->cellId()]['addItem'] = [
                        'items'    => [
                            $proxy->getJsonArray()
                        ],
                        'position' => null,
                        'offset'   => 0
                    ];
                    if ($clearAfterUpdate === true) {
                        unlink(rtrim(rtrim($sanitizer->getServerFilepath(), '/'), '\\').DIRECTORY_SEPARATOR.$sanitizer->getServerFilename());
                    }
                    return $result;
                } catch (Throwable $e) {
                    $exception = new Exception($e->getMessage(), 14345005, __METHOD__, $e);
                    $exception->setLocaleToken('byteShard.upload.generic.error');
                    $exception->setUploadFileName($file['name']);
                    throw $exception;
                }
            }
            $exception = new Exception('Sanitizer encountered an error', 14345004);
            $exception->setLocaleToken('byteShard.upload.sanitizer.error');
            $exception->setUploadFileName($file['name']);
            throw $exception;
        }
        $exception = new Exception('No allowed file types defined', 14345003);
        $exception->setLocaleToken('byteShard.upload.fileType.notDefined');
        $exception->setUploadFileName($file['name']);
        throw $exception;
    }
}