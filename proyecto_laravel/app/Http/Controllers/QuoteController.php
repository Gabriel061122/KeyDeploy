<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Quote;
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
     * Ver un presupuesto emitido.
     */
    public function show(Quote $quote): View
    {
        abort_unless($quote->user_id === auth()->id(), 403);

        $quote->load('lines', 'project');

        return view('quote.show', compact('quote'));
    }
}
