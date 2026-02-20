<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.0-flash-lite')]
class OrderImportAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You analyze Mac repair shop service notes written in Spanish or a mix of Spanish and English. '
            .'Extract the most likely repair status, physical location, and delivery date from the text. '
            .'Return only the JSON object as specified.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['pending_diagnosis', 'in_progress', 'waiting_part', 'waiting_approval', 'ready', 'delivered', 'no_repair', 'warranty'])
                ->required(),
            'location' => $schema->string()
                ->enum(['shop', 'lab', 'client', 'delivered'])
                ->required(),
            'delivered_at' => $schema->string()
                ->description('ISO date YYYY-MM-DD if a delivery date is found in the text, otherwise empty string')
                ->required(),
        ];
    }
}
