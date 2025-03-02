<?php

namespace Drutiny\Plugin;

enum Question: int
{
    case DEFAULT = 0;
    case CHOICE = 1;
    case CONFIRMATION = 2;
}
