<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectFileRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectFileController extends Controller
{
    /**
     * Subir un fichero asociado a un proyecto.
     */
    public function upload(StoreProjectFileRequest $request, Project $project): RedirectResponse
    {
        abort_unless($project->user_id === auth()->id(), 403);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $storagePath = 'project-files/'.Str::uuid().'.'.$extension;

        try {
            $path = Storage::disk('public')->put($storagePath, $file->get());

            ProjectFile::create([
                'project_id' => $project->id,
                'architecture_id' => $request->input('architecture_id'),
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $storagePath,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        } catch (\Exception $e) {
            // Limpieza compensatoria: borrar fichero si falla BD
            if (isset($storagePath) && Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->delete($storagePath);
            }

            return redirect()->back()
                ->withErrors(['file' => 'Error al guardar el fichero. Inténtalo de nuevo.']);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Fichero subido correctamente.');
    }

    /**
     * Ver/descargar un fichero.
     */
    public function show(ProjectFile $file): Response|BinaryFileResponse
    {
        abort_unless($file->project->user_id === auth()->id(), 403);

        if (! Storage::disk('public')->exists($file->storage_path)) {
            abort(404, 'Fichero no encontrado.');
        }

        return Storage::disk('public')->response($file->storage_path, $file->original_name);
    }

    /**
     * Sustituir un fichero por uno nuevo.
     */
    public function replace(StoreProjectFileRequest $request, ProjectFile $file): RedirectResponse
    {
        abort_unless($file->project->user_id === auth()->id(), 403);

        $newFile = $request->file('file');
        $extension = $newFile->getClientOriginalExtension();
        $newStoragePath = 'project-files/'.Str::uuid().'.'.$extension;
        $project = $file->project;

        try {
            // Borrar fichero antiguo
            Storage::disk('public')->delete($file->storage_path);

            // Guardar nuevo
            Storage::disk('public')->put($newStoragePath, $newFile->get());

            // Actualizar registro
            $file->update([
                'original_name' => $newFile->getClientOriginalName(),
                'storage_path' => $newStoragePath,
                'mime_type' => $newFile->getMimeType(),
                'size_bytes' => $newFile->getSize(),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['file' => 'Error al sustituir el fichero. Inténtalo de nuevo.']);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Fichero sustituido correctamente.');
    }

    /**
     * Eliminar un fichero.
     */
    public function destroy(ProjectFile $file): RedirectResponse
    {
        abort_unless($file->project->user_id === auth()->id(), 403);

        $project = $file->project;

        // Borrar fichero de Storage
        if (Storage::disk('public')->exists($file->storage_path)) {
            Storage::disk('public')->delete($file->storage_path);
        }

        // Eliminar registro BD (CASCADE se encarga de limpiar)
        $file->delete();

        return redirect()->route('projects.show', $project)
            ->with('success', 'Fichero eliminado correctamente.');
    }
}
