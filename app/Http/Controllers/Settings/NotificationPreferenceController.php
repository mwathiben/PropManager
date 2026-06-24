<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Phase-35 PLATFORM-NOTIF-2: landlord self-serve write endpoint.
 *
 *   POST /api/notifications/preferences — toggle one type/channel
 *   GET  /api/notifications/preferences — read full matrix
 *
 * Transactional types are LOCKED — billing receipts + security
 * alerts must remain on regardless. The endpoint rejects requests
 * to flip them off (422).
 */
class NotificationPreferenceController extends Controller
{
    public const TOGGLEABLE_TYPES = [
        'rent_reminder',
        'arrears_notice',
        'rent_hike',
        'lease_expiry',
        'lease_renewal',
        'maintenance_notice',
        'general',
        'eviction_notice',
        'caretaker_invitation',
        'tenant_invitation',
        'lifecycle',
    ];

    public const TRANSACTIONAL_LOCKED_TYPES = [
        'invoice',
        'receipt',
    ];

    public const CHANNELS = ['email', 'sms', 'whatsapp', 'push', 'in_app'];

    /**
     * Phase-37 PWA-FRONTEND-ADMIN-1: Inertia render of the landlord
     * notification matrix at /settings/notifications. Server-renders
     * the same payload show() emits as JSON so the page mounts with
     * zero extra round-trip.
     */
    public function page(Request $request): InertiaResponse
    {
        $user = $request->user();
        $landlordId = $user->effectiveScopeId();
        $pref = NotificationPreference::getOrCreate($user->id, (int) $landlordId);

        return Inertia::render('Settings/Notifications', [
            'preferences' => $pref->only(array_merge(
                array_map(fn ($t) => $t.'_enabled', array_merge(self::TOGGLEABLE_TYPES, self::TRANSACTIONAL_LOCKED_TYPES)),
                array_map(fn ($c) => $c.'_enabled', self::CHANNELS),
            )),
            'transactional_locked' => self::TRANSACTIONAL_LOCKED_TYPES,
            'toggleable_types' => self::TOGGLEABLE_TYPES,
            'channels' => self::CHANNELS,
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $landlordId = $user->effectiveScopeId();
        $pref = NotificationPreference::getOrCreate($user->id, (int) $landlordId);

        return response()->json([
            'preferences' => $pref->only(array_merge(
                array_map(fn ($t) => $t.'_enabled', array_merge(self::TOGGLEABLE_TYPES, self::TRANSACTIONAL_LOCKED_TYPES)),
                array_map(fn ($c) => $c.'_enabled', self::CHANNELS),
            )),
            'transactional_locked' => self::TRANSACTIONAL_LOCKED_TYPES,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'channel' => 'nullable|string',
            'enabled' => 'required|boolean',
        ]);

        $type = $validated['type'];
        $channel = $validated['channel'] ?? null;
        $enabled = (bool) $validated['enabled'];

        if (in_array($type, self::TRANSACTIONAL_LOCKED_TYPES, true) && ! $enabled) {
            return response()->json([
                'error' => 'transactional_locked',
                'message' => "Cannot disable transactional type '{$type}'.",
            ], 422);
        }

        if (! in_array($type, array_merge(self::TOGGLEABLE_TYPES, self::TRANSACTIONAL_LOCKED_TYPES), true)) {
            return response()->json([
                'error' => 'unknown_type',
                'message' => "Unknown notification type '{$type}'.",
            ], 422);
        }

        if ($channel !== null && ! in_array($channel, self::CHANNELS, true)) {
            return response()->json([
                'error' => 'unknown_channel',
                'message' => "Unknown channel '{$channel}'.",
            ], 422);
        }

        $user = $request->user();
        $landlordId = $user->effectiveScopeId();
        $pref = NotificationPreference::getOrCreate($user->id, (int) $landlordId);

        if ($channel === null) {
            $pref->{$type.'_enabled'} = $enabled;
        } else {
            $pref->{$channel.'_enabled'} = $enabled;
        }
        $pref->save();

        return response()->json([
            'updated' => true,
            'type' => $type,
            'channel' => $channel,
            'enabled' => $enabled,
        ]);
    }
}
