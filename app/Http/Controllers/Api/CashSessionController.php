<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\CashSessionRepository;
use App\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CashSessionController extends Controller
{
    protected CashSessionRepository $repository;

    public function __construct(CashSessionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Abrir caja con monto inicial
     */
    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        // Verificar si ya existe una sesión abierta para este usuario
        $existingSession = $this->repository->findOpenSessionByUser($validated['user_id']);
        if ($existingSession) {
            return response()->json([
                'status' => 422,
                'message' => 'El usuario ya tiene una sesión de caja abierta',
                'session' => $this->repository->getSummary($existingSession),
            ], 422);
        }

        // Crear nueva sesión
        $session = $this->repository->createSession([
            'user_id' => $validated['user_id'],
            'opening_balance' => $validated['opening_balance'],
            'status' => 'open',
        ]);

        $sessionData = $this->repository->getSummary($session);
        $sessionData['formatted_date'] = $session->created_at->format('d/m/Y');
        $sessionData['formatted_time'] = $session->created_at->format('H:i:s');

        return response()->json([
            'status' => 201,
            'message' => 'Caja abierta correctamente',
            'session' => $sessionData,
        ], 201);
    }

    /**
     * Agregar movimiento manual (depósito o retiro)
     */
    public function addMovement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        // Buscar sesión abierta
        $session = $this->repository->findOpenSessionByUser($validated['user_id']);
        if (!$session) {
            return response()->json([
                'status' => 404,
                'message' => 'No hay sesión de caja abierta para este usuario',
            ], 404);
        }

        // Agregar movimiento
        $session = $this->repository->addMovement($session, $validated['type'], $validated['amount']);

        return response()->json([
            'status' => 200,
            'message' => $validated['type'] === 'deposit' ? 'Depósito registrado correctamente' : 'Retiro registrado correctamente',
            'movement' => [
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ],
            'session' => $this->repository->getSummary($session),
        ]);
    }

    /**
     * Obtener resumen en tiempo real de la caja
     */
    public function getSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Buscar sesión abierta
        $session = $this->repository->findOpenSessionByUser($validated['user_id']);
        if (!$session) {
            return response()->json([
                'status' => 404,
                'message' => 'No hay sesión de caja abierta para este usuario',
            ], 404);
        }

        $summary = $this->repository->getSummary($session);

        return response()->json([
            'status' => 200,
            'summary' => $summary,
        ]);
    }

    /**
     * Cerrar caja con arqueo físico
     */
    public function close(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'bill_100_count' => 'required|integer|min:0',
            'bill_50_count' => 'required|integer|min:0',
            'bill_20_count' => 'required|integer|min:0',
            'bill_10_count' => 'required|integer|min:0',
            'bill_5_count' => 'required|integer|min:0',
            'bill_1_count' => 'required|integer|min:0',
            'coin_1_count' => 'required|integer|min:0',
            'coin_50_count' => 'required|integer|min:0',
            'coin_25_count' => 'required|integer|min:0',
            'coin_10_count' => 'required|integer|min:0',
            'coin_5_count' => 'required|integer|min:0',
            'coin_1_cent_count' => 'required|integer|min:0',
        ]);

        // Buscar sesión abierta
        $session = $this->repository->findOpenSessionByUser($validated['user_id']);
        if (!$session) {
            return response()->json([
                'status' => 404,
                'message' => 'No hay sesión de caja abierta para este usuario',
            ], 404);
        }

        // Cerrar sesión con arqueo
        $result = $this->repository->closeSession($session, $validated);

        return response()->json([
            'status' => 200,
            'message' => 'Caja cerrada correctamente',
            'session' => $result['session'],
            'denomination' => [
                'id' => $result['denomination']->id,
                'total_physical' => $result['denomination']->total_physical,
                'formatted_total_physical' => $result['denomination']->formatted_total_physical,
                'expected_balance' => $result['denomination']->expected_balance,
                'formatted_expected_balance' => $result['denomination']->formatted_expected_balance,
                'difference' => $result['denomination']->difference,
                'formatted_difference' => $result['denomination']->formatted_difference,
                'balance_status' => $result['denomination']->balance_status,
                'balance_status_description' => $result['denomination']->balance_status_description,
                'denominations' => $result['denomination']->getDenominationsArray(),
            ],
        ]);
    }

    /**
     * Obtener historial de sesiones de un usuario
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $sessions = $this->repository->getSessionHistory(
            $validated['user_id'],
            $validated['limit'] ?? 20
        );

        return response()->json([
            'status' => 200,
            'sessions' => $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'opening_balance' => $session->opening_balance,
                    'formatted_opening_balance' => $session->formatted_opening_balance,
                    'cash_deposits' => $session->cash_deposits,
                    'formatted_cash_deposits' => $session->formatted_cash_deposits,
                    'cash_withdrawals' => $session->cash_withdrawals,
                    'formatted_cash_withdrawals' => $session->formatted_cash_withdrawals,
                    'sales_total' => $session->sales_total,
                    'formatted_sales_total' => $session->formatted_sales_total,
                    'final_balance' => $session->final_balance,
                    'formatted_final_balance' => $session->formatted_final_balance,
                    'expected_balance' => $session->getExpectedBalanceAttribute(),
                    'formatted_expected_balance' => $session->formatted_expected_balance,
                    'status' => $session->status,
                    'status_description' => $session->status_description,
                    'created_at' => $session->created_at->format('Y-m-d H:i:s'),
                    'closed_at' => $session->closed_at?->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    /**
     * Obtener detalles de una sesión específica
     */
    public function show(int $sessionId): JsonResponse
    {
        $session = $this->repository->getSessionWithDenominations($sessionId);
        if (!$session) {
            return response()->json([
                'status' => 404,
                'message' => 'Sesión no encontrada',
            ], 404);
        }

        $sessionData = [
            'id' => $session->id,
            'user' => [
                'id' => $session->user->id,
                'name' => $session->user->name,
            ],
            'opening_balance' => $session->opening_balance,
            'formatted_opening_balance' => $session->formatted_opening_balance,
            'cash_deposits' => $session->cash_deposits,
            'formatted_cash_deposits' => $session->formatted_cash_deposits,
            'cash_withdrawals' => $session->cash_withdrawals,
            'formatted_cash_withdrawals' => $session->formatted_cash_withdrawals,
            'sales_total' => $session->sales_total,
            'formatted_sales_total' => $session->formatted_sales_total,
            'expected_balance' => $session->getExpectedBalanceAttribute(),
            'formatted_expected_balance' => $session->formatted_expected_balance,
            'final_balance' => $session->final_balance,
            'formatted_final_balance' => $session->formatted_final_balance,
            'status' => $session->status,
            'status_description' => $session->status_description,
            'created_at' => $session->created_at->format('Y-m-d H:i:s'),
            'closed_at' => $session->closed_at?->format('Y-m-d H:i:s'),
        ];

        // Agregar datos de arqueo si existe
        if ($session->cashDenominations->isNotEmpty()) {
            $denomination = $session->cashDenominations->first();
            $sessionData['denomination'] = [
                'id' => $denomination->id,
                'total_physical' => $denomination->total_physical,
                'formatted_total_physical' => $denomination->formatted_total_physical,
                'expected_balance' => $denomination->expected_balance,
                'formatted_expected_balance' => $denomination->formatted_expected_balance,
                'difference' => $denomination->difference,
                'formatted_difference' => $denomination->formatted_difference,
                'balance_status' => $denomination->balance_status,
                'balance_status_description' => $denomination->balance_status_description,
                'denominations' => $denomination->getDenominationsArray(),
            ];
        }

        return response()->json([
            'status' => 200,
            'session' => $sessionData,
        ]);
    }

    /**
     * Verificar si existe sesión abierta para el usuario actual
     */
    public function checkOpen(Request $request): JsonResponse
    {
        $userId = $request->get('user_id', auth()->id());

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

    /**
     * Obtener todas las sesiones abiertas
     */
    public function openSessions(): JsonResponse
    {
        $sessions = $this->repository->getOpenSessions();

        return response()->json([
            'status' => 200,
            'sessions' => $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'user' => [
                        'id' => $session->user->id,
                        'name' => $session->user->name,
                    ],
                    'opening_balance' => $session->opening_balance,
                    'formatted_opening_balance' => $session->formatted_opening_balance,
                    'expected_balance' => $session->getExpectedBalanceAttribute(),
                    'formatted_expected_balance' => $session->formatted_expected_balance,
                    'created_at' => $session->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }
}
