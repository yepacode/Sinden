<?php

namespace App\Http\Controllers;

use App\Models\GrupoBosquejo;
use App\Models\PlantillaBosquejo;
use App\Models\ConfiguracionSistema;
use App\Traits\RegistraActividad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Helpers\ImageHelper;
use Intervention\Image\Facades\Image;

class BosquejoMatrizController extends Controller
{
    use RegistraActividad;

    public function __construct()
    {
        $this->middleware('permission:gestionar_bosquejos_matriz')
            ->only(['storeGrupo', 'updateGrupo', 'destroyGrupo', 'storeBosquejo', 'updateBosquejo', 'destroyBosquejo', 'updateNombreGenericos']);
    }

    /**
     * Pagina principal con accordion de grupos y tarjetas de bosquejos.
     */
    public function index()
    {
        $grupos = GrupoBosquejo::with(['plantillas' => function ($q) {
            $q->orderBy('nombre');
        }])->orderBy('nombre')->get();

        $totalGrupos = GrupoBosquejo::count();
        $totalBosquejos = PlantillaBosquejo::count();
        $bosquejosSinGrupo = PlantillaBosquejo::whereNull('grupo_bosquejo_id')->count();

        $bosquejosSueltos = PlantillaBosquejo::whereNull('grupo_bosquejo_id')
            ->orderBy('nombre')->get();

        return view('bosquejos-matriz.index', compact(
            'grupos',
            'totalGrupos',
            'totalBosquejos',
            'bosquejosSinGrupo',
            'bosquejosSueltos'
        ));
    }

    /**
     * Actualizar el nombre de la seccion de bosquejos sin grupo (AJAX).
     */
    public function updateNombreGenericos(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:50',
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 50 caracteres.',
        ]);

        $anterior = ConfiguracionSistema::get('nombre_bosquejos_genericos', 'Genericos');
        ConfiguracionSistema::set('nombre_bosquejos_genericos', $validated['nombre']);

        $this->registrarActividad(
            'bosquejo_grupo.nombre_genericos_actualizado',
            "Se cambio el nombre de los bosquejos sin grupo de '{$anterior}' a '{$validated['nombre']}'",
            null,
            ['anterior' => $anterior, 'nuevo' => $validated['nombre']]
        );

        return response()->json([
            'success' => true,
            'nombre' => $validated['nombre'],
            'message' => 'Nombre actualizado exitosamente.',
        ]);
    }

    /**
     * Crear nuevo grupo (AJAX).
     */
    public function storeGrupo(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ], [
            'nombre.required' => 'El nombre del grupo es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
        ]);

        $grupo = GrupoBosquejo::create($validated);

        $this->registrarCreacion(
            'bosquejo_grupo.creado',
            "Se creo el grupo de bosquejos: {$grupo->nombre}",
            $grupo
        );

        return response()->json([
            'success' => true,
            'message' => 'Grupo creado exitosamente.',
            'grupo' => $grupo,
        ]);
    }

    /**
     * Renombrar grupo (AJAX).
     */
    public function updateGrupo(Request $request, GrupoBosquejo $grupo)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $valoresOriginales = $grupo->getOriginal();
        $nombreAnterior = $grupo->nombre;
        $grupo->update($validated);

        $this->registrarActualizacion(
            'bosquejo_grupo.actualizado',
            "Se renombro el grupo: '{$nombreAnterior}' a '{$grupo->nombre}'",
            $grupo,
            $valoresOriginales
        );

        return response()->json([
            'success' => true,
            'message' => 'Grupo actualizado exitosamente.',
        ]);
    }

    /**
     * Eliminar grupo con todos sus bosquejos y archivos (AJAX).
     */
    public function destroyGrupo(GrupoBosquejo $grupo)
    {
        $nombreGrupo = $grupo->nombre;
        $plantillas = $grupo->plantillas;
        $bosquejosCount = $plantillas->count();

        foreach ($plantillas as $plantilla) {
            $this->eliminarArchivosBosquejo($plantilla);
        }

        $grupo->plantillas()->delete();

        $dirPath = public_path("uploads/bosquejos-matriz/{$grupo->id}");
        if (File::isDirectory($dirPath)) {
            File::deleteDirectory($dirPath);
        }

        $this->registrarEliminacion(
            'bosquejo_grupo.eliminado',
            "Se elimino el grupo: '{$nombreGrupo}' con {$bosquejosCount} bosquejos",
            $grupo,
            null,
            ['bosquejos_eliminados' => $bosquejosCount]
        );

        $grupo->delete();

        return response()->json([
            'success' => true,
            'message' => "Grupo '{$nombreGrupo}' eliminado exitosamente.",
        ]);
    }

    /**
     * Subir bosquejo a un grupo o individual (AJAX).
     */
    public function storeBosquejo(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'grupo_bosquejo_id' => 'nullable|exists:grupos_bosquejos,id',
            'archivo' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
        ], [
            'nombre.required' => 'El nombre del bosquejo es obligatorio.',
            'grupo_bosquejo_id.exists' => 'El grupo seleccionado no existe.',
            'archivo.required' => 'Debe seleccionar una imagen.',
            'archivo.image' => 'El archivo debe ser una imagen.',
            'archivo.mimes' => 'Solo se permiten imagenes JPG, PNG y WebP.',
            'archivo.max' => 'La imagen no puede exceder 10 MB. Tamano maximo permitido: 10 MB.',
            'archivo.uploaded' => 'La imagen supera el tamano maximo permitido (10 MB). Reduzca el tamano e intente de nuevo.',
        ]);

        $grupoId = $validated['grupo_bosquejo_id'] ?? null;
        $uploadSubDir = $grupoId ?: 'individuales';
        $file = $request->file('archivo');

        $uploadPath = public_path("uploads/bosquejos-matriz/{$uploadSubDir}");
        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }

        // Crear registro para obtener ID
        $bosquejo = PlantillaBosquejo::create([
            'grupo_bosquejo_id' => $grupoId,
            'nombre' => $validated['nombre'],
            'ruta_archivo' => '',
            'ruta_miniatura' => null,
        ]);

        $extension = $file->getClientOriginalExtension();
        $timestamp = time();
        $fileNameBase = "bosquejo_{$bosquejo->id}_{$timestamp}";

        // Guardar original
        $originalName = "{$fileNameBase}.{$extension}";
        $file->move($uploadPath, $originalName);
        $rutaArchivo = "uploads/bosquejos-matriz/{$uploadSubDir}/{$originalName}";

        // Generar miniatura
        $thumbName = "{$fileNameBase}_thumb.{$extension}";
        $thumbFullPath = "{$uploadPath}/{$thumbName}";
        $rutaMiniatura = "uploads/bosquejos-matriz/{$uploadSubDir}/{$thumbName}";

        try {
            ImageHelper::makeSquare("{$uploadPath}/{$originalName}");
            ImageHelper::makeSquareThumbnail("{$uploadPath}/{$originalName}", $thumbFullPath);
        } catch (\Exception $e) {
            $rutaMiniatura = $rutaArchivo;
        }

        $bosquejo->update([
            'ruta_archivo' => $rutaArchivo,
            'ruta_miniatura' => $rutaMiniatura,
        ]);

        $desc = $grupoId
            ? "Se subio el bosquejo: '{$bosquejo->nombre}' al grupo ID {$grupoId}"
            : "Se subio el bosquejo individual: '{$bosquejo->nombre}'";

        $this->registrarCreacion(
            'bosquejo.creado',
            $desc,
            $bosquejo,
            null,
            ['grupo_bosquejo_id' => $grupoId]
        );

        return response()->json([
            'success' => true,
            'message' => 'Bosquejo subido exitosamente.',
            'bosquejo' => $bosquejo->fresh(),
        ]);
    }

    /**
     * Renombrar bosquejo (AJAX).
     */
    public function updateBosquejo(Request $request, PlantillaBosquejo $bosquejo)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $valoresOriginales = $bosquejo->getOriginal();
        $nombreAnterior = $bosquejo->nombre;
        $bosquejo->update($validated);

        $this->registrarActualizacion(
            'bosquejo.actualizado',
            "Se renombro el bosquejo: '{$nombreAnterior}' a '{$bosquejo->nombre}'",
            $bosquejo,
            $valoresOriginales
        );

        return response()->json([
            'success' => true,
            'message' => 'Bosquejo actualizado exitosamente.',
        ]);
    }

    /**
     * Eliminar bosquejo y sus archivos (AJAX).
     */
    public function destroyBosquejo(PlantillaBosquejo $bosquejo)
    {
        $nombreBosquejo = $bosquejo->nombre;

        $this->eliminarArchivosBosquejo($bosquejo);

        $this->registrarEliminacion(
            'bosquejo.eliminado',
            "Se elimino el bosquejo: '{$nombreBosquejo}'",
            $bosquejo
        );

        $bosquejo->delete();

        return response()->json([
            'success' => true,
            'message' => "Bosquejo '{$nombreBosquejo}' eliminado exitosamente.",
        ]);
    }

    /**
     * Descargar archivo original del bosquejo.
     */
    public function downloadBosquejo(PlantillaBosquejo $bosquejo)
    {
        $filePath = public_path($bosquejo->ruta_archivo);

        if (!File::exists($filePath)) {
            abort(404, 'Archivo no encontrado.');
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $downloadName = Str::slug($bosquejo->nombre) . '.' . $extension;

        return response()->download($filePath, $downloadName);
    }

    /**
     * Eliminar archivos fisicos de un bosquejo.
     */
    private function eliminarArchivosBosquejo(PlantillaBosquejo $bosquejo): void
    {
        if ($bosquejo->ruta_archivo && File::exists(public_path($bosquejo->ruta_archivo))) {
            File::delete(public_path($bosquejo->ruta_archivo));
        }

        if (
            $bosquejo->ruta_miniatura &&
            $bosquejo->ruta_miniatura !== $bosquejo->ruta_archivo &&
            File::exists(public_path($bosquejo->ruta_miniatura))
        ) {
            File::delete(public_path($bosquejo->ruta_miniatura));
        }
    }
}
