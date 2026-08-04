<?php

namespace App\Console\Commands;

use App\Helpers\ImageHelper;
use App\Models\OrdenBosquejo;
use App\Models\PlantillaBosquejo;
use Illuminate\Console\Command;

class RegenerarThumbnailsBosquejos extends Command
{
    /**
     * Uso:
     *   php artisan bosquejos:regenerar-thumbnails            (regenera todos)
     *   php artisan bosquejos:regenerar-thumbnails --orden=95 (solo una orden)
     */
    protected $signature = 'bosquejos:regenerar-thumbnails {--orden= : Regenerar solo los bosquejos de esta orden}';

    protected $description = 'Regenera las miniaturas de bosquejos preservando la proporcion (arregla texto distorsionado)';

    public function handle(): int
    {
        $ordenId = $this->option('orden');
        $ok = 0;
        $sinFuente = 0;
        $err = 0;

        // --- Bosquejos de ordenes ---
        $queryOrden = OrdenBosquejo::whereNotNull('ruta_miniatura');
        if ($ordenId) {
            $queryOrden->where('orden_id', $ordenId);
        }
        $bosquejos = $queryOrden->get();
        $this->info("Bosquejos de ordenes a procesar: {$bosquejos->count()}");

        foreach ($bosquejos as $b) {
            $this->regenerar($b, $ok, $sinFuente, $err);
        }

        // --- Plantillas de la matriz (solo si no se filtro por orden) ---
        if (!$ordenId && class_exists(PlantillaBosquejo::class)) {
            $plantillas = PlantillaBosquejo::whereNotNull('ruta_miniatura')->get();
            $this->info("Plantillas de matriz a procesar: {$plantillas->count()}");
            foreach ($plantillas as $p) {
                $this->regenerar($p, $ok, $sinFuente, $err);
            }
        }

        $this->newLine();
        $this->info("Regeneradas OK: {$ok} | sin archivo fuente: {$sinFuente} | errores: {$err}");

        return self::SUCCESS;
    }

    private function regenerar($modelo, int &$ok, int &$sinFuente, int &$err): void
    {
        $src = $modelo->ruta_archivo ? public_path($modelo->ruta_archivo) : null;
        $thumb = $modelo->ruta_miniatura ? public_path($modelo->ruta_miniatura) : null;

        if (!$src || !is_file($src) || !$thumb) {
            $sinFuente++;
            return;
        }

        try {
            ImageHelper::makeSquareThumbnail($src, $thumb);
            $ok++;
        } catch (\Throwable $e) {
            $err++;
            $this->warn("  id={$modelo->id}: {$e->getMessage()}");
        }
    }
}
