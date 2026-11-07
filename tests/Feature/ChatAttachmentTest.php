<?php

namespace Tests\Feature;

use App\Services\Settings\RestaurantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class ChatAttachmentTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function publicRestaurant(array $ai = []): \App\Models\Restaurant
    {
        [$restaurant] = $this->restaurantWithBranch('C');
        config(['restaurant.public_code' => $restaurant->code]);
        $this->menuItem($restaurant, 'Rolex', 6000);
        if ($ai !== []) {
            app(RestaurantSettings::class)->save($restaurant->id, 'ai', $ai + ['enabled' => true]);
        }

        return $restaurant;
    }

    public function test_photo_is_sent_to_gemini_inline(): void
    {
        $this->publicRestaurant(['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'key_gemini' => 'g-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'That looks like our Rolex!']]]]]])]);

        $this->post(route('api.v1.chat.message'), ['file' => UploadedFile::fake()->image('dish.png', 40, 40)], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['reply' => 'That looks like our Rolex!']);

        Http::assertSent(function (HttpRequest $request): bool {
            $parts = data_get($request->data(), 'contents.0.parts');

            return data_get($parts, '0.inlineData.mimeType') === 'image/png'
                && base64_decode((string) data_get($parts, '0.inlineData.data'), true) !== false
                && str_contains((string) data_get($parts, '1.text'), 'attached a photo');
        });
    }

    public function test_photo_is_sent_to_openai_compatible_providers_as_an_image_url(): void
    {
        $this->publicRestaurant(['provider' => 'groq', 'model' => 'llama-3.1-8b-instant', 'key_groq' => 'gsk']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'Nice photo!']]]])]);

        $this->post(route('api.v1.chat.message'), ['message' => 'Is this on your menu?', 'file' => UploadedFile::fake()->image('dish.jpg', 30, 30)], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['reply' => 'Nice photo!']);

        Http::assertSent(fn (HttpRequest $request) => str_starts_with((string) data_get($request->data(), 'messages.1.content.1.image_url.url'), 'data:image/jpeg;base64,')
            && data_get($request->data(), 'messages.1.content.0.text') === 'Is this on your menu?');
    }

    public function test_pdf_on_a_photo_only_provider_gets_a_friendly_reply(): void
    {
        $this->publicRestaurant(['provider' => 'openai', 'model' => 'gpt-4o-mini', 'key_openai' => 'sk']);
        Http::fake();

        $this->post(route('api.v1.chat.message'), ['file' => UploadedFile::fake()->createWithContent('menu.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('reply', fn ($reply) => str_contains($reply, 'only read photos'));
        Http::assertNothingSent();
    }

    public function test_text_only_model_failure_is_explained_without_switching_models(): void
    {
        $restaurant = $this->publicRestaurant(['provider' => 'groq', 'model' => 'llama-3.1-8b-instant', 'key_groq' => 'gsk']);
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'model llama-3.1-8b-instant does not support image input']], 400)]);

        $this->post(route('api.v1.chat.message'), ['file' => UploadedFile::fake()->image('dish.png')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('reply', fn ($reply) => str_contains($reply, 'could not read that file'));
        $this->assertSame('llama-3.1-8b-instant', app(RestaurantSettings::class)->get($restaurant->id, 'ai', 'model'));
    }

    public function test_attachment_without_ai_and_validation(): void
    {
        $this->publicRestaurant();
        Http::fake();

        $this->post(route('api.v1.chat.message'), ['file' => UploadedFile::fake()->image('dish.png')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('reply', fn ($reply) => str_contains($reply, 'AI assistant is switched on'));

        $this->post(route('api.v1.chat.message'), ['file' => UploadedFile::fake()->createWithContent('notes.txt', 'hello')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
        $this->post(route('api.v1.chat.message'), ['file' => UploadedFile::fake()->create('huge.png', 6000, 'image/png')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
        $this->postJson(route('api.v1.chat.message'), [])->assertStatus(422)->assertJsonValidationErrors('message');
        Http::assertNothingSent();
    }

    public function test_staff_chat_accepts_attachments(): void
    {
        $restaurant = $this->publicRestaurant(['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'key_gemini' => 'g-key']);
        $staff = $this->staff($restaurant, null);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Got it.']]]]]])]);

        $this->actingAs($staff)->post(route('admin.chatbot.ask'), ['file' => UploadedFile::fake()->image('receipt.png')], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['reply' => 'Got it.']);
    }
}
