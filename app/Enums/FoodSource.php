<?php

namespace App\Enums;

enum FoodSource: string
{
    case OpenFoodFacts = 'off';
    case Usda = 'usda';
    case Custom = 'custom';
}
