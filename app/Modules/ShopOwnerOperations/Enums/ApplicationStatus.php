<?php

namespace App\Modules\ShopOwnerOperations\Enums;

enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
