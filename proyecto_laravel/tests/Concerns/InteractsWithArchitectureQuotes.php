<?php

namespace Tests\Concerns;

use App\Models\Architecture;
use App\Models\ArchitectureItem;
use App\Models\Product;
use App\Models\ProductAvailability;
use App\Models\Project;
use App\Models\Provider;
use App\Models\Quote;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;

/**
 * Recursos y observadores compartidos por las pruebas de aceptación de la
 * etapa `feature/architecture-quotes` (sección 6 de la especificación):
 * construcción de catálogo/arquitecturas manuales y observación externa del
 * flujo de emisión desde la ficha de la arquitectura.
 *
 * Las pruebas nunca dependen de decisiones privadas de implementación del
 * servicio de emisión: la acción de emitir se localiza como comportamiento
 * observable (un formulario POST propio de la ficha de la arquitectura, una
 * vez descartado el formulario común de cierre de sesión) y los efectos se
 * comprueban sobre las tablas `quotes` / `quote_lines` ya existentes.
 */
trait InteractsWithArchitectureQuotes
{
    /**
     * @param  list<array{product_id: int, quantity: int, config: array<string, mixed>}>  $spec
     */
    protected function saveManualArchitecture(User $owner, Project $project, Region $region, array $spec, string $name = 'Arquitectura de emisión'): Architecture
    {
        $architecture = Architecture::query()->create([
            'project_id' => $project->id,
            'region_id' => $region->id,
            'name' => $name,
            'source' => Architecture::SOURCE_MANUAL,
        ]);

        foreach ($spec as $row) {
            ArchitectureItem::query()->create([
                'architecture_id' => $architecture->id,
                'product_id' => $row['product_id'],
                'quantity' => $row['quantity'],
                'config' => is_array($row['config']) ? $row['config'] : [],
            ]);
        }

        return $architecture;
    }

    /**
     * Localiza la acción de emisión desde la ficha de la arquitectura
     * (sección 6: «se añade una acción independiente desde el detalle de una
     * arquitectura manual») y devuelve la URL y los campos ocultos del
     * formulario de emisión.
     *
     * Toda la búsqueda es de comportamiento observable: se descarta el
     * formulario común de cierre de sesión y se exige que quede exactamente
     * una acción POST propia de la ficha. Si la implementación interpone un
     * paso de confirmación por enlace GET, se sigue un enlace que mencione
     * presupuesto/emisión y la búsqueda continúa ahí.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    protected function quoteEmissionAction(User $owner, Architecture $architecture): array
    {
        $showUrl = route('architectures.show', $architecture);

        $showResponse = $this->actingAs($owner)->get($showUrl);
        $showResponse->assertOk();

        $html = $showResponse->getContent();
        $candidate = $this->firstEmissionForm($html, $showUrl);

        if ($candidate === null) {
            foreach ($this->linksAboutEmission($html) as $link) {
                $confirmResponse = $this->actingAs($owner)->get($link);

                if (! $confirmResponse->isOk()) {
                    continue;
                }

                $candidate = $this->firstEmissionForm($confirmResponse->getContent(), $link);

                if ($candidate !== null) {
                    break;
                }
            }
        }

        if ($candidate === null) {
            $seen = array_column($this->extractPostForms($html, $showUrl), 'action');

            $this->fail(
                'La especificación (sección 6) añade «una acción independiente desde el '
                .'detalle de una arquitectura manual», así que la ficha de la arquitectura '
                .'debe ofrecer el flujo de emisión (formulario POST propio o enlace a su '
                .'página de confirmación). Formularios POST vistos en la ficha: '
                .json_encode($seen),
            );
        }

        return $candidate;
    }

    /**
     * Manda el formulario de emisión ya localizado (útil cuando la acción
     * se descubre ANTES de cambiar el estado del servidor, como al eliminar
     * el proyecto propietario).
     */
    protected function postEmissionTo(User $user, array $action, array $extraFields = []): TestResponse
    {
        $payload = [...$action['fields'], ...$extraFields];

        if (isset($action['fields']['_token'])) {
            $payload['_token'] = $action['fields']['_token'];
        }

        return $this->actingAs($user)->post($action['action'], $payload);
    }

    /**
     * Emite un presupuesto desde la arquitectura siguiendo el flujo
     * observable. Los campos extra se añaden a la petición para comprobar
     * (criterio 10 y sección 7) que el servidor no confía en datos del
     * navegador.
     */
    protected function emitQuoteFrom(User $user, Architecture $architecture, array $extraFields = []): TestResponse
    {
        $action = $this->quoteEmissionAction($user, $architecture);

        return $this->postEmissionTo($user, $action, $extraFields);
    }

    /**
     * Manda el formulario de emisión SIN autenticarse (criterio 10: la
     * acción debe exigir autenticación aunque la URL se filtre).
     */
    protected function emitQuoteAsGuest(User $owner, Architecture $architecture): TestResponse
    {
        $action = $this->quoteEmissionAction($owner, $architecture);

        auth()->guard()->forgetUser();
        $this->assertGuest();

        return $this->post($action['action'], $action['fields']);
    }

    /**
     * Devuelve el único formulario POST propio de una página (sin el cierre
     * de sesión común) o null si no existe.
     *
     * @return array{action: string, fields: array<string, string>}|null
     */
    private function firstEmissionForm(string $html, string $pageUrl): ?array
    {
        $candidates = array_values(array_filter(
            $this->extractPostForms($html, $pageUrl),
            fn (array $form): bool => ! Str::contains($form['action'], 'salir'),
        ));

        if (count($candidates) !== 1) {
            if (count($candidates) > 1) {
                $this->fail(
                    'La ficha expone varias acciones POST propias ('.json_encode(
                        array_column($candidates, 'action'),
                    ).'); la especificación pide UNA acción independiente de emisión y el '
                    .'observador no puede adivinar cuál es la correcta.',
                );
            }

            return null;
        }

        return $candidates[0];
    }

    /**
     * Formularios POST de un HTML con sus campos ocultos resueltos.
     *
     * @return list<array{action: string, fields: array<string, string>}>
     */
    private function extractPostForms(string $html, string $fallbackUrl): array
    {
        preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/si', $html, $groups, PREG_SET_ORDER);

        $forms = [];

        foreach ($groups as [$attributes, $inner]) {
            if (! preg_match('/method\s*=\s*["\']?post\b/i', $attributes)) {
                continue;
            }

            $action = preg_match('/action\s*=\s*["\']([^"\']*)["\']/i', $attributes, $match)
                ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5)
                : $fallbackUrl;

            $forms[] = ['action' => $action, 'fields' => $this->hiddenFieldsOf($inner)];
        }

        return $forms;
    }

    /**
     * @return array<string, string>
     */
    private function hiddenFieldsOf(string $formInner): array
    {
        $fields = [];

        if (! preg_match_all('/<input\b([^>]*)>/i', $formInner, $tags)) {
            return $fields;
        }

        foreach ($tags[1] as $attributes) {
            $type = preg_match('/type\s*=\s*["\']([\w-]+)["\']/i', $attributes, $t)
                ? mb_strtolower($t[1])
                : '';

            if ($type !== '' && $type !== 'hidden') {
                continue;
            }

            $name = preg_match('/name\s*=\s*["\']([^"\']+)["\']/i', $attributes, $n) ? $n[1] : null;

            if ($name === null) {
                continue;
            }

            $value = preg_match('/value\s*=\s*["\']([^"\']*)["\']/i', $attributes, $v)
                ? html_entity_decode($v[1], ENT_QUOTES | ENT_HTML5)
                : '';

            $fields[$name] = $value;
        }

        return $fields;
    }

    /**
     * Enlaces de una página que apuntan al flujo de presupuesto (para el
     * caso de una ficha que delega en una pantalla de confirmación).
     *
     * @return list<string>
     */
    private function linksAboutEmission(string $html): array
    {
        if (! preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $html, $links, PREG_SET_ORDER)) {
            return [];
        }

        $hrefs = [];
        $terms = ['presupuesto', 'emitir', 'emision', 'emisión'];

        foreach ($links as [$match, $href, $text]) {
            $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5);

            if ($href === '#' || $href === '') {
                continue;
            }

            $plain = mb_strtolower(trim(strip_tags($text)));

            foreach ($terms as $term) {
                if (Str::contains($plain, $term) || Str::contains($href, $term)) {
                    $hrefs[] = $href;

                    break;
                }
            }
        }

        return array_values(array_unique($hrefs));
    }

    /**
     * @return list<string>
     */
    protected function allSessionErrors(): array
    {
        $bag = session('errors');

        if ($bag === null) {
            return [];
        }

        if ($bag instanceof ViewErrorBag) {
            return $bag->all();
        }

        if ($bag instanceof MessageBag) {
            return $bag->all();
        }

        if (is_array($bag)) {
            return collect($bag)->flatten()->all();
        }

        return [$bag];
    }

    /**
     * Comprueba que los mensajes de error citan las causas dadas
     * (sección 7: mensaje concreto, no silencio).
     *
     * @param  list<string>  $errors
     * @param  list<string>  $needles
     */
    protected function assertMessagesMention(array $errors, array $needles, string $mode = 'all', ?string $context = null): void
    {
        $this->assertNotEmpty(
            $errors,
            'El bloqueo debe producir mensajes de error'.($context ? ' — '.$context : ''),
        );

        foreach ($needles as $needle) {
            $found = false;

            foreach ($errors as $message) {
                if (Str::contains($message, $needle) || Str::contains(mb_strtolower($message), mb_strtolower($needle))) {
                    $found = true;

                    break;
                }
            }

            if ($mode === 'any') {
                if ($found) {
                    return;
                }

                $this->fail(
                    'Ningún mensaje de error cita una de las causas ("'
                    .implode('" / "', $needles).'"). Mensajes recibidos: ['
                    .implode(' | ', $errors).']'.($context ? ' — '.$context : ''),
                );
            }

            $this->assertTrue(
                $found,
                'El motivo de bloqueo debe citar la causa ("'.$needle.'"). '
                .'Mensajes recibidos: ['.implode(' | ', $errors).']'.($context ? ' — '.$context : ''),
            );
        }
    }

    /**
     * Ninguna fila historic de presupuesto, ni completa ni parcial.
     */
    protected function assertNothingQuoted(): void
    {
        $this->assertDatabaseCount('quotes', 0);
        $this->assertDatabaseCount('quote_lines', 0);
    }

    /**
     * La respuesta muestra el importe en euros con dos decimales, el
     * formato ya usado en las pantallas del proyecto (number_format .' €').
     */
    protected function assertSeesAmount(TestResponse $response, int $cents): void
    {
        $response->assertSee(number_format($cents / 100, 2).' €', false);
    }

    /**
     * Presupuesto emitido desde la arquitectura: el último creado.
     */
    protected function lastQuote(): Quote
    {
        return Quote::query()->latest('id')->firstOrFail();
    }

    /**
     * Región y proyectos con datos aislados por prueba.
     */
    protected function regionFor(string $prefix): Region
    {
        $suffix = substr(bin2hex(random_bytes(5)), 0, 6);

        return Region::query()->create([
            'code' => $prefix.'-'.$suffix,
            'name' => 'Región '.$prefix.' ('.$suffix.')',
            'country' => 'ES',
            'is_eu' => true,
        ]);
    }

    protected function projectFor(User $user, Region $region, string $maxBudget = '1000.00'): Project
    {
        return Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
            'max_budget' => $maxBudget,
        ]);
    }

    protected function productWithTariff(
        Region $region,
        string $name,
        string $type,
        int $priceCents,
        string $pricingModel = 'fijo_mensual',
        ?int $oneTimeCents = null,
        bool $active = true,
        array $extraRegions = [],
    ): Product {
        $suffix = substr(bin2hex(random_bytes(5)), 0, 6);
        $provider = Provider::query()->firstOrCreate(['name' => 'Proveedor de emisión']);

        $product = Product::query()->create([
            'code' => Str::slug($name).'-'.$suffix,
            'name' => $name,
            'type' => $type,
            'unit_price' => $priceCents / 100,
            'provider_id' => $provider->id,
            'active' => $active,
        ]);

        ProductAvailability::query()->create([
            'product_id' => $product->id,
            'region_id' => $region->id,
            'available' => true,
            'price_cents' => $priceCents,
            'pricing_model' => $pricingModel,
        ] + ($oneTimeCents !== null ? ['one_time_price_cents' => $oneTimeCents] : []));

        foreach ($extraRegions as $extraRegion) {
            ProductAvailability::query()->create([
                'product_id' => $product->id,
                'region_id' => $extraRegion->id,
                'available' => true,
                'price_cents' => $priceCents,
                'pricing_model' => $pricingModel,
            ] + ($oneTimeCents !== null ? ['one_time_price_cents' => $oneTimeCents] : []));
        }

        return $product;
    }
}
