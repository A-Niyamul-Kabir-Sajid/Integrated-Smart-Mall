<?php

namespace App\Core\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by a service when a request is well-formed but breaks a business rule,
 * for example assigning a shop unit on a floor of another mall.
 *
 * Browser requests are sent back with the message in the "business" error bag;
 * JSON requests receive 422 with a stable machine-readable rule code.
 */
class BusinessRuleViolation extends RuntimeException
{
    public function __construct(public readonly string $rule, string $message)
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'rule' => $this->rule,
            ], 422);
        }

        return back()
            ->withInput($request->except(['password', 'password_confirmation', 'current_password']))
            ->withErrors(['business' => $this->getMessage()]);
    }
}
