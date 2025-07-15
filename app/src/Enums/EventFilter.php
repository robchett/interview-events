<?php

namespace App\Enums;

enum EventFilter: string
{

    case startFrom = 'startFrom';
    case startTo = 'startTo';
    case endFrom = 'endFrom';
    case endTo = 'endTo';

}
