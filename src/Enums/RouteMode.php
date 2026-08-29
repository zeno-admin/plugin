<?php

namespace Zeno\Plugin\Enums;

enum RouteMode: string
{
    case Authenticated = 'authenticated';
    case Authorized = 'authorized';
}
