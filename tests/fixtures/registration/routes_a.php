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
 * A registration-role file: every top-level statement past the preamble is a
 * dataflow-independent registration expression. Its clone with routes_b.php is
 * ASSERTED — two different registration files agreeing is a copy someone could
 * remove, not the regularity that makes a route file one. A file repeating
 * itself is the demoted case; routes_self.php is that.
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
