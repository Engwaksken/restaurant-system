<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Backups\RestaurantBackupService;
use App\Services\Settings\RestaurantSettings;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Restaurant data backups: create, download, delete and schedule. */
class BackupController extends Controller
{
    public function index(Request $request, RestaurantBackupService $backups, RestaurantSettings $settings): View
    {
        $user = $this->authorizeBackups($request);
        $list = $backups->list($user->restaurant_id);
        $values = $settings->group($user->restaurant_id, 'backups');

        return view('admin.settings.backups', [
            'backups' => $list,
            'values' => $values,
            'group' => SettingsRegistry::group('backups'),
            'pageStats' => [
                ['label' => 'Backups stored', 'value' => number_format($list->count()), 'icon' => 'fas fa-box-archive', 'tone' => 'brand', 'hint' => 'Keeping the latest ' . (int) $values['keep']],
                ['label' => 'Latest backup', 'value' => $list->first() ? $list->first()['created_at']->diffForHumans() : 'Never', 'icon' => 'fas fa-clock-rotate-left', 'tone' => $list->first() && $list->first()['created_at']->gt(now()->subDays(8)) ? 'success' : 'warning'],
                ['label' => 'Automatic backups', 'value' => SettingsRegistry::group('backups')['fields']['frequency']['options'][$values['frequency']] ?? 'Off', 'icon' => 'fas fa-calendar-check', 'tone' => $values['frequency'] === 'off' ? 'neutral' : 'info'],
                ['label' => 'Total size', 'value' => $this->humanSize((int) $list->sum('size')), 'icon' => 'fas fa-hard-drive', 'tone' => 'violet'],
            ],
        ]);
    }

    public function store(Request $request, RestaurantBackupService $backups, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeBackups($request);
        try {
            $name = $backups->create(Restaurant::query()->findOrFail($user->restaurant_id));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The backup could not be created. ' . $exception->getMessage());
        }
        $logger->log('settings.backup.created', null, ['file' => $name]);

        return back()->with('success', 'Backup created. You can download it below.');
    }

    public function download(Request $request, string $file, RestaurantBackupService $backups, ActivityLogger $logger): BinaryFileResponse
    {
        $user = $this->authorizeBackups($request);
        $path = $backups->path($user->restaurant_id, $file);
        abort_unless($path !== null, 404);
        $logger->log('settings.backup.downloaded', null, ['file' => $file]);

        return response()->download($path, $file, ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store']);
    }

    public function destroy(Request $request, string $file, RestaurantBackupService $backups, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeBackups($request);
        abort_unless($backups->delete($user->restaurant_id, $file), 404);
        $logger->log('settings.backup.deleted', null, ['file' => $file]);

        return back()->with('success', 'Backup deleted.');
    }

    public function updateSchedule(Request $request, RestaurantSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeBackups($request);
        $data = $request->validate(SettingsRegistry::rules('backups'));
        $settings->save($user->restaurant_id, 'backups', $data);
        $logger->log('settings.backups.updated', null, ['frequency' => $data['frequency'] ?? null, 'keep' => $data['keep'] ?? null]);

        return back()->with('success', 'Backup schedule saved.');
    }

    private function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1) . ' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 1) . ' KB',
            default => $bytes . ' B',
        };
    }

    private function authorizeBackups(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_if($user->branch_id !== null, 403, 'Branch-assigned staff cannot manage backups.');
        abort_unless($user->can('settings.system.manage') || $user->can('admin'), 403);

        return $user;
    }
}
