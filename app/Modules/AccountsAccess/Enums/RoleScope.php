<?php

namespace App\Modules\AccountsAccess\Enums;

/**
 * ROLE.scope_type: where a role applies. Platform roles are held directly
 * (USER_ROLE); mall and shop roles only through a membership for one mall or shop.
 */
enum RoleScope: string
{
    case Platform = 'platform';
    case Mall = 'mall';
    case Shop = 'shop';
}
