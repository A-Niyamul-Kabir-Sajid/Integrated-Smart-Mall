<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\SupportStatus;
use App\Modules\ShopOwnerOperations\Models\SupportThread;
use App\Modules\ShopOwnerOperations\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The shop's support inbox. Routes require the support area for the shop in the URL, and a
 * thread of another shop is not found through it.
 */
class SupportController extends Controller
{
    public function __construct(private SupportService $support) {}

    public function index(Request $request, Shop $shop): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(SupportStatus::class)]])['status'] ?? null;

        return view('shop-owner.support.index', [
            'shop' => $shop,
            'status' => $status,
            'threads' => $this->support->listForShop($request->user(), $shop, $status === null ? null : SupportStatus::from($status)),
            'statuses' => SupportStatus::cases(),
        ]);
    }

    public function show(Shop $shop, SupportThread $shopThread): View
    {
        return view('shop-owner.support.show', ['shop' => $shop, 'thread' => $shopThread->load(['messages.author:user_id,display_name', 'customer:user_id,display_name'])]);
    }

    public function reply(Request $request, Shop $shop, SupportThread $shopThread): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:'.SupportService::BODY_MAX]]);

        $this->support->staffReply($request->user(), $shopThread->setRelation('shop', $shop), $data['body']);

        return back()->with('status', __('Your reply was sent.'));
    }

    public function close(Request $request, Shop $shop, SupportThread $shopThread): RedirectResponse
    {
        $this->support->close($request->user(), $shopThread->setRelation('shop', $shop));

        return back()->with('status', __('The conversation was closed.'));
    }
}
