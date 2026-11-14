<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallOwnerAdmin\Services\AnnouncementService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\View\View;

/**
 * The public page of a mall's published notices and events.
 */
class PublicAnnouncementController extends Controller
{
    public function index(Mall $publicMall, AnnouncementService $announcements): View
    {
        return view('customer.announcements.index', ['mall' => $publicMall, ...$announcements->publicFor($publicMall)]);
    }
}
