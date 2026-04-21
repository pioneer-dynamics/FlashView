<?php

namespace Tests\Feature\Regressions;

use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * PIO-76: On the decrypt screen, a wrong-password error was replaced almost
 * instantly by the generic retrieve-message screen, so the user could not read
 * it. The fix surfaces the wrong-password error via a local `decryptError` ref
 * rendered as a persistent `<Alert>` at the top of the form — this survives
 * follow-up Inertia visits that would otherwise consume Laravel's single-use
 * session flash.
 *
 * Note: the shipping user-facing copy uses a U+2014 em dash between "password"
 * and "this secret". The regex below deliberately uses \W+ between words so a
 * future hyphen normalisation does not silently break this test.
 */
class PIO76Test extends TestCase
{
    use RefreshDatabase;

    public function test_secret_form_keeps_wrong_password_error_on_local_ref(): void
    {
        $contents = file_get_contents(resource_path('js/Pages/Secret/SecretForm.vue'));

        $this->assertStringContainsString(
            'const decryptError = ref(null)',
            $contents,
            'SecretForm.vue must declare a local decryptError ref so the wrong-password error survives follow-up Inertia visits that consume the single-use Laravel flash.'
        );

        $this->assertMatchesRegularExpression(
            '/<Alert[^>]*v-if="decryptError"[^>]*type="Error"/',
            $contents,
            'SecretForm.vue must render decryptError in a persistent <Alert type="Error"> so the error stays visible on the page.'
        );

        $this->assertMatchesRegularExpression(
            '/Wrong password\W+this secret has been permanently destroyed/u',
            $contents,
            'SecretForm.vue wrong-password copy must state that the secret has been permanently destroyed so the user understands why no retry is possible.'
        );
    }

    public function test_burned_secret_cannot_be_decrypted_again(): void
    {
        // Use markAsRetrieved() directly to simulate a real retrieval. The
        // Secret::retrieved model event short-circuits under PHPUnit
        // (App::runningInConsole()), so hitting the decrypt route in a test
        // does NOT null the message. Calling the public method directly is
        // the faithful equivalent.
        $secret = Secret::factory()->create();
        $secret->markAsRetrieved();

        $signedUrl = URL::temporarySignedRoute('secret.decrypt', now()->addMinutes(5), ['secret' => $secret->hash_id]);
        $response = $this->get($signedUrl);

        $response->assertStatus(404);
    }
}
