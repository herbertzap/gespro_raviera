<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        
        // Sincronización diaria completa de productos a las 2:00 AM
        // Incluye: productos nuevos, actualización de existentes, precios y stock
        $schedule->command('productos:sincronizar-programado')
                ->dailyAt('02:00')
                ->withoutOverlapping()
                ->runInBackground();
        
        // Verificar cada hora si las NVV han sido facturadas
        $schedule->command('nvv:verificar-facturadas')
                ->hourly()
                ->withoutOverlapping()
                ->runInBackground();
        
        // Sincronización diaria de cheques protestados a las 3:00 AM
        $schedule->command('cheques:sincronizar')
                ->dailyAt('03:00')
                ->withoutOverlapping()
                ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
