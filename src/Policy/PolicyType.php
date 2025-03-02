<?php

namespace Drutiny\Policy;

enum PolicyType: string
{
    case AUDIT = 'audit';
    case DATA = 'data';
}
