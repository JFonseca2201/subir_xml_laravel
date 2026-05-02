<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Repositories\CashSessionRepository;

class CashSessionTestController extends Controller
{
    protected CashSessionRepository $repository;

    public function __construct(CashSessionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Verificar si existe sesión abierta para el usuario (SIN AUTENTICACIÓN)
     */
    public function checkOpen(Request $request): JsonResponse
    {
        $userId = $request->get('user_id');

        if (!$userId) {
            return response()->json([
                'status' => 400,
                'message' => 'Se requiere ID de usuario',
            ], 400);
        }

        $session = $this->repository->findOpenSessionByUser($userId);

        if (!$session) {
            return response()->json([
                'status' => 404,
                'message' => 'No hay sesión de caja abierta',
                'has_open_session' => false,
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Sesión de caja abierta encontrada',
            'has_open_session' => true,
            'session' => [
                'id' => $session->id,
                'user_id' => $session->user_id,
                'opening_balance' => $session->opening_balance,
                'formatted_opening_balance' => $session->formatted_opening_balance,
                'cash_deposits' => $session->cash_deposits,
                'cash_withdrawals' => $session->cash_withdrawals,
                'sales_total' => $session->sales_total,
                'final_balance' => $session->final_balance,
                'status' => $session->status,
                'created_at' => $session->created_at->format('Y-m-d H:i:s'),
                'expected_balance' => $session->getExpectedBalanceAttribute(),
                'formatted_expected_balance' => $session->formatted_expected_balance,
            ],
        ]);
    }
}
