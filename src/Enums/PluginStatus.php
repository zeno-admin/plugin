<?php

namespace Zeno\Plugin\Enums;

enum PluginStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case Uninstalled = 'uninstalled';
}
