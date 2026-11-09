<?php

namespace App\Http\Controllers;

use App\Models\Architecture;
use App\Models\Project;
use App\Models\Quote;
use App\Services\ArchitectureQuoteService;
use App\Services\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function __construct(protected QuoteService $quoteService) {}

    /**
     * Emitir un presupuesto desde el carrito del usuario.
     */
    public function emit(Project $project): RedirectResponse
    {
        abort_unless($project->user_id === auth()->id(), 403);

        try {
            $quote = $this->quoteService->emitFromCart(auth()->user(), $project);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['emit' => $e->getMessage()]);
        }

        return redirect()->route('quotes.show', $quote)
            ->with('success', 'Presupuesto emitido correctamente.');
    }

    /**
     * Emitir un presupuesto desde una arquitectura manual guardada.
     *
     * Es una entrada independiente del carrito: recalcula y revalida con
     * los datos persistidos de la arquitectura, el proyecto y el catálogo.
     */
    public function emitFromArchitecture(
        Architecture $architecture,
        ArchitectureQuoteService $architectureQuoteService,
    ): RedirectResponse {
        $this->authorize('emitQuote', $architecture);

        try {
            $result = $architectureQuoteService->emitFromArchitecture(auth()->user(), $architecture);
        } catch (\Exception $e) {
            return redirect()
                ->route('architectures.show', $architecture)
                ->withErrors(['emit' => $e->getMessage()]);
        }

        $redirect = redirect()
            ->route('quotes.show', $result['quote'])
            ->with('success', 'Presupuesto emitido desde la arquitectura.');

        if ($result['warnings'] !== []) {
            $redirect->with('warning', implode(' ', $result['warnings']));
        }

        return $redirect;
    }

    /**
     * Ver un presupuesto emitido.
     */
    public function show(Quote $quote): View
    {
        abort_unless($quote->user_id === auth()->id(), 403);

        $quote->load('lines', 'project');

        return view('quote.show', compact('quote'));
    }
}
