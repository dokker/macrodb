<?php

namespace App\Enums;

enum InputMethod: string
{
    case Text = 'szöveg';
    case Barcode = 'vonalkód';
    case Photo = 'fotó';
}
