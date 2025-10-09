<?php

namespace byteShard\Action\Ribbon;

use byteShard\Cell;
use byteShard\Enum\HttpResponseState;
use byteShard\ID\CellIDElement;
use byteShard\ID\ID;
use byteShard\ID\PatternIDElement;
use byteShard\ID\TabIDElement;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Action\CellActionResult;

class AttachPopup extends Action
{
    private string $cell;

    /**
     * SetCellHeader constructor.
     */
    public function __construct(string $cell, private readonly string $contentClass, private readonly int $width = 400, private readonly int $height = 300)
    {
        parent::__construct();
        $this->cell = Cell::getContentCellName($cell);
    }

    protected function runAction(): ActionResultInterface
    {
        $content = [];
        $cells        = $this->getCells([$this->cell]);
        foreach ($cells as $cell) {
            $contentClass = new $this->contentClass($cell);
            $id           = $cell->getNewId();
            $patternId    = 'a';
            if ($this->contentClass === '' || !str_starts_with(strtolower($this->contentClass), 'app\\cell')) {
                $newId = ID::factory(new TabIDElement($id->getTabId()), new CellIDElement($this->contentClass), new PatternIDElement($patternId));
            } else {
                $newId = ID::factory(new TabIDElement($id->getTabId()), new CellIDElement(substr($this->contentClass, 9)), new PatternIDElement($patternId));
            }
            $nonce = $cell->getNonce();
            if (!empty($nonce)) {
                $nonce = base64_encode($nonce);
            } else {
                $nonce = '';
            }
            $content = [
                'content' => [$contentClass->getCellContent(false)->content[0]],
                'type'    => 'DHTMLXPopup',
                'state'   => HttpResponseState::SUCCESS->value,
                'setup'   => [
                    'xid'       => $newId->getEncryptedCellId(),
                    'cn'        => $nonce,
                    'EID'       => $newId->getEncryptedCellIdForEvent(),
                    'patternId' => $patternId,
                    'dimension' => [
                        'width'  => $this->width,
                        'height' => $this->height
                    ]
                ],
                'update'  => [],
                'events'  => [
                    'onHide' => 'destroyOnHide'
                ]
            ];
        }
        $result = new CellActionResult('rb');
        return $result->addCellCommand([$this->cell], 'attachPopup', $content);
    }
}