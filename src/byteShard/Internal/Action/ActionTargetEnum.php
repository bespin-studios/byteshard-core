<?php

namespace byteShard\Internal\Action;

enum ActionTargetEnum: string
{
    case Toolbar = 'tb';
    case Ribbon = 'rb';
    case Cell = 'LCell';
    case Layout = 'layout';
    case Tab = 'tab';
    case Window = 'window';
    case TabBar = 'tabBar';
    case Popup = 'popup';
    case Message = 'message';
    case Global = 'global';

}
