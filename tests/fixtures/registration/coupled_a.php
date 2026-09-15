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
 * The charter's paired negative: the same registrations written against one
 * shared receiver. Dataflow-coupled, so it is a procedure rather than a table,
 * and its clone with coupled_b.php stays ASSERTED.
 */

use Illuminate\Support\Facades\Route;

$router->get('/catalog/index', [IndexController::class, 'index']);
$router->get('/catalog/create', [CreateController::class, 'create']);
$router->get('/catalog/store', [StoreController::class, 'store']);
$router->get('/catalog/show', [ShowController::class, 'show']);
$router->get('/catalog/edit', [EditController::class, 'edit']);
$router->get('/catalog/update', [UpdateController::class, 'update']);
$router->get('/catalog/destroy', [DestroyController::class, 'destroy']);
$router->get('/catalog/restore', [RestoreController::class, 'restore']);
$router->get('/catalog/archive', [ArchiveController::class, 'archive']);
$router->get('/catalog/publish', [PublishController::class, 'publish']);
$router->get('/catalog/draft', [DraftController::class, 'draft']);
$router->get('/catalog/review', [ReviewController::class, 'review']);
$router->get('/catalog/approve', [ApproveController::class, 'approve']);
$router->get('/catalog/reject', [RejectController::class, 'reject']);
