<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RestaurantChatbotService
{
    public function __construct(
        private readonly RestaurantKnowledgeService $knowledge
    ) {
    }

    /**
     * Generate a natural-language response using live restaurant data.
     */
    public function reply(string $message): array
    {
        $message = trim($message);

        if ($message === '') {
            return [
                'reply' => 'Please enter a question so I can help.',
                'source' => 'validation',
                'error' => 'empty_message',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | AI Status
        |--------------------------------------------------------------------------
        */

        $enabled = filter_var(
            config(
                'services.restaurant_ai.enabled',
                false
            ),
            FILTER_VALIDATE_BOOL
        );

        if (!$enabled) {
            return [
                'reply' =>
                    'The restaurant assistant is currently unavailable. Please try again later.',
                'source' => 'configuration',
                'error' => 'ai_disabled',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | AI Provider
        |--------------------------------------------------------------------------
        */

        $provider = strtolower(
            trim(
                (string) config(
                    'services.restaurant_ai.provider',
                    'gemini'
                )
            )
        );

        if ($provider !== 'gemini') {
            return [
                'reply' =>
                    'The restaurant assistant is not configured correctly at the moment.',
                'source' => 'configuration',
                'error' => 'unsupported_provider',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Retrieve Restaurant Knowledge
        |--------------------------------------------------------------------------
        */

        $knowledgeLimit = (int) config(
            'services.restaurant_ai.knowledge_limit',
            20
        );

        $knowledgeLimit = max(
            5,
            min(
                $knowledgeLimit,
                100
            )
        );

        try {
            $context = $this->knowledge->plainTextContext(
                $message,
                $knowledgeLimit
            );

        } catch (Throwable $e) {
            report($e);

            Log::error(
                'Restaurant chatbot knowledge scan failed.',
                [
                    'message' => $e->getMessage(),
                ]
            );

            return [
                'reply' =>
                    'I could not check the restaurant information right now. Please try again in a moment.',
                'source' => 'restaurant_database',
                'error' => 'knowledge_scan_failed',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Ask Gemini
        |--------------------------------------------------------------------------
        */

        return $this->askGemini(
            message: $message,
            context: $context
        );
    }

    /**
     * Send restaurant context and user question to Gemini.
     */
    private function askGemini(
        string $message,
        string $context
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Gemini Configuration
        |--------------------------------------------------------------------------
        */

        $key = trim(
            (string) config(
                'services.restaurant_ai.gemini.key',
                ''
            )
        );

        $model = trim(
            (string) config(
                'services.restaurant_ai.gemini.model',
                'gemini-3.6-flash'
            )
        );

        $baseUrl = rtrim(
            trim(
                (string) config(
                    'services.restaurant_ai.gemini.base_url',
                    'https://generativelanguage.googleapis.com/v1beta'
                )
            ),
            '/'
        );

        $timeout = (int) config(
            'services.restaurant_ai.timeout',
            45
        );

        $temperature = (float) config(
            'services.restaurant_ai.temperature',
            0.2
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Gemini Configuration
        |--------------------------------------------------------------------------
        */

        if ($key === '') {
            return [
                'reply' =>
                    'The restaurant assistant is not fully configured yet.',
                'source' => 'configuration',
                'error' => 'missing_gemini_key',
            ];
        }

        if ($model === '') {
            return [
                'reply' =>
                    'The restaurant assistant model has not been configured.',
                'source' => 'configuration',
                'error' => 'missing_gemini_model',
            ];
        }

        if ($baseUrl === '') {
            return [
                'reply' =>
                    'The restaurant assistant connection has not been configured.',
                'source' => 'configuration',
                'error' => 'missing_gemini_url',
            ];
        }

        $timeout = max(
            10,
            min(
                $timeout,
                120
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Gemini Endpoint
        |--------------------------------------------------------------------------
        */

        $url =
            $baseUrl
            . '/models/'
            . rawurlencode($model)
            . ':generateContent';

        /*
        |--------------------------------------------------------------------------
        | Build Prompt
        |--------------------------------------------------------------------------
        */

        $systemPrompt = $this->systemPrompt();

        if (trim($context) === '') {
            $context =
                'No matching restaurant information was found for this question.';
        }

        $prompt =
            "RESTAURANT INFORMATION:\n\n"
            . $context
            . "\n\n"
            . "CUSTOMER OR STAFF QUESTION:\n"
            . $message;

        try {
            /*
            |--------------------------------------------------------------------------
            | Send Request to Gemini
            |--------------------------------------------------------------------------
            */

            $response = Http::withHeaders([
                'x-goog-api-key' => $key,
                'Content-Type' => 'application/json',
            ])
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->retry(
                    2,
                    500,
                    throw: false
                )
                ->post(
                    $url,
                    [
                        'systemInstruction' => [
                            'parts' => [
                                [
                                    'text' => $systemPrompt,
                                ],
                            ],
                        ],

                        'contents' => [
                            [
                                'role' => 'user',

                                'parts' => [
                                    [
                                        'text' => $prompt,
                                    ],
                                ],
                            ],
                        ],

                        'generationConfig' => [
                            'temperature' => $temperature,
                            'topP' => 0.9,
                            'maxOutputTokens' => 1200,
                        ],
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | Handle Gemini Errors
            |--------------------------------------------------------------------------
            */

            if (!$response->successful()) {
                return $this->handleGeminiError(
                    $response,
                    $model,
                    $url
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Extract Response
            |--------------------------------------------------------------------------
            */

            $reply = $this->extractGeminiText(
                $response
            );

            if ($reply === null) {
                Log::warning(
                    'Restaurant chatbot received empty Gemini response.',
                    [
                        'model' => $model,

                        'response' =>
                            mb_substr(
                                $response->body(),
                                0,
                                2000
                            ),
                    ]
                );

                return [
                    'reply' =>
                        'I checked the restaurant information but could not prepare a response. Please try asking the question differently.',
                    'source' => 'restaurant_database',
                    'error' => 'empty_gemini_response',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Clean and Humanise Response
            |--------------------------------------------------------------------------
            */

            $reply = $this->cleanReply(
                $reply
            );

            return [
                'reply' => $reply,
                'source' => 'restaurant_database+gemini',
                'error' => null,
            ];

        } catch (Throwable $e) {
            report($e);

            Log::error(
                'Restaurant chatbot Gemini connection failed.',
                [
                    'message' => $e->getMessage(),
                    'model' => $model,
                ]
            );

            return [
                'reply' =>
                    'I checked the restaurant information, but I could not connect to the assistant service. Please try again shortly.',
                'source' => 'restaurant_database',
                'error' => 'gemini_connection_failed',
            ];
        }
    }

    /**
     * Extract text from Gemini response.
     */
    private function extractGeminiText(
        Response $response
    ): ?string {
        $parts = data_get(
            $response->json(),
            'candidates.0.content.parts',
            []
        );

        if (!is_array($parts)) {
            return null;
        }

        $textParts = [];

        foreach ($parts as $part) {
            if (
                is_array($part)
                && isset($part['text'])
                && is_string($part['text'])
                && trim($part['text']) !== ''
            ) {
                $textParts[] =
                    trim($part['text']);
            }
        }

        if ($textParts === []) {
            return null;
        }

        return trim(
            implode(
                "\n\n",
                $textParts
            )
        );
    }

    /**
     * Clean Gemini output and force plain, human-friendly text.
     */
    private function cleanReply(
        string $reply
    ): string {
        $reply = trim($reply);

        /*
        |--------------------------------------------------------------------------
        | Remove Markdown Bold / Italic
        |--------------------------------------------------------------------------
        */

        $reply = str_replace(
            [
                '**',
                '__',
            ],
            '',
            $reply
        );

        /*
        |--------------------------------------------------------------------------
        | Remove Markdown Headings
        |--------------------------------------------------------------------------
        */

        $reply = preg_replace(
            '/^\s*#{1,6}\s*/m',
            '',
            $reply
        ) ?? $reply;

        /*
        |--------------------------------------------------------------------------
        | Remove Inline Code Formatting
        |--------------------------------------------------------------------------
        */

        $reply = str_replace(
            '`',
            '',
            $reply
        );

        /*
        |--------------------------------------------------------------------------
        | Remove Markdown Bullet Prefixes
        |--------------------------------------------------------------------------
        |
        | Keep the text, but remove unnecessary Markdown formatting.
        |
        */

        $reply = preg_replace(
            '/^\s*[-*]\s+/m',
            '',
            $reply
        ) ?? $reply;

        /*
        |--------------------------------------------------------------------------
        | Remove Numbered Markdown Formatting When Unnecessary
        |--------------------------------------------------------------------------
        */

        $reply = preg_replace(
            '/^\s*\d+\.\s+/m',
            '',
            $reply
        ) ?? $reply;

        /*
        |--------------------------------------------------------------------------
        | Remove Excess Spaces
        |--------------------------------------------------------------------------
        */

        $reply = preg_replace(
            '/[ \t]{2,}/',
            ' ',
            $reply
        ) ?? $reply;

        /*
        |--------------------------------------------------------------------------
        | Remove Excess Blank Lines
        |--------------------------------------------------------------------------
        */

        $reply = preg_replace(
            "/\n{3,}/",
            "\n\n",
            $reply
        ) ?? $reply;

        /*
        |--------------------------------------------------------------------------
        | Clean Spaces Around Punctuation
        |--------------------------------------------------------------------------
        */

        $reply = preg_replace(
            '/\s+([,.!?;:])/',
            '$1',
            $reply
        ) ?? $reply;

        return trim($reply);
    }

    /**
     * Handle Gemini API errors.
     */
    private function handleGeminiError(
        Response $response,
        string $model,
        string $url
    ): array {
        $status = $response->status();

        $json = $response->json();

        $providerMessage = data_get(
            $json,
            'error.message'
        );

        $providerStatus = data_get(
            $json,
            'error.status'
        );

        Log::warning(
            'Restaurant chatbot Gemini request failed.',
            [
                'http_status' => $status,

                'provider_status' =>
                    $providerStatus,

                'provider_message' =>
                    $providerMessage,

                'model' => $model,

                'url' => $url,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Authentication
        |--------------------------------------------------------------------------
        */

        if (
            $status === 401
            || $status === 403
        ) {
            return [
                'reply' =>
                    'The restaurant assistant could not authenticate with the AI service. Please check the API configuration.',
                'source' => 'restaurant_database',
                'error' => 'gemini_authentication_failed',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Invalid Request
        |--------------------------------------------------------------------------
        */

        if ($status === 400) {
            return [
                'reply' =>
                    'The restaurant assistant could not process this request right now.',
                'source' => 'restaurant_database',
                'error' => 'gemini_invalid_request',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Model Not Found
        |--------------------------------------------------------------------------
        */

        if ($status === 404) {
            return [
                'reply' =>
                    'The restaurant assistant model is currently unavailable. Please check the configured AI model.',
                'source' => 'restaurant_database',
                'error' => 'gemini_model_not_found',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Rate Limit
        |--------------------------------------------------------------------------
        */

        if ($status === 429) {
            return [
                'reply' =>
                    'The restaurant assistant is receiving too many requests at the moment. Please try again shortly.',
                'source' => 'restaurant_database',
                'error' => 'gemini_rate_limit',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Gemini Service Error
        |--------------------------------------------------------------------------
        */

        if ($status >= 500) {
            return [
                'reply' =>
                    'The restaurant assistant is temporarily unavailable. Please try again in a moment.',
                'source' => 'restaurant_database',
                'error' => 'gemini_server_error',
            ];
        }

        return [
            'reply' =>
                'I checked the restaurant information but could not generate a response right now.',
            'source' => 'restaurant_database',
            'error' => 'gemini_request_failed',
        ];
    }

    /**
     * Grounding, tone and response rules.
     */
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a helpful assistant built into a restaurant management system.

Answer questions using the supplied live restaurant information.

Your responses should sound natural, warm and professional. Speak like a knowledgeable restaurant staff member rather than a database or technical system.

RESPONSE STYLE:

1. Use natural conversational English.

2. Give the answer directly without unnecessary introductions such as:
   "According to the database"
   "The database indicates"
   "Based on the provided context"
   "According to the restaurant system"

3. Do not sound robotic.

4. Do not repeat the user's question unnecessarily.

5. Keep simple answers short.

6. Give more detail only when the question needs it.

7. Where appropriate, use natural phrases such as:
   "Water is currently available for 5,000 UGX."
   "There are 3 pending orders at the moment."
   "Chicken Pizza is currently unavailable."
   "There are no reservations recorded for today."

8. If several records match, present them clearly and naturally.

9. Use complete sentences.

10. Do not use Markdown.

11. Do not use Markdown bold formatting.

12. Do not use ** symbols.

13. Do not use Markdown headings.

14. Do not use backticks.

15. Avoid unnecessary bullet lists for short answers.

16. Return plain text only.

DATA RULES:

17. Treat the supplied restaurant information as the primary source of truth.

18. Do not invent menu items, prices, availability, stock quantities, order statuses, payment statuses, reservations, customer details, supplier details, opening hours or financial information.

19. If the requested information cannot be found, say so naturally. For example:
   "I couldn't find that information in the restaurant system."
   or
   "That information is not currently recorded."

20. Never expose passwords, API keys, tokens, database credentials or confidential configuration.

21. When discussing menu prices or financial amounts, use the restaurant's configured currency where available.

22. Only say a menu item is available if the supplied information confirms it.

23. For orders, payments, reservations and stock, rely only on the supplied live information because these details can change frequently.

24. Convert database values into friendly language instead of showing raw JSON or technical field names.

25. If a database value is 1 or true for availability, say "available". If it is 0 or false, say "unavailable".

26. Avoid mentioning database column names such as is_available, grand_total, balance_due, created_at or order_type unless the user specifically asks for technical details.

27. Format amounts naturally. For example:
   5000 should normally be presented as 5,000 UGX when the currency is UGX.

28. If only one matching item exists, answer naturally in the singular.

29. If multiple matching items exist, provide a clear summary.

30. Do not claim that you checked information that was not included in the supplied restaurant information.
PROMPT;
    }
}