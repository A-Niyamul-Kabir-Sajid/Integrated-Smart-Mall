<?php

namespace App\Modules\SharedPlatform\Enums;

/**
 * Authorization abilities every module may check with Gate::allows(Ability::X, $model)
 * or the route middleware ->can(Ability::X->value, 'model'). AccountsAccess defines
 * them from platform roles and mall/shop memberships, so modules never need to
 * know how access is granted.
 */
enum Ability: string
{
    /** Platform operators: manage malls and platform settings. */
    case AdministerPlatform = 'administer-platform';

    /** Mall administrators of the given mall (and platform operators). */
    case AdministerMall = 'administer-mall';

    /** Owners of the given shop (and platform operators). */
    case ManageShop = 'manage-shop';

    /** Owners and staff of the given shop (and platform operators). */
    case WorkInShop = 'work-in-shop';
}
