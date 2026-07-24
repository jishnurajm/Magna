<?php

declare(strict_types=1);

namespace Magna\Management\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Magna\Audit\AuditLog;
use Magna\Settings\GeneralSettings;
use Magna\Settings\MailSettings;
use Magna\Settings\StorageSettings;

class SettingController extends ManagementController
{
    public function index(): JsonResponse
    {
        Gate::authorize('settings.view');

        return response()->json([
            'data' => [
                'general' => GeneralSettings::get()->toArray(maskSecrets: true),
                'mail' => MailSettings::get()->toArray(maskSecrets: true),
                'storage' => StorageSettings::get()->toArray(maskSecrets: true),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize('settings.manage');

        $group = $request->string('group')->value();
        /** @var array<string, mixed> $values */
        $values = (array) $request->input('values', []);

        $settings = match ($group) {
            'general' => GeneralSettings::get(),
            'mail' => MailSettings::get(),
            'storage' => StorageSettings::get(),
            default => null,
        };

        if ($settings === null) {
            return response()->json(['message' => "Unknown settings group '{$group}'."], 422);
        }

        $before = $settings->toArray(maskSecrets: true);

        foreach ($values as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->{$key} = $value;
            }
        }

        $settings->save();

        AuditLog::record(
            action: 'settings.changed',
            actorId: $this->actorId(),
            ip: $request->ip(),
            before: $before,
            after: $settings->toArray(maskSecrets: true),
        );

        return response()->json([
            'data' => $settings->toArray(maskSecrets: true),
        ]);
    }
}
