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
 * One registration-role file repeating itself. The second run of routes is not
 * a copy anybody could remove — it is the regularity that makes this a route
 * file — which is the case the registration stratum exists to demote, and the
 * one ruling H draws the same way for tables.
 */

use Illuminate\Support\Facades\Route;

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

Route::get('/archive/index', [IndexController::class, 'index']);
Route::get('/archive/create', [CreateController::class, 'create']);
Route::get('/archive/store', [StoreController::class, 'store']);
Route::get('/archive/show', [ShowController::class, 'show']);
Route::get('/archive/edit', [EditController::class, 'edit']);
Route::get('/archive/update', [UpdateController::class, 'update']);
Route::get('/archive/destroy', [DestroyController::class, 'destroy']);
Route::get('/archive/restore', [RestoreController::class, 'restore']);
Route::get('/archive/archive', [ArchiveController::class, 'archive']);
Route::get('/archive/publish', [PublishController::class, 'publish']);
Route::get('/archive/draft', [DraftController::class, 'draft']);
Route::get('/archive/review', [ReviewController::class, 'review']);
Route::get('/archive/approve', [ApproveController::class, 'approve']);
Route::get('/archive/reject', [RejectController::class, 'reject']);
