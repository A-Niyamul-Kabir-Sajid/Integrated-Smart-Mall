<?php

namespace App\Modules\ChatbotRag\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ChatbotRag\Services\Assistant\NavigationHandoff;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\RedirectResponse;

/**
 * Opens the destination of a "directions" link in an answer. The destination is looked up again
 * here: one that does not exist, is not public or belongs to another mall is not found.
 */
class AssistantHandoffController extends Controller
{
    public function show(Mall $publicMall, string $type, string $id, NavigationHandoff $handoff): RedirectResponse
    {
        $url = $handoff->destinationUrl($publicMall, $type, $id);

        abort_if($url === null, 404);

        return redirect()->to($url);
    }
}
