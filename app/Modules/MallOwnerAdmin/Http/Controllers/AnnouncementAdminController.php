<?php

namespace App\Modules\MallOwnerAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MallOwnerAdmin\Enums\AnnouncementKind;
use App\Modules\MallOwnerAdmin\Enums\AnnouncementStatus;
use App\Modules\MallOwnerAdmin\Models\Announcement;
use App\Modules\MallOwnerAdmin\Services\AnnouncementService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Notices and events of one mall. Routes require administer-mall for the mall in the URL; an
 * announcement of another mall is not found through it.
 */
class AnnouncementAdminController extends Controller
{
    public function __construct(private AnnouncementService $announcements) {}

    public function index(Request $request, Mall $mall): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::enum(AnnouncementStatus::class)]]);

        return view('mall-admin.announcements.index', [
            'mall' => $mall,
            'announcements' => $this->announcements->listForMall($request->user(), $mall, $filters),
            'statuses' => AnnouncementStatus::cases(),
            'status' => $filters['status'] ?? null,
        ]);
    }

    public function create(Mall $mall): View
    {
        return view('mall-admin.announcements.form', ['mall' => $mall, 'announcement' => new Announcement(['kind' => AnnouncementKind::Notice]), 'kinds' => AnnouncementKind::cases()]);
    }

    public function store(Request $request, Mall $mall): RedirectResponse
    {
        $announcement = $this->announcements->create($request->user(), $mall, $this->validated($request), $request->boolean('publish'));

        return redirect()->route('mall-admin.announcements.index', $mall)->with('status', $announcement->status === AnnouncementStatus::Published ? __('The announcement is published.') : __('The draft was saved.'));
    }

    public function edit(Mall $mall, Announcement $adminAnnouncement): View
    {
        return view('mall-admin.announcements.form', ['mall' => $mall, 'announcement' => $adminAnnouncement, 'kinds' => AnnouncementKind::cases()]);
    }

    public function update(Request $request, Mall $mall, Announcement $adminAnnouncement): RedirectResponse
    {
        $this->announcements->update($request->user(), $adminAnnouncement, $this->validated($request));

        return redirect()->route('mall-admin.announcements.index', $mall)->with('status', __('The announcement was saved.'));
    }

    public function publish(Request $request, Mall $mall, Announcement $adminAnnouncement): RedirectResponse
    {
        $this->announcements->publish($request->user(), $adminAnnouncement);

        return back()->with('status', __('The announcement is published.'));
    }

    public function unpublish(Request $request, Mall $mall, Announcement $adminAnnouncement): RedirectResponse
    {
        $this->announcements->unpublish($request->user(), $adminAnnouncement);

        return back()->with('status', __('The announcement is a draft again.'));
    }

    public function archive(Request $request, Mall $mall, Announcement $adminAnnouncement): RedirectResponse
    {
        $this->announcements->archive($request->user(), $adminAnnouncement);

        return back()->with('status', __('The announcement was archived.'));
    }

    /**
     * @return array{kind: string, title: string, body: string, location_note?: string|null, starts_at?: string|null, ends_at?: string|null}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', Rule::enum(AnnouncementKind::class)],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'location_note' => ['nullable', 'string', 'max:160'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);
    }
}
