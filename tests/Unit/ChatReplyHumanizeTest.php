<?php

namespace Tests\Unit;

use App\Services\Ai\RestaurantAssistant;
use PHPUnit\Framework\TestCase;

class ChatReplyHumanizeTest extends TestCase
{
    public function test_markdown_dashes_and_emojis_become_plain_sentences(): void
    {
        $raw = "## Our picks 🍽️\n\nHere are a few favourites — all made fresh:\n\n- **Rolex** – chapati rolled with eggs (UGX 6,000)\n* *Luwombo* — slow-cooked chicken\n1. Fresh juice\n\nWe're open 8–10 daily! 😊";

        $this->assertSame(
            "Our picks. Here are a few favourites, all made fresh: Rolex, chapati rolled with eggs (UGX 6,000). Luwombo, slow-cooked chicken. Fresh juice. We're open 8 to 10 daily!",
            RestaurantAssistant::humanize($raw)
        );
    }

    public function test_plain_replies_are_left_alone(): void
    {
        $plain = 'Our Rolex costs UGX 6,000. Would you like to add a fresh juice?';

        $this->assertSame($plain, RestaurantAssistant::humanize($plain));
    }

    public function test_hyphenated_words_and_links_are_kept_readable(): void
    {
        $this->assertSame(
            'Try our slow-cooked stew, see the full menu.',
            RestaurantAssistant::humanize("Try our slow-cooked stew -- see the [full menu](https://example.com/menu).")
        );
    }
}
