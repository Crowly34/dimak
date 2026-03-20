<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Gemini)]
#[UseCheapestModel]
#[Temperature(0)]
#[MaxTokens(256)]
class QueryParser implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You parse repair shop search queries into structured filters.

        Domain: a computer/phone repair shop. Clients bring devices (laptops, desktops, phones, tablets) for repair. Each visit creates an order (with a folio number) containing one or more tickets (one per device).

        Extract ONLY what is explicitly mentioned. Leave everything else null.

        Status values and their synonyms:
        - "pending_diagnosis": pending, pendiente, diagnóstico, nuevo
        - "in_progress": in progress, en progreso, trabajando
        - "waiting_part": waiting part, esperando pieza, pieza
        - "waiting_approval": waiting approval, esperando aprobación, aprobación
        - "ready": ready, listo, lista, terminado
        - "delivered": delivered, entregado, entregada
        - "no_repair": no repair, sin reparación, no se pudo
        - "warranty": warranty, garantía

        Payment synonyms:
        - paid/pagado/pagada → paid = true
        - unpaid/no pagado/sin pagar/debe → paid = false

        The user may write in Spanish, English, or mixed. Interpret naturally.
        If the input looks like a number or code, treat it as a folio.
        For fields not mentioned in the query, return an empty string. For enum fields (status, paid), return "none" if not mentioned.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_name' => $schema->string()->description('Client name or partial name, or empty string if not mentioned'),
            'device' => $schema->string()->description('Device type: laptop, macbook, iphone, pc, etc., or empty string if not mentioned'),
            'folio' => $schema->string()->description('Order folio/number, or empty string if not mentioned'),
            'status' => $schema->string()->enum([
                'pending_diagnosis', 'in_progress', 'waiting_part',
                'waiting_approval', 'ready', 'delivered', 'no_repair', 'warranty', 'none',
            ])->description('Ticket status filter, or "none" if not mentioned'),
            'paid' => $schema->string()->enum(['true', 'false', 'none'])->description('Payment status: "true" if paid, "false" if unpaid, "none" if not mentioned'),
        ];
    }
}
