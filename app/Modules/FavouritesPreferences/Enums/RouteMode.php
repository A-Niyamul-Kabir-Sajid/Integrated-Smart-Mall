<?php

namespace App\Modules\FavouritesPreferences\Enums;

/**
 * USER_PREFERENCE.route_mode: how MapsNavigation weighs routes for this user.
 */
enum RouteMode: string
{
    case ShortestDistance = 'shortest_distance';
    case Fastest = 'fastest';
    case AccessibleDistance = 'accessible_distance';
}
