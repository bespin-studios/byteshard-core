<?php

namespace byteShard\ID;

use byteShard\Cell;
use byteShard\Session;

class UploadId
{

    /**
     * @param Cell $cell
     * @param string $encryptedClientName the encrypted upload_control_name
     * @param array $fileTypeArray
     * @param string $targetFilename
     * @param string $targetPath
     * @param bool $clearAfterUpload
     * @return null|string
     */
    public static function getUploadId(Cell $cell, string $encryptedClientName, array $fileTypeArray = [], string $targetFilename = '', string $targetPath = '', bool $clearAfterUpload = false): ?string
    {
        $message = [
            '!#c' => $cell->getContentClass(),
            '!#o' => $encryptedClientName,
            '!#u' => $clearAfterUpload,
            '!#n' => $targetFilename,
            '!#p' => $targetPath,
            '!#i' => $cell->getNewId()->getEncodedCellId()
        ];
        if (!empty($fileTypeArray)) {
            $message['!#f'] = $fileTypeArray;
        }
        $message = (object)array_filter($message);
        return urlencode(Session::encrypt(json_encode($message), $cell->getNonce()));
    }
}