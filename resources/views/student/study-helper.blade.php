<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Helper | Library Book Monitoring System</title>
    <style>
        :root { --blue: #1469f5; --navy: #0b1b2b; --ink: #172338; --muted: #7e8795; --paper: #f4f6f9; --panel: #fff; --line: #e3e8ef; }
        * { box-sizing: border-box; } body { margin: 0; min-height: 100vh; padding: 32px; background: var(--paper); color: var(--ink); font-family: 'Trebuchet MS', Verdana, sans-serif; }
        main { width: min(100%, 900px); margin: auto; animation: reveal .5s ease both; } header { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 24px; }
        .eyebrow { margin: 0 0 7px; color: var(--blue); font-size: 11px; font-weight: 900; letter-spacing: 1.5px; text-transform: uppercase; } h1 { margin: 0; font-size: clamp(30px, 6vw, 48px); } p { color: var(--muted); line-height: 1.6; }
        header a, button { display: inline-block; padding: 11px 15px; border: 0; border-radius: 5px; background: var(--blue); color: #fff; font: 800 12px 'Trebuchet MS', sans-serif; text-decoration: none; cursor: pointer; } header a:hover, button:hover { background: #0d54cb; }
        .helper { display: grid; grid-template-columns: 260px 1fr; gap: 20px; } .panel { padding: 24px; background: var(--panel); border: 1px solid var(--line); border-radius: 7px; box-shadow: 0 3px 12px #1b2d4510; }
        .panel h2 { margin: 0 0 16px; font-size: 17px; } label { display: block; margin: 15px 0 6px; color: var(--ink); font-size: 12px; font-weight: 800; } select, textarea { width: 100%; padding: 11px; border: 1px solid #ccd4df; border-radius: 4px; background: #fff; color: var(--ink); font: 13px/1.5 'Trebuchet MS', sans-serif; } textarea { min-height: 155px; resize: vertical; } button { margin-top: 14px; width: 100%; }
        .response { min-height: 310px; background: #fbfcfe; } .response h2 { color: var(--blue); } .response-content { white-space: pre-line; color: #435064; line-height: 1.7; } .hint { margin-top: 20px; padding: 13px; border-left: 3px solid #f28b21; background: #fff8ed; color: #75501f; font-size: 12px; line-height: 1.5; }
        .chips { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 16px; } .chip { padding: 7px 9px; border: 1px solid var(--line); border-radius: 20px; color: var(--muted); font-size: 11px; }
        @keyframes reveal { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } } @media (max-width: 700px) { body { padding: 20px; } header { align-items: start; flex-direction: column; } .helper { grid-template-columns: 1fr; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <header>
            <div>
                <p class="eyebrow">Guided learning assistant</p>
                <h1>Study Helper</h1>
                @if ($aiEnabled)
                    <p>Ask by typing or upload a photo. Choose your learning level, answer language, or practice mode. AI can make mistakes, so verify important answers.</p>
                @else
                    <p>Offline mode can solve supported math problems and explain a few built-in topics. Answers are fixed in English; learning level, other languages, photo questions, and broader answers need the AI service online.</p>
                @endif
            </div>
            <a href="{{ route('study.index') }}">Back to Study</a>
        </header>
        <section class="helper">
            <form class="panel" id="helper-form" method="POST" enctype="multipart/form-data" action="{{ route('study.helper.answer') }}">
                @csrf
                <h2>Ask a study question</h2>
                <label for="subject">Subject</label>
                <select id="subject" name="subject" required>
                    @foreach ($subjects as $value => $label)
                        <option value="{{ $value }}" @selected(old('subject', 'Mathematics') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('subject')<p role="alert">{{ $message }}</p>@enderror
                <label for="reading_level">Explanation level</label>
                <select id="reading_level" name="reading_level" required>
                    @foreach ($readingLevels as $value => $label)
                        <option value="{{ $value }}" @selected(old('reading_level', 'beginner') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="language">Answer language</label>
                <select id="language" name="language" required>
                    @foreach ($languages as $value => $label)
                        <option value="{{ $value }}" @selected(old('language', 'English') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="mode">Learning mode</label>
                <select id="mode" name="mode" required>
                    @foreach ($modes as $value => $label)
                        <option value="{{ $value }}" @selected(old('mode', 'explain') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="question">Your problem</label>
                <textarea id="question" name="question" maxlength="1000" placeholder="Example: How do I solve 3x + 5 = 20? You can leave this blank when uploading a photo.">{{ old('question') }}</textarea>
                @error('question')<p role="alert">{{ $message }}</p>@enderror
                <label for="image">Or upload a problem photo</label>
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                <small>JPG, PNG, or WebP, up to 5 MB. Photo reading requires internet and the AI service.</small>
                @error('image')<p role="alert">{{ $message }}</p>@enderror
                <button type="submit">Get help</button>
                @if ($aiEnabled)
                    <p class="hint">Your text and any uploaded photo are sent to the configured AI service. Do not include passwords, student IDs, or other private information.</p>
                    <div class="chips"><span class="chip">Step-by-step</span><span class="chip">Practice quiz</span><span class="chip">Learning levels</span><span class="chip">Photo questions</span></div>
                @else
                    <div class="chips"><span class="chip">Offline math</span><span class="chip">Built-in topics</span><span class="chip">English only offline</span></div>
                @endif
            </form>
            <section class="panel response" aria-live="polite">
                <h2>Answer and study steps</h2>
                @if (session()->has('study_response'))
                    @php($studyResponse = session('study_response'))
                    <div class="response-content">
                        <p><strong>{{ $studyResponse['answer'] }}</strong></p>
                        @if ($studyResponse['steps'])
                            <ol>
                                @foreach ($studyResponse['steps'] as $step)
                                    <li>{{ $step }}</li>
                                @endforeach
                            </ol>
                        @endif
                        <p>{{ $studyResponse['follow_up'] }}</p>
                    </div>
                    @if (! $studyResponse['supported'])
                        <p class="hint">The offline helper does not have a reliable answer for this topic. Check your textbook or ask your teacher.</p>
                    @endif
                    @if ($studyResponse['offline_fallback'] ?? false)
                        <p class="hint">The AI service is offline or unreachable, so this answer came from the built-in English offline helper. It may not cover every question or your selected language.</p>
                    @endif
                    @if (! empty($studyResponse['practice_questions']))
                        <h3>Practice questions</h3>
                        <ol>
                            @foreach ($studyResponse['practice_questions'] as $practiceQuestion)
                                <li>
                                    <strong>{{ $practiceQuestion['question'] }}</strong>
                                    <details>
                                        <summary>Show answer</summary>
                                        <p>{{ $practiceQuestion['answer'] }}</p>
                                    </details>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    @if (! empty($studyResponse['sources']))
                        <h3>Study sources</h3>
                        <ul>
                            @foreach ($studyResponse['sources'] as $source)
                                <li><a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['title'] }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <div class="response-content">Choose a subject and ask about a supported topic to see a worked answer here.</div>
                @endif
                @if (! $aiEnabled)
                    <div class="hint">Without an API key, only the built-in sample topics can be answered. Check important answers against your lesson, textbook, or teacher.</div>
                @endif
            </section>
        </section>
    </main>
</body>
</html>
