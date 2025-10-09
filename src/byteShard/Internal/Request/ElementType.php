<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Request;

enum ElementType: string
{
    case DhxLayout = 'dhxLayout';
    case DhxTab = 'dhxTab';
    case DhxTree = 'dhxTree';
    case DhxGrid = 'dhxGrid';
    case DhxToolbar = 'dhxToolbar';
    case DhxForm = 'dhxForm';
    case Popup = 'popup';
    case BsPoll = 'bsPoll';
    case DhxScheduler = 'dhxScheduler';
    case DhxRibbon = 'dhxRibbon';
}
