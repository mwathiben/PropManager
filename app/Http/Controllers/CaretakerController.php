<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Caretaker\CaretakerAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Landlord-side caretaker management. The team UI (Operations → Team)
 * lists caretakers via users.landlord_id; removal severs that link +
 * detaches the caretaker from the landlord's buildings.
 */
class CaretakerController extends Controller
{
    public function __construct(
        private CaretakerAssignmentService $assignments,
    ) {}

    public function destroy(Request $request, User $caretaker): RedirectResponse
    {
        $landlordId = $this->landlordIdFor($request);

        // 404 (not 403) so a foreign / non-caretaker id reveals nothing.
        abort_unless(
            $caretaker->role === 'caretaker' && (int) $caretaker->landlord_id === $landlordId,
            404,
        );

        $this->assignments->removeFromLandlord($caretaker, $landlordId);

        return redirect()->route('operations.hub', ['tab' => 'team'])
            ->with('success', 'Caretaker removed.');
    }

    private function landlordIdFor(Request $request): int
    {
        $user = $request->user();

        return $user->effectiveScopeId();
    }
}
