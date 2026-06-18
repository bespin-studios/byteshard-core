<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Request;

enum EventType: string
{
    case OnPanelResizeFinish = 'onPanelResizeFinish';
    case OnCollapse = 'onCollapse';
    case OnExpand = 'onExpand';
    case OnSelect = 'onSelect';
    case OnTabClose = 'onTabClose';
    case OnDblClick = 'onDblClick';
    case OnRowSelect = 'onRowSelect';
    case OnCellEdit = 'onCellEdit';
    case OnClick = 'onClick';
    case OnEnter = 'onEnter';
    case OnStateChange = 'onStateChange';
    case OnButtonClick = 'onButtonClick';
    case OnChange = 'onChange';
    case OnInputChange = 'onInputChange';
    case OnInfo = 'onInfo';
    case OnPopupClose = 'onPopupClose';
    case OnDrop = 'onDrop';
    case OnEmptyClick = 'onEmptyClick';
    case OnScrollForward = 'onScrollForward';
    case OnScrollBackward = 'onScrollBackward';
    case OnPoll = 'poll';
    case OnReady = 'onReady';
    case OnCellInit = 'onCellInit';
    case OnContainerInit = 'onContainerInit';
    case OnGridLink = 'onGridLink';
    case OnRowAction = 'onRowAction';

    case OnJSLinkClicked = 'onJSLinkClicked';
}
