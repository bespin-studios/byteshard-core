<?php

namespace byteShard\Internal\Permission;

use byteShard\Cell;
use byteShard\Enum\ContentType;
use byteShard\Internal\Struct\ClientCell;
use byteShard\Internal\Struct\ContentComponent;
use byteShard\Layout\Enum\Pattern;
use byteShard\Locale;
use SimpleXMLElement;

class NoPermission
{
    public static function content(?string $contentClass, string $appName = ''): ContentComponent
    {

        if ($contentClass !== null) {
            $form = new $contentClass(new Cell());
            $formContent = $form->getCellContent();
            if ($formContent instanceof ClientCell) {
                $content = $formContent->content;
            }
        }
        if (!isset($content)) {
            $formContentXml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><items/>');
            $label          = $formContentXml->addChild('item');
            $label->addAttribute('type', 'label');
            $label->addAttribute('name', 'NoPermission');
            $label->addAttribute('label', sprintf(Locale::get('byteShard.environment.cell.label.noPermission'), $appName));
            $content = [
                new ContentComponent(
                    type   : ContentType::DhtmlxForm,
                    content: $formContentXml->asXML(),
                )
            ];
        }

        return new ContentComponent(
            type   : ContentType::DhtmlxLayout,
            content: [
                new ContentComponent(
                    type   : ContentType::DhtmlxLayoutCell,
                    content: $content,
                    setup  : [
                        'label'     => Locale::get('byteShard.environment.tab.label.noPermission'),
                        'patternId' => 'a'
                    ],
                )
            ],
            setup  : ['pattern' => Pattern::PATTERN_1C]
        );
    }
}