<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Evita NVV duplicadas cuando el vendedor reenvía el formulario
 * (doble clic, otra pestaña o recarga mientras el guardado sigue en curso).
 */
class NvvGuardadoAntiDuplicadoService
{
    private const LOCK_SECONDS = 180;

    private const FINGERPRINT_MINUTES = 8;

    public function acquireUserLock(int $userId): ?object
    {
        $lock = Cache::lock('nvv_guardar_user:'.$userId, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return null;
        }

        return $lock;
    }

    /**
     * @return array{cotizacion_id: int}|null Si ya existe una NVV equivalente reciente.
     */
    public function findRecentDuplicate(int $userId, Request $request, float $total): ?array
    {
        $key = $this->fingerprintCacheKey($userId, $request, $total);
        $existingId = Cache::get($key);

        if ($existingId) {
            return ['cotizacion_id' => (int) $existingId];
        }

        return null;
    }

    public function registerSuccessfulSave(int $userId, Request $request, float $total, int $cotizacionId): void
    {
        $key = $this->fingerprintCacheKey($userId, $request, $total);
        Cache::put($key, $cotizacionId, now()->addMinutes(self::FINGERPRINT_MINUTES));
    }

    private function fingerprintCacheKey(int $userId, Request $request, float $total): string
    {
        $productos = collect($request->input('productos', []))
            ->map(fn ($p) => [
                (string) ($p['codigo'] ?? ''),
                round((float) ($p['cantidad'] ?? 0), 4),
                round((float) ($p['precio'] ?? 0), 2),
                round((float) ($p['descuento'] ?? 0), 2),
            ])
            ->sortBy(fn ($row) => $row[0])
            ->values()
            ->all();

        $payload = [
            'user' => $userId,
            'cliente' => (string) $request->input('cliente_codigo'),
            'total' => round($total, 2),
            'productos' => $productos,
        ];

        return 'nvv_fingerprint:'.hash('sha256', json_encode($payload));
    }
}
