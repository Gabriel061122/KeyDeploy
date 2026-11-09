<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T08: Subida de ficheros con Storage::fake y UploadedFile::fake.
 *
 * Verificar subida válida, sustitución, eliminación y rechazo de tipos
 * o tamaños no permitidos.
 */
class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * T08a: Subir un fichero válido lo guarda en Storage y crea el
     * registro en project_files con los metadatos correctos.
     */
    public function test_upload_valid_file_stores_and_records(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post(route('files.upload', $project), [
                'file' => $file,
            ])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHas('success', 'Fichero subido correctamente.');

        // Verificar que el fichero existe en Storage
        $file = ProjectFile::first();
        Storage::disk('public')->assertExists($file->storage_path);

        // Verificar el registro en BD
        $this->assertDatabaseCount('project_files', 1);
        $this->assertDatabaseHas('project_files', [
            'project_id' => $project->id,
            'original_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
        ]);
        // El tamaño real del archivo fake puede variar; verificar que es > 0
        $this->assertGreaterThan(0, ProjectFile::first()->size_bytes);
    }

    /**
     * T08b: Subir un fichero con tipo MIME no permitido es rechazado.
     */
    public function test_upload_rejects_invalid_mime_type(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $this->actingAs($user)
            ->post(route('files.upload', $project), [
                'file' => $file,
            ])
            ->assertSessionHasErrors(['file' => 'El fichero debe ser PDF, PNG, JPG, DOCX o MD.']);

        $this->assertDatabaseCount('project_files', 0);
    }

    /**
     * T08c: Subir un fichero que supera el tamaño máximo es rechazado.
     */
    public function test_upload_rejects_file_too_large(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // 10MB + 1 byte (límite es 10MB = 10240KB)
        $file = UploadedFile::fake()->create('grande.pdf', 10241 * 1024, 'application/pdf');

        $this->actingAs($user)
            ->post(route('files.upload', $project), [
                'file' => $file,
            ])
            ->assertSessionHasErrors(['file' => 'El fichero no puede superar los 10 MB.']);

        $this->assertDatabaseCount('project_files', 0);
    }

    /**
     * T08d: Sustituir un fichero borra el antiguo y guarda el nuevo.
     */
    public function test_replace_file_updates_storage_and_record(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Subir fichero original
        $file1 = UploadedFile::fake()->create('original.pdf', 100, 'application/pdf');
        $this->actingAs($user)
            ->post(route('files.upload', $project), ['file' => $file1])
            ->assertRedirect();

        $projectFile = ProjectFile::first();
        $oldPath = $projectFile->storage_path;

        // Sustituir
        $file2 = UploadedFile::fake()->create('actualizado.pdf', 200, 'application/pdf');
        $this->actingAs($user)
            ->post(route('files.replace', $projectFile), ['file' => $file2])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHas('success', 'Fichero sustituido correctamente.');

        // El fichero antiguo fue borrado
        Storage::disk('public')->assertMissing($oldPath);

        // El nuevo fichero existe
        $projectFile->refresh();
        Storage::disk('public')->assertExists($projectFile->storage_path);

        // El registro se actualizó
        $this->assertSame('actualizado.pdf', $projectFile->original_name);
        $this->assertGreaterThan(0, $projectFile->size_bytes);
    }

    /**
     * T08e: Eliminar un fichero borra el archivo y el registro.
     */
    public function test_destroy_file_removes_storage_and_record(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('eliminar.pdf', 100, 'application/pdf');
        $this->actingAs($user)
            ->post(route('files.upload', $project), ['file' => $file])
            ->assertRedirect();

        $projectFile = ProjectFile::first();
        $path = $projectFile->storage_path;

        $this->actingAs($user)
            ->delete(route('files.destroy', $projectFile))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHas('success', 'Fichero eliminado correctamente.');

        // El fichero fue borrado de Storage
        Storage::disk('public')->assertMissing($path);

        // El registro fue eliminado
        $this->assertDatabaseMissing('project_files', ['id' => $projectFile->id]);
        $this->assertDatabaseCount('project_files', 0);
    }

    /**
     * T08f: Un usuario no autorizado no puede subir ficheros a proyecto ajeno.
     */
    public function test_unauthorized_user_cannot_upload(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $otherUser->id]);

        $file = UploadedFile::fake()->create('hack.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post(route('files.upload', $project), ['file' => $file])
            ->assertForbidden();

        $this->assertDatabaseCount('project_files', 0);
    }
}
