<?php

namespace App\Http\Controllers;

use App\Models\TenantActivity;
use App\Models\TenantNote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class TenantNoteController extends Controller
{
    public function store(Request $request, User $tenant)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($tenant->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'is_pinned' => 'boolean',
        ]);

        TenantNote::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'content' => $validated['content'],
            'is_pinned' => $validated['is_pinned'] ?? false,
        ]);

        TenantActivity::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $tenant->id,
            'performed_by' => $user->id,
            'type' => 'note_added',
            'description' => 'A note was added to the tenant profile.',
        ]);

        return Redirect::back()->with('success', 'Note added.');
    }

    public function update(Request $request, TenantNote $note)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($note->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'is_pinned' => 'boolean',
        ]);

        $note->update($validated);

        return Redirect::back()->with('success', 'Note updated.');
    }

    public function destroy(TenantNote $note)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($note->landlord_id !== $landlordId) {
            abort(403);
        }

        $note->delete();

        return Redirect::back()->with('success', 'Note deleted.');
    }
}
