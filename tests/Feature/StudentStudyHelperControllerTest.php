<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StudentStudyHelperControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_receives_a_worked_answer_for_a_supported_math_question(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Mathematics',
                'question' => '3x + 5 = 20',
            ]);

        $response->assertRedirect(route('study.helper'));
        $response->assertSessionHas('study_response', fn (array $answer): bool => $answer['answer'] === 'x = 5'
            && $answer['supported'] === true
            && count($answer['steps']) === 4);

        $this->get(route('study.helper'))
            ->assertOk()
            ->assertSee('x = 5')
            ->assertSee('Divide both sides by 3');
    }

    public function test_student_can_use_the_offline_practice_quiz_for_math(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Mathematics',
                'question' => '1 + 2',
                'mode' => 'practice',
            ]);

        $response->assertRedirect(route('study.helper'));
        $response->assertSessionHas('study_response', fn (array $answer): bool => count($answer['practice_questions']) === 3
            && $answer['practice_questions'][0]['answer'] === '15'
            && $answer['sources'][0]['title'] === 'Khan Academy Mathematics');

        $this->get(route('study.helper'))
            ->assertOk()
            ->assertSee('Practice questions')
            ->assertSee('Show answer');
    }

    public function test_student_receives_a_built_in_science_answer(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Science',
                'question' => 'How does photosynthesis work?',
            ]);

        $response->assertRedirect(route('study.helper'));
        $response->assertSessionHas('study_response', fn (array $answer): bool => $answer['supported'] === true
            && str_contains($answer['answer'], 'glucose')
            && count($answer['steps']) === 4);
    }

    public function test_student_receives_a_built_in_science_answer_for_the_blue_sky_question(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Science',
                'question' => 'Why is the sky blue?',
            ]);

        $response->assertRedirect(route('study.helper'));
        $response->assertSessionHas('study_response', fn (array $answer): bool => $answer['supported'] === true
            && str_contains($answer['answer'], 'scatter shorter blue wavelengths'));

        $this->get(route('study.helper'))
            ->assertOk()
            ->assertSee('This scattering effect is called Rayleigh scattering.')
            ->assertSee('Khan Academy Science');
    }

    public function test_any_subject_question_uses_a_general_explanation_when_no_topic_match_exists(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Any subject',
                'question' => 'What is gravity?',
            ]);

        $response->assertRedirect(route('study.helper'));
        $response->assertSessionHas('study_response', fn (array $answer): bool => $answer['supported'] === true
            && str_contains($answer['answer'], 'force of attraction')
            && count($answer['steps']) >= 3);
    }

    public function test_unknown_question_is_not_misrepresented_as_a_reliable_offline_answer(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Any subject',
                'question' => 'Explain a topic not in the offline examples',
            ]);

        $response->assertRedirect(route('study.helper'));
        $response->assertSessionHas('study_response', fn (array $answer): bool => $answer['supported'] === false
            && str_contains($answer['answer'], 'can’t give a reliable answer'));
    }

    public function test_student_gets_a_builtin_answer_when_the_ai_service_is_unreachable(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        config([
            'services.study_helper.api_key' => 'test-key',
            'services.study_helper.endpoint' => 'https://study-helper.test/chat',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://study-helper.test/chat' => Http::failedConnection(),
        ]);

        $response = $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Mathematics',
                'question' => '1 + 2',
            ]);

        $response->assertRedirect(route('study.helper'));
        $response->assertSessionHas('study_response', fn (array $answer): bool => $answer['answer'] === '3'
            && $answer['supported'] === true
            && $answer['offline_fallback'] === true);

        $this->get(route('study.helper'))
            ->assertOk()
            ->assertSee('The AI service is offline or unreachable');
    }

    public function test_ai_answer_uses_the_selected_level_language_and_practice_mode(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        config([
            'services.study_helper.api_key' => 'test-key',
            'services.study_helper.endpoint' => 'https://study-helper.test/chat',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://study-helper.test/chat' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'answer' => 'Una explicación matemática.',
                            'steps' => ['Paso uno.', 'Paso dos.'],
                            'follow_up' => 'Inténtalo de nuevo.',
                            'practice_questions' => [
                                ['question' => '¿Cuánto es 2 + 2?', 'answer' => '4'],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Mathematics',
                'question' => 'Explain fractions',
                'reading_level' => 'intermediate',
                'language' => 'Spanish',
                'mode' => 'practice',
            ]);

        $response->assertRedirect(route('study.helper'));
        $response->assertSessionHas('study_response', fn (array $answer): bool => $answer['answer'] === 'Una explicación matemática.'
            && $answer['practice_questions'][0]['answer'] === '4'
            && $answer['sources'][0]['title'] === 'Khan Academy Mathematics');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://study-helper.test/chat'
            && str_contains($request['messages'][1]['content'], 'Reading level: intermediate')
            && str_contains($request['messages'][1]['content'], 'Answer language: Spanish')
            && str_contains($request['messages'][1]['content'], 'Mode: practice'));
    }

    public function test_student_can_submit_an_image_for_ai_reading(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        config([
            'services.study_helper.api_key' => 'test-key',
            'services.study_helper.endpoint' => 'https://study-helper.test/chat',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://study-helper.test/chat' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'answer' => 'The answer is 3.',
                            'steps' => ['Add the two numbers.'],
                            'follow_up' => 'Try another sum.',
                            'practice_questions' => [],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Mathematics',
                'reading_level' => 'beginner',
                'language' => 'English',
                'mode' => 'explain',
                'image' => UploadedFile::fake()->image('problem.png'),
            ]);

        $response->assertRedirect(route('study.helper'));
        $response->assertSessionHas('study_response', fn (array $answer): bool => $answer['answer'] === 'The answer is 3.');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://study-helper.test/chat'
            && str_starts_with(data_get($request['messages'][1]['content'], '1.image_url.url', ''), 'data:image/'));
    }

    public function test_photo_question_requires_the_ai_service_to_be_configured(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        config(['services.study_helper.api_key' => null]);
        Http::preventStrayRequests();

        $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Mathematics',
                'image' => UploadedFile::fake()->image('problem.png'),
            ])
            ->assertRedirect(route('study.helper'))
            ->assertSessionHasErrors([
                'image' => 'Photo questions need an internet connection and an AI service API key.',
            ]);
    }

    public function test_invalid_learning_preferences_and_non_image_upload_are_rejected(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Mathematics',
                'question' => '1 + 2',
                'reading_level' => 'expert',
                'language' => 'Filipino',
                'mode' => 'unknown',
                'image' => UploadedFile::fake()->create('notes.txt', 2, 'text/plain'),
            ])
            ->assertRedirect(route('study.helper'))
            ->assertSessionHasErrors(['reading_level', 'language', 'mode', 'image']);
    }

    public function test_invalid_subject_and_missing_question_return_validation_errors(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->from(route('study.helper'))
            ->post(route('study.helper.answer'), [
                'subject' => 'Other',
                'question' => '',
            ])
            ->assertRedirect(route('study.helper'))
            ->assertSessionHasErrors([
                'subject' => 'The selected subject is invalid.',
                'question' => 'The question field is required when image is not present.',
            ]);
    }

    public function test_only_authenticated_students_can_use_the_study_helper(): void
    {
        $this->get(route('study.helper'))
            ->assertRedirect(route('login'));

        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('study.helper'))
            ->assertForbidden();

        $this->post(route('study.helper.answer'), [
            'subject' => 'Mathematics',
            'question' => '3x + 5 = 20',
        ])->assertForbidden();
    }
}
