<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class WebhookController extends Controller
{
    /**
     * Maneja los webhooks de GitHub para despliegue automático
     */
    public function github(Request $request)
    {
        // Verificar que el webhook viene de GitHub
        $signature = $request->header('X-Hub-Signature-256');
        $payload = $request->getContent();
        $secret = env('GITHUB_WEBHOOK_SECRET');
        
        if (!$this->verifySignature($signature, $payload, $secret)) {
            Log::warning('Webhook de GitHub rechazado: firma inválida');
            return response('Unauthorized', 401);
        }

        // Verificar que es un push a la rama main
        $data = $request->json()->all();
        $ref = $data['ref'] ?? '';
        $commits = $data['commits'] ?? [];

        if ($ref !== 'refs/heads/main' || empty($commits)) {
            Log::info('Webhook recibido pero no es un push a main o no hay commits');
            return response('OK', 200);
        }

        Log::info('Webhook de GitHub recibido para rama main', [
            'commits' => count($commits),
            'pusher' => $data['pusher']['name'] ?? 'unknown'
        ]);

        // Ejecutar el script de despliegue en segundo plano
        $this->executeDeploy();

        return response('Deploy iniciado', 200);
    }

    /**
     * Verifica la firma del webhook de GitHub
     */
    private function verifySignature($signature, $payload, $secret)
    {
        if (!$signature || !$secret) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($signature, $expectedSignature);
    }

    /**
     * Ejecuta el script de despliegue
     */
    private function executeDeploy()
    {
        $deployScript = base_path('deploy.sh');
        
        if (!file_exists($deployScript)) {
            Log::error('Script de despliegue no encontrado: ' . $deployScript);
            return;
        }

        // Ejecutar el script en segundo plano
        $command = "nohup {$deployScript} > /dev/null 2>&1 &";
        
        Log::info('Ejecutando script de despliegue: ' . $command);
        
        // Ejecutar el comando
        exec($command);
    }
}
