<?php

namespace App\Modules\ShopOwnerOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\SupportKind;
use App\Modules\ShopOwnerOperations\Models\SupportThread;
use App\Modules\ShopOwnerOperations\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A customer writes to a listed shop and follows their own conversations. Another
 * customer's thread is not found.
 */
class CustomerSupportController extends Controller
{
    public function __construct(private SupportService $support) {}

    public function create(Mall $publicMall, Shop $publicShop): View
    {
        return view('account.support.create', ['mall' => $publicMall, 'shop' => $publicShop, 'kinds' => SupportKind::cases()]);
    }

    public function store(Request $request, Mall $publicMall, Shop $publicShop): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(SupportKind::class)],
            'body' => ['required', 'string', 'max:'.SupportService::BODY_MAX],
        ]);

        $thread = $this->support->open($request->user(), $publicShop, $data);

        return redirect()->route('support.show', $thread)->with('status', __('Your message was sent to :shop.', ['shop' => $publicShop->name]));
    }

    public function index(Request $request): View
    {
        return view('account.support.index', ['threads' => $this->support->listForCustomer($request->user())]);
    }

    public function show(SupportThread $customerThread): View
    {
        return view('account.support.show', ['thread' => $customerThread->load(['messages.author:user_id,display_name', 'shop:shop_id,name'])]);
    }

    public function reply(Request $request, SupportThread $customerThread): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:'.SupportService::BODY_MAX]]);

        $this->support->customerReply($request->user(), $customerThread, $data['body']);

        return back()->with('status', __('Your reply was sent.'));
    }

    public function close(Request $request, SupportThread $customerThread): RedirectResponse
    {
        $this->support->close($request->user(), $customerThread);

        return back()->with('status', __('The conversation was closed.'));
    }
}
