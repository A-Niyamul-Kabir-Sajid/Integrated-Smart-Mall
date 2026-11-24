<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\ApiKeyIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administrators create and revoke the keys other apps use for /api/v1.
 */
class ApiKeyController extends Controller
{
    public function __construct(private ApiKeyIssuer $issuer) {}

    public function index(): View
    {
        return view('admin.api-keys.index', [
            'keys' => ApiKey::query()->latest()->get(),
            'abilities' => ApiKey::ABILITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(array_keys(ApiKey::ABILITIES))],
            'expires_in_days' => ['nullable', 'integer', 'between:1,3650'],
        ]);

        [$key, $plain] = $this->issuer->issue(
            $data['name'],
            $data['abilities'],
            isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null,
        );

        return redirect()->route('admin.api-keys.index')
            ->with('new_key', $plain)
            ->with('status', "Key “{$key->name}” created. Copy it now: it is shown only once.");
    }

    public function revoke(ApiKey $apiKey): RedirectResponse
    {
        $this->issuer->revoke($apiKey);

        return back()->with('status', "Key “{$apiKey->name}” revoked. Apps using it are refused from now on.");
    }
}
