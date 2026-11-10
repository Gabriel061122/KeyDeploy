<?php

use App\Http\Controllers\ArchitectureController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RequirementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de KeyDeploy
|--------------------------------------------------------------------------
| EP04: proyectos, requerimientos y arquitecturas manuales del CLIENTE.
| Autenticación (RF01), acceso solo a recursos propios (RF17/RN09) y
| formulario dinámico de requerimientos con peticiones HTTP tradicionales
| (GET), sin JavaScript.
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Registro público: solo crea cuentas CLIENTE (RF14).
Route::middleware('guest')->group(function () {
    Route::get('registrarse', [AuthController::class, 'showRegister'])->name('register');
    Route::post('registrarse', [AuthController::class, 'register'])->name('register.store');
    Route::get('acceso', [AuthController::class, 'showLogin'])->name('login');
    Route::post('acceso', [AuthController::class, 'login'])->name('login.store');
});

Route::post('salir', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    // La papelera se registra antes del recurso para que "trash" no se
    // interprete como identificador de proyecto.
    Route::get('papelera/proyectos', [ProjectController::class, 'trash'])->name('projects.trash');

    Route::resource('proyectos', ProjectController::class)
        ->names('projects')
        ->parameter('proyectos', 'project')
        ->whereNumber('project');

    Route::post('proyectos/{project}/restaurar', [ProjectController::class, 'restore'])
        ->name('projects.restore')
        ->withTrashed()
        ->whereNumber('project');

    // Página de confirmación previa al borrado, en lugar de JavaScript.
    Route::get('proyectos/{project}/eliminar', [ProjectController::class, 'confirmDelete'])
        ->name('projects.delete')
        ->whereNumber('project');

    // Requerimientos anidados en el proyecto, con enlace reforzado.
    Route::resource('proyectos/{project}/requerimientos', RequirementController::class)
        ->names('projects.requirements')
        ->parameter('requerimientos', 'requirement')
        ->scoped()
        ->whereNumber('project');

    Route::get('proyectos/{project}/requerimientos/{requirement}/eliminar', [RequirementController::class, 'confirmDelete'])
        ->name('projects.requirements.delete')
        ->scopeBindings()
        ->whereNumber(['project', 'requirement']);

    // Recarga del formulario dinámico conservando lo tecleado, sin guardar.
    Route::post('proyectos/{project}/requerimientos/tipo', [RequirementController::class, 'refreshType'])
        ->name('projects.requirements.refresh')
        ->whereNumber('project');

    Route::put('proyectos/{project}/requerimientos/{requirement}/tipo', [RequirementController::class, 'refreshTypeForEdit'])
        ->name('projects.requirements.refresh.edit')
        ->scopeBindings()
        ->whereNumber(['project', 'requirement']);

    // Arquitecturas manuales: alta en tres pasos sin JavaScript.
    Route::get('proyectos/{project}/arquitecturas/crear', [ArchitectureController::class, 'create'])
        ->name('projects.architectures.create')
        ->whereNumber('project');

    Route::post('proyectos/{project}/arquitecturas/region', [ArchitectureController::class, 'refresh'])
        ->name('projects.architectures.refresh')
        ->whereNumber('project');

    Route::post('proyectos/{project}/arquitecturas/revisar', [ArchitectureController::class, 'preview'])
        ->name('projects.architectures.review')
        ->whereNumber('project');

    Route::get('proyectos/{project}/arquitecturas/revisar', [ArchitectureController::class, 'check'])
        ->name('projects.architectures.check')
        ->whereNumber('project');

    Route::post('proyectos/{project}/arquitecturas', [ArchitectureController::class, 'store'])
        ->name('projects.architectures.store')
        ->whereNumber('project');

    Route::get('proyectos/{project}/arquitecturas', [ArchitectureController::class, 'index'])
        ->name('projects.architectures.index')
        ->whereNumber('project');

    Route::get('arquitecturas/{architecture}', [ArchitectureController::class, 'show'])
        ->name('architectures.show')
        ->whereNumber('architecture');

    // Emisión de presupuesto desde una arquitectura manual guardada.
    Route::post('arquitecturas/{architecture}/presupuesto', [QuoteController::class, 'emitFromArchitecture'])
        ->name('architectures.quotes.emit')
        ->whereNumber('architecture');

    // Carrito persistente (EP05)
    Route::get('carrito', [CartItemController::class, 'index'])->name('cart.index');
    Route::post('carrito', [CartItemController::class, 'store'])->name('cart.store');
    Route::patch('carrito/{item}', [CartItemController::class, 'update'])->name('cart.update');
    Route::delete('carrito/{item}', [CartItemController::class, 'destroy'])->name('cart.destroy');

    // Presupuesto como snapshot (EP05)
    Route::post('proyectos/{project}/presupuesto/emitir', [QuoteController::class, 'emit'])
        ->name('quotes.emit')
        ->whereNumber('project');
    Route::get('presupuestos/{quote}', [QuoteController::class, 'show'])
        ->name('quotes.show')
        ->whereNumber('quote');

    // Ficheros adjuntos (EP05)
    Route::post('proyectos/{project}/ficheros', [ProjectFileController::class, 'upload'])
        ->name('files.upload')
        ->whereNumber('project');
    Route::get('ficheros/{file}', [ProjectFileController::class, 'show'])
        ->name('files.show')
        ->whereNumber('file');
    Route::post('ficheros/{file}/sustituir', [ProjectFileController::class, 'replace'])
        ->name('files.replace')
        ->whereNumber('file');
    Route::delete('ficheros/{file}', [ProjectFileController::class, 'destroy'])
        ->name('files.destroy')
        ->whereNumber('file');
});
