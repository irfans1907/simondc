<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AssetCategoryController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetScanController;
use App\Http\Controllers\AssetSettingsController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileShareController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MonthlyCheckController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PanelPowerController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\RackTypeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SharedWithMeController;
use App\Http\Controllers\TroubleshootController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VlanTagRequestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');

Route::get('email/verified', fn () => Inertia::render('auth/email-verified'))
    ->name('verification.verified');

Route::get('email/status', function () {
    return response()->json(['verified' => auth()->check() && auth()->user()->hasVerifiedEmail()]);
})->middleware('auth');

require __DIR__.'/settings.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    Route::get('files', [FileController::class, 'index'])
        ->middleware('permission:view my files')
        ->name('files.index');

    Route::middleware('permission:manage files')->group(function () {
        Route::post('files', [FileController::class, 'store'])->name('files.store');
        Route::post('files/folder', [FileController::class, 'storeFolder'])->name('files.store-folder');
        Route::post('files/move', [FileController::class, 'move'])->name('files.move');
        Route::post('files/move-to-shared', [FileController::class, 'moveToShared'])->name('files.move-to-shared');
        Route::delete('files/bulk-delete', [FileController::class, 'bulkDestroy'])->name('files.bulk-destroy');
        Route::patch('files/{file}', [FileController::class, 'update'])->name('files.update');
        Route::delete('files/{file}', [FileController::class, 'destroy'])->name('files.destroy');

        Route::post('file-shares', [FileShareController::class, 'store'])->name('file-shares.store');
        Route::patch('file-shares/{fileShare}/permission', [FileShareController::class, 'updatePermission'])->name('file-shares.update-permission');
        Route::delete('file-shares/{fileShare}', [FileShareController::class, 'destroy'])->name('file-shares.destroy');
    });

    Route::get('files/bulk-download', [FileController::class, 'bulkDownload'])
        ->middleware('permission:view my files')
        ->name('files.bulk-download');

    Route::get('files/{file}/download', [FileController::class, 'download'])
        ->middleware('permission:view my files')
        ->name('files.download');

    Route::get('files/{file}/preview', [FileController::class, 'preview'])
        ->middleware('permission:view my files')
        ->name('files.preview');

    Route::get('shared-with-me', [SharedWithMeController::class, 'index'])
        ->middleware('permission:view shared with me')
        ->name('shared-with-me.index');

    Route::post('shared-with-me', [SharedWithMeController::class, 'store'])
        ->middleware('permission:view shared with me')
        ->name('shared-with-me.store');

    Route::post('shared-with-me/folder', [SharedWithMeController::class, 'storeFolder'])
        ->middleware('permission:view shared with me')
        ->name('shared-with-me.store-folder');

    Route::patch('shared-with-me/{file}/rename', [SharedWithMeController::class, 'rename'])
        ->middleware('permission:view shared with me')
        ->name('shared-with-me.rename');

    Route::post('shared-with-me/{file}/copy-to-my-files', [SharedWithMeController::class, 'copyToMyFiles'])
        ->middleware('permission:view shared with me')
        ->name('shared-with-me.copy-to-my-files');

    Route::delete('shared-with-me/{file}', [SharedWithMeController::class, 'destroy'])
        ->middleware('permission:view shared with me')
        ->name('shared-with-me.destroy');

    Route::get('shared-with-me/bulk-download', [SharedWithMeController::class, 'bulkDownload'])
        ->middleware('permission:view shared with me')
        ->name('shared-with-me.bulk-download');

    Route::get('shared-with-me/{file}/download', [SharedWithMeController::class, 'download'])
        ->middleware('permission:view shared with me')
        ->name('shared-with-me.download');

    Route::get('shared-with-me/{file}/preview', [SharedWithMeController::class, 'preview'])
        ->middleware('permission:view shared with me')
        ->name('shared-with-me.preview');

    Route::middleware('permission:manage-users')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::middleware('permission:manage-roles')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::patch('permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
    });

    Route::middleware('permission:manage-activity-log')->group(function () {
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
    });

    Route::middleware('permission:manage-menus')->group(function () {
        Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
        Route::patch('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
    });

    // QR scan pages: login only, no asset permission (Fase 2 decision).
    Route::get('assets/scan', [AssetScanController::class, 'index'])
        ->name('assets.scan');
    Route::get('assets/{asset}/scan', [AssetScanController::class, 'show'])
        ->name('assets.scan.show');

    Route::middleware('permission:manage-asset-management')->group(function () {
        Route::get('assets/export', [AssetController::class, 'export'])->name('assets.export');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::patch('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    });

    Route::middleware('permission:view-asset-management|manage-asset-management')->group(function () {
        Route::get('assets', [AssetController::class, 'index'])
            ->name('assets.index');
        Route::get('assets/overview', [AssetController::class, 'overview'])
            ->name('assets.overview');
        Route::get('assets/categorylist', [AssetCategoryController::class, 'index'])
            ->name('asset-categories.index');
        Route::get('assets/panelpowerlist', [PanelPowerController::class, 'index'])
            ->name('assets.panelpowerlist');
        Route::get('assets/maintenance', [MaintenanceController::class, 'index'])
            ->name('assets.maintenance');
        Route::get('assets/troubleshoot', [TroubleshootController::class, 'index'])
            ->name('assets.troubleshoot');
        Route::get('assets/settings', [AssetSettingsController::class, 'index'])
            ->name('assets.settings');
    });

    Route::middleware('permission:manage-asset-management')->group(function () {
        Route::post('assets/category', [AssetCategoryController::class, 'store'])
            ->name('asset-categories.store');
        Route::delete('assets/category/bulk', [AssetCategoryController::class, 'bulkDestroy'])
            ->name('asset-categories.bulkDestroy');
        Route::patch('assets/category/{category}', [AssetCategoryController::class, 'update'])
            ->name('asset-categories.update');
        Route::delete('assets/category/{category}', [AssetCategoryController::class, 'destroy'])
            ->name('asset-categories.destroy');
        Route::post('assets/{asset}/panel-mcbs', [PanelPowerController::class, 'storeMcb'])
            ->name('panel-mcbs.store');
        Route::post('assets/{asset}/panel-mcbs/batch', [PanelPowerController::class, 'storeMcbs'])
            ->name('panel-mcbs.batch');
        Route::patch('assets/{asset}/panel-mcbs/{panelMcb}', [PanelPowerController::class, 'updateMcb'])
            ->name('panel-mcbs.update');
        Route::delete('assets/{asset}/panel-mcbs/{panelMcb}', [PanelPowerController::class, 'destroyMcb'])
            ->name('panel-mcbs.destroy');
        Route::get('assets/maintenance/export', [MaintenanceController::class, 'export'])
            ->name('assets.maintenance.export');
        Route::post('assets/maintenance', [MaintenanceController::class, 'store'])
            ->name('assets.maintenance.store');
        Route::patch('assets/maintenance/{maintenance}', [MaintenanceController::class, 'update'])
            ->name('assets.maintenance.update');
        Route::delete('assets/maintenance/{maintenance}', [MaintenanceController::class, 'destroy'])
            ->name('assets.maintenance.destroy');
        Route::post('assets/troubleshoot', [TroubleshootController::class, 'store'])
            ->name('assets.troubleshoot.store');
        Route::get('assets/troubleshoot/export', [TroubleshootController::class, 'export'])
            ->name('assets.troubleshoot.export');
        Route::get('assets/troubleshoot/history/export', [TroubleshootController::class, 'exportHistory'])
            ->name('assets.troubleshoot.history.export');
        Route::patch('assets/troubleshoot/{troubleshoot}', [TroubleshootController::class, 'update'])
            ->name('assets.troubleshoot.update');
        Route::delete('assets/troubleshoot/{troubleshoot}', [TroubleshootController::class, 'destroy'])
            ->name('assets.troubleshoot.destroy');
        Route::patch('assets/settings/{asset}', [AssetSettingsController::class, 'update'])
            ->name('assets.settings.update');
    });

    Route::middleware('permission:view-rack-management|manage-rack-management')->group(function () {
        Route::get('rack', [RackController::class, 'index'])->name('rack.index');
        Route::get('rack/overview', [RackController::class, 'overview'])->name('rack.overview');
        Route::get('rack/racklist', [RackTypeController::class, 'index'])->name('rack.racklist');
    });

    Route::middleware('permission:manage-rack-management')->group(function () {
        Route::get('rack/export', [RackController::class, 'export'])->name('rack.export');
        Route::post('rack', [RackController::class, 'store'])->name('rack.store');
        Route::patch('rack/{rack}', [RackController::class, 'update'])->name('rack.update');
        Route::delete('rack/{rack}', [RackController::class, 'destroy'])->name('rack.destroy');
        Route::post('rack/racklist', [RackTypeController::class, 'store'])->name('rack.racklist.store');
        Route::patch('rack/racklist/{rackType}', [RackTypeController::class, 'update'])->name('rack.racklist.update');
        Route::delete('rack/racklist/{rackType}', [RackTypeController::class, 'destroy'])->name('rack.racklist.destroy');
    });

    // The customer module root redirects to its overview child page, exactly
    // like the rack module: /customer is the menu URL, /customer/overview
    // holds the table.
    Route::middleware('permission:view-customer-list|manage-customer-list')->group(function () {
        Route::get('customer', [CustomerController::class, 'index'])->name('customer.index');
        Route::get('customer/overview', [CustomerController::class, 'overview'])->name('customer.overview');
        Route::get('customer/vlanreq', [VlanTagRequestController::class, 'index'])->name('customer.vlanreq');
    });

    Route::middleware('permission:manage-customer-list')->group(function () {
        Route::post('customer', [CustomerController::class, 'store'])->name('customer.store');
        Route::patch('customer/{customer}', [CustomerController::class, 'update'])->name('customer.update');
        Route::delete('customer/{customer}', [CustomerController::class, 'destroy'])->name('customer.destroy');
        Route::get('customer/export', [CustomerController::class, 'export'])->name('customer.export');
        Route::get('customer/vlanreq/export', [VlanTagRequestController::class, 'export'])->name('customer.vlanreq.export');
        Route::post('customer/vlanreq', [VlanTagRequestController::class, 'store'])->name('customer.vlanreq.store');
        Route::patch('customer/vlanreq/{vlanTagRequest}', [VlanTagRequestController::class, 'update'])->name('customer.vlanreq.update');
        Route::delete('customer/vlanreq/{vlanTagRequest}', [VlanTagRequestController::class, 'destroy'])->name('customer.vlanreq.destroy');
    });

    // The checklist module root redirects to its overview child page. Real
    // checklist routes must be registered before the dynamic child catch-all
    // below so /checklist/{child} never falls through to a placeholder page.
    Route::middleware('permission:view-checklist|manage-checklist')->group(function () {
        Route::get('checklist', [ChecklistController::class, 'index']);
        Route::get('checklist/overview', [ChecklistController::class, 'overview'])
            ->name('checklist.overview');
        Route::get('checklist/dailycheck', [ChecklistController::class, 'dailycheck'])
            ->name('checklist.dailycheck');
        Route::get('checklist/monthlycheck', [MonthlyCheckController::class, 'index'])
            ->name('checklist.monthlycheck');
    });

    Route::middleware('permission:manage-checklist')->group(function () {
        Route::post('checklist/entries', [ChecklistController::class, 'storeEntries'])
            ->name('checklist.entries.store');
        Route::delete('checklist/entries/{entry}', [ChecklistController::class, 'destroyEntry'])
            ->name('checklist.entries.destroy');

        Route::post('checklist/monthlycheck/load', [MonthlyCheckController::class, 'storeLoad'])
            ->name('checklist.monthlycheck.load');
        Route::post('checklist/monthlycheck/panel', [MonthlyCheckController::class, 'storePanel'])
            ->name('checklist.monthlycheck.panel');
        Route::get('checklist/monthlycheck/export', [MonthlyCheckController::class, 'export'])
            ->name('checklist.monthlycheck.export');
    });

    // The monitoring module root redirects to its overview child page; every
    // monitoring page lives under its own child URL (/monitoring/{child}).
    // Deliberately unnamed: a "monitoring.index" name would make showChild's
    // overview fallback redirect /monitoring/overview into this redirect.
    Route::get('monitoring', fn () => redirect('/monitoring/overview'))
        ->middleware('permission:view-monitoring|manage-monitoring');

    // Dynamic child pages (e.g. /monitoring/overview). Must come before the
    // single-segment catch-all below. Uppercase letters are allowed so menu
    // URLs like /assets/Test reach the controller instead of a 405 from the
    // {asset}/{rack} PATCH-DELETE wildcards.
    Route::get('/{parent}/{child}', [PageController::class, 'showChild'])
        ->name('dynamic.child')
        ->where('parent', '[a-zA-Z][a-zA-Z0-9-]*')
        ->where('child', '[a-zA-Z][a-zA-Z0-9-]*');

    // Dynamic pages generated by menus. Must be last to avoid conflicts.
    Route::get('/{slug}', [PageController::class, 'show'])
        ->name('dynamic.page')
        ->where('slug', '[a-zA-Z][a-zA-Z0-9-]*');
});
