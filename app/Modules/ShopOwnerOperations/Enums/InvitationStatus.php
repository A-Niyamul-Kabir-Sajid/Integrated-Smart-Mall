<?php

namespace App\Modules\ShopOwnerOperations\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
}
