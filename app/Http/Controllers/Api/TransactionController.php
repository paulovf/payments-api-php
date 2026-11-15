<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Account $account): JsonResponse
    {
        $this->authorize('viewTransactions', $account);

        return response()->json(
            $account->transactions()
                ->orderByDesc('id')
                ->paginate(20)
        );
    }

    public function store(
        StoreTransactionRequest $request,
        Account $account
    ): JsonResponse {
        $this->authorize('createTransaction', $account);

        $data = $request->validated();

        $idempotencyKey = $data['idempotency_key'];

        $findExisting = fn () => \App\Models\Transaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $matchesRequest = function ($transaction) use ($account, $data): bool {
            return $transaction->account_id === $account->id
                && $transaction->amount_in_cents === $data['amount_in_cents']
                && $transaction->type === $data['type']
                && $transaction->description === $data['description'];
        };

        $existing = $findExisting();

        if ($existing !== null) {
            if (!$matchesRequest($existing)) {
                return response()->json([
                    'message' => 'A chave de idempotência já foi utilizada com outros dados.',
                ], 409);
            }

            return response()->json([
                'data' => $existing,
                'balance_in_cents' => $account->fresh()->balance_in_cents,
            ])->header('Idempotency-Replayed', 'true');
        }

        try {
            $transaction = DB::transaction(function () use (
                $account,
                $data,
                $idempotencyKey,
                $findExisting,
                $matchesRequest
            ) {
                $lockedAccount = Account::query()
                    ->whereKey($account->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Segunda verificação após adquirir o bloqueio.
                $existing = $findExisting();

                if ($existing !== null) {
                    if (!$matchesRequest($existing)) {
                        throw new \DomainException(
                            'A chave de idempotência já foi utilizada com outros dados.'
                        );
                    }

                    return $existing;
                }

                $amount = $data['amount_in_cents'];

                if (
                    $data['type'] === 'debit'
                    && $lockedAccount->balance_in_cents < $amount
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'amount_in_cents' => 'Saldo insuficiente para realizar a operação.',
                    ]);
                }

                $balanceChange = $data['type'] === 'credit'
                    ? $amount
                    : -$amount;

                $lockedAccount->balance_in_cents += $balanceChange;
                $lockedAccount->save();

                return $lockedAccount->transactions()->create([
                    'amount_in_cents' => $amount,
                    'type' => $data['type'],
                    'status' => 'completed',
                    'description' => $data['description'],
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (\Illuminate\Database\QueryException $exception) {
            // PostgreSQL: 23505 = violação de unicidade.
            $isUniqueViolation = ($exception->errorInfo[0] ?? null) === '23505';

            if (!$isUniqueViolation) {
                throw $exception;
            }

            // Outra requisição pode ter inserido a mesma chave simultaneamente.
            $existing = $findExisting();

            if ($existing === null) {
                throw $exception;
            }

            if (!$matchesRequest($existing)) {
                return response()->json([
                    'message' => 'A chave de idempotência já foi utilizada com outros dados.',
                ], 409);
            }

            return response()->json([
                'data' => $existing,
                'balance_in_cents' => $account->fresh()->balance_in_cents,
            ])->header('Idempotency-Replayed', 'true');
        }

        return response()->json([
            'data' => $transaction,
            'balance_in_cents' => $account->fresh()->balance_in_cents,
        ], 201);
    }
}