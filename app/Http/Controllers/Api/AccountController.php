<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse {
        $accounts = $request->user()
            ->accounts()
            ->orderBy('id')
            ->paginate(20);

        return response()->json($accounts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StoreAccountRequest $request
    ): JsonResponse {
        $account = $request->user()
            ->accounts()
            ->create($request->validated());

        return response()->json($account, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        return response()->json($account);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateAccountRequest $request,
        Account $account
    ): JsonResponse {
        $this->authorize('update', $account);

        $account->update($request->validated());

        return response()->json($account);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        if ($account->transactions()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir uma conta que possui movimentações.',
            ], 409);
        }

        $account->delete();

        return response()->json(null, 204);
    }
}