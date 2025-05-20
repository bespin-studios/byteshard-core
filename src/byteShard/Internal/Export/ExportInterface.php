<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Export;

use byteShard\Exception;
use byteShard\File\FileInterface;
use byteShard\Internal\ExportHandler;
use byteShard\Internal\Struct\ClientData;

interface ExportInterface
{
    public function getXlsExport(?string $contentId): array;

    public function setProcessedClientData(?ClientData $clientData): void;

    /**
     * @throws Exception|\Exception
     */
    public function defineDownloadParent(): FileInterface;

    public function getExportHandler(ExportHandler $exportHandler): ?HandlerInterface;
}