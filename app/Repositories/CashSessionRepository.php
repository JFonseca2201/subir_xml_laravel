<?php

namespace App\Repositories;

use App\Models\CashSession;
use App\Models\CashDenomination;
use Illuminate\Support\Collection;

class CashSessionRepository
{
    public function findOpenSessionByUser(int $userId): ?CashSession
    {
        return CashSession::where('user_id', $userId)
            ->where('status', 'open')
            ->first();
    }

    public function createSession(array $data): CashSession
    {
        return CashSession::create($data);
    }

    public function addMovement(CashSession $session, string $type, float $amount): CashSession
    {
        if ($type === 'deposit') {
            $session->increment('cash_deposits', $amount);
        } elseif ($type === 'withdrawal') {
            $session->increment('cash_withdrawals', $amount);
        } elseif ($type === 'sale') {
            $session->increment('sales_total', $amount);
        }

        return $session->fresh();
    }

    public function getSummary(CashSession $session): array
    {
        return [
            'session_id' => $session->id,
            'user_id' => $session->user_id,
            'opening_balance' => $session->opening_balance,
            'cash_deposits' => $session->cash_deposits,
            'cash_withdrawals' => $session->cash_withdrawals,
            'sales_total' => $session->sales_total,
            'expected_balance' => $session->getExpectedBalanceAttribute(),
            'status' => $session->status,
            'created_at' => $session->created_at,
        ];
    }

    public function closeSession(CashSession $session, array $denominationData): array
    {
        // Crear registro de arqueo físico
        $denomination = CashDenomination::create([
            'cash_session_id' => $session->id,
            'bill_100_count' => $denominationData['bill_100_count'] ?? 0,
            'bill_50_count' => $denominationData['bill_50_count'] ?? 0,
            'bill_20_count' => $denominationData['bill_20_count'] ?? 0,
            'bill_10_count' => $denominationData['bill_10_count'] ?? 0,
            'bill_5_count' => $denominationData['bill_5_count'] ?? 0,
            'bill_1_count' => $denominationData['bill_1_count'] ?? 0,
            'coin_1_count' => $denominationData['coin_1_count'] ?? 0,
            'coin_50_count' => $denominationData['coin_50_count'] ?? 0,
            'coin_25_count' => $denominationData['coin_25_count'] ?? 0,
            'coin_10_count' => $denominationData['coin_10_count'] ?? 0,
            'coin_5_count' => $denominationData['coin_5_count'] ?? 0,
            'coin_1_cent_count' => $denominationData['coin_1_cent_count'] ?? 0,
            'expected_balance' => $session->getExpectedBalanceAttribute(),
        ]);

        // Cerrar la sesión
        $session->update([
            'final_balance' => $denomination->total_physical,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return [
            'session' => $session->fresh(),
            'denomination' => $denomination->fresh(),
        ];
    }

    public function getSessionHistory(int $userId, ?int $limit = null): Collection
    {
        $query = CashSession::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getOpenSessions(): Collection
    {
        return CashSession::where('status', 'open')
            ->with('user')
            ->get();
    }

    public function getSessionWithDenominations(int $sessionId): ?CashSession
    {
        return CashSession::with(['user', 'cashDenominations'])
            ->find($sessionId);
    }
}
