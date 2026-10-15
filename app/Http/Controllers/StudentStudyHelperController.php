<?php

namespace App\Http\Controllers;

use App\Exceptions\StudyHelperException;
use App\Services\StudyHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentStudyHelperController extends Controller
{
    public function show(): View
    {
        abort_unless(auth()->user()->role === 'student', 403);

        $aiEnabled = is_string(config('services.study_helper.api_key'))
            && trim(config('services.study_helper.api_key')) !== '';

        return view('student.study-helper', [
            'subjects' => StudyHelper::SUBJECTS,
            'readingLevels' => StudyHelper::READING_LEVELS,
            'languages' => $aiEnabled ? StudyHelper::LANGUAGES : ['English' => 'English'],
            'modes' => StudyHelper::MODES,
            'aiEnabled' => $aiEnabled,
        ]);
    }

    public function answer(Request $request, StudyHelper $studyHelper): RedirectResponse
    {
        abort_unless($request->user()->role === 'student', 403);

        $aiEnabled = is_string(config('services.study_helper.api_key'))
            && trim(config('services.study_helper.api_key')) !== '';
        $allowedLanguages = $aiEnabled ? StudyHelper::LANGUAGES : ['English' => 'English'];

        $data = $request->validate([
            'subject' => ['required', 'string', Rule::in(array_keys(StudyHelper::SUBJECTS))],
            'question' => ['required_without:image', 'nullable', 'string', 'max:1000'],
            'reading_level' => ['sometimes', 'required', 'string', Rule::in(array_keys(StudyHelper::READING_LEVELS))],
            'language' => ['sometimes', 'required', 'string', Rule::in(array_keys($allowedLanguages))],
            'mode' => ['sometimes', 'required', 'string', Rule::in(array_keys(StudyHelper::MODES))],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        try {
            $answer = $studyHelper->answer(
                $data['subject'],
                $data['question'] ?? '',
                $data['reading_level'] ?? 'beginner',
                $data['language'] ?? 'English',
                $data['mode'] ?? 'explain',
                $request->file('image'),
            );
        } catch (StudyHelperException $exception) {
            Log::warning('Study helper could not generate an answer.', [
                'user_id' => $request->user()->id,
                'reason' => $exception->getMessage(),
            ]);

            $errorField = $request->hasFile('image') ? 'image' : 'question';

            return back()->withInput()->withErrors([$errorField => $exception->getMessage()]);
        }

        return back()->with('study_response', $answer);
    }
}
