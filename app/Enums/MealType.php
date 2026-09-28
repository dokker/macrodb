<?php

namespace App\Enums;

enum MealType: string
{
    case Breakfast = 'reggeli';
    case Lunch = 'ebéd';
    case Dinner = 'vacsora';
    case Snack = 'snack';
}
