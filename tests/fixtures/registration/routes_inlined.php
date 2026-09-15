<?php

declare(strict_types=1);
/*
 * This file is part of PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * The same registrations as routes_a.php, copied into program text: this file's
 * top level is one class declaration, so it is not registration-role. The clone
 * therefore has one registration site and one that is not, and `Strata`'s
 * every-site rule keeps it ASSERTED — the copy in the class is exactly what a
 * reader wants shown.
 */

use Illuminate\Support\Facades\Route;

final class CatalogRouteInstaller
{
    public function install(): void
    {
        Route::get('/catalog/index', [IndexController::class, 'index']);
        Route::get('/catalog/create', [CreateController::class, 'create']);
        Route::get('/catalog/store', [StoreController::class, 'store']);
        Route::get('/catalog/show', [ShowController::class, 'show']);
        Route::get('/catalog/edit', [EditController::class, 'edit']);
        Route::get('/catalog/update', [UpdateController::class, 'update']);
        Route::get('/catalog/destroy', [DestroyController::class, 'destroy']);
        Route::get('/catalog/restore', [RestoreController::class, 'restore']);
        Route::get('/catalog/archive', [ArchiveController::class, 'archive']);
        Route::get('/catalog/publish', [PublishController::class, 'publish']);
        Route::get('/catalog/draft', [DraftController::class, 'draft']);
        Route::get('/catalog/review', [ReviewController::class, 'review']);
        Route::get('/catalog/approve', [ApproveController::class, 'approve']);
        Route::get('/catalog/reject', [RejectController::class, 'reject']);
    }
}
