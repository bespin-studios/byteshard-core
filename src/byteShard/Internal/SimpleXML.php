<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Debug;
use SimpleXMLElement;

class SimpleXML
{
    private static bool $decode = false;

    public static function initializeDecode(): void
    {
        if (class_exists('\config')) {
            $config = new \config();
            if ($config instanceof Config) {
                self::$decode = $config->useDecodeUtf8();
            }
        }
    }

    public static function addChildCData(?SimpleXMLElement $xml, string $name, string $cDataText): ?SimpleXMLElement
    {
        $child = $xml?->addChild($name);
        if ($child !== null) {
            $node         = dom_import_simplexml($child);
            $cdataSection = $node->ownerDocument?->createCDATASection($cDataText);
            if ($cdataSection !== null) {
                $node->appendChild($cdataSection);
            }
        }
        return $child;
    }

    public static function addAttribute(?SimpleXMLElement $xml, string $qualifiedName, ?string $value = null, ?string $namespace = null): void
    {
        if ($xml === null) {
            return;
        }
        $qualifiedName = htmlspecialchars(htmlspecialchars_decode($qualifiedName, 16), 16, 'UTF-8'); //16 === ENT_XML1
        if ($value === null) {
            $value = '';
        } elseif ($value !== '') {
            $value = htmlspecialchars_decode($value);
            if (self::$decode === true) {
                if (!preg_match("//u", $value)) {
                    $value = mb_convert_encoding($value, 'UTF-8');
                }
            }
        }
        $xml->addAttribute($qualifiedName, $value, $namespace);
    }

    public static function addChild(?SimpleXMLElement $xml, string $qualifiedName, ?string $value = null, ?string $namespace = null): ?SimpleXMLElement
    {
        if ($xml === null) {
            return null;
        }
        $qualifiedName = htmlspecialchars(htmlspecialchars_decode($qualifiedName, 16), 16, 'UTF-8'); //16 === ENT_XML1
        if ($value !== null) {
            $value = htmlspecialchars(htmlspecialchars_decode($value, 16), 16, 'UTF-8');
        }
        return $xml->addChild($qualifiedName, $value, $namespace);
    }

    public static function appendXML(?SimpleXMLElement $appendTo, ?SimpleXMLElement $append, bool $incRootNode = true): void
    {
        if ($appendTo === null || $append === null) {
            return;
        }
        if (trim((string)$append) === '' || $append->count() > 0) {
            // create a child node if root node explicitly requested or if there is a parent of the current node
            $xml = ($incRootNode || $append->xpath('parent::*')) ? $appendTo->addChild($append->getName(), (trim((string)$append) === '') ? null : (string)$append) : $appendTo;
            if ($xml !== null) {
                foreach ($append->children() as $child) {
                    SimpleXML::appendXML($xml, $child);
                }
            }
        } else {
            $xml = $appendTo->addChild($append->getName(), (string)$append);
        }
        if ($xml !== null) {
            $attributes = $append->attributes();
            if ($attributes !== null) {
                foreach ($attributes as $index => $attribute) {
                    $xml->addAttribute($index, $attribute);
                }
            }
        }
    }

    public static function asString(?SimpleXMLElement $xml): string
    {
        if ($xml === null) {
            return '';
        }
        $result = $xml->asXML();
        if ($result === false) {
            Debug::error('Could not output SimpleXMLElement as string (14025001)');
            return '';
        }
        return $result;
    }
}