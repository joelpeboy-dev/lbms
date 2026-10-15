<?php

namespace App\Services;

use App\Exceptions\StudyHelperException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use JsonException;

class StudyHelper
{
    public const SUBJECTS = [
        'Mathematics' => 'Mathematics',
        'Science' => 'Science',
        'English' => 'English',
        'History / AP' => 'History / AP',
        'ICT / Computer' => 'ICT / Computer',
        'General study' => 'General study',
        'Any subject' => 'Any subject',
    ];

    public const READING_LEVELS = [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
    ];

    public const LANGUAGES = [
        'English' => 'English',
        'Filipino' => 'Filipino',
        'Spanish' => 'Spanish',
    ];

    public const MODES = [
        'explain' => 'Explain a problem',
        'practice' => 'Practice quiz',
    ];

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string, offline_fallback?: bool, practice_questions?: list<array{question: string, answer: string}>, sources?: list<array{title: string, url: string}>}
     */
    public function answer(
        string $subject,
        string $question,
        string $readingLevel = 'beginner',
        string $language = 'English',
        string $mode = 'explain',
        ?UploadedFile $image = null,
    ): array {
        $apiKey = config('services.study_helper.api_key');

        if (is_string($apiKey) && trim($apiKey) !== '') {
            try {
                $answer = $this->answerWithOpenAi($subject, $question, trim($apiKey), $readingLevel, $language, $mode, $image);
            } catch (ConnectionException $exception) {
                if ($image !== null) {
                    throw new StudyHelperException(
                        'The photo could not be read because the AI service is offline. Reconnect and try again.',
                        previous: $exception,
                    );
                }

                $answer = $this->builtInAnswer($subject, $question);
                $answer['offline_fallback'] = true;
            }

            $answer['sources'] = $this->sourcesFor($subject);

            if ($mode === 'practice' && $answer['supported'] && empty($answer['practice_questions'])) {
                $answer['practice_questions'] = $this->practiceQuestionsFor($subject);
            }

            return $answer;
        }

        if ($image !== null) {
            throw new StudyHelperException('Photo questions need an internet connection and an AI service API key.');
        }

        $answer = $this->builtInAnswer($subject, $question);
        $answer['sources'] = $this->sourcesFor($subject);

        if ($mode === 'practice' && $answer['supported']) {
            $answer['practice_questions'] = $this->practiceQuestionsFor($subject);
        }

        return $answer;
    }

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string}
     */
    private function builtInAnswer(string $subject, string $question): array
    {
        $normalizedQuestion = mb_strtolower($question);

        if (in_array($subject, ['Mathematics', 'Any subject'], true)) {
            $mathAnswer = $this->solveLinearEquation($question);

            if ($mathAnswer !== null) {
                return $mathAnswer;
            }

            $arithmeticAnswer = $this->solveArithmeticExpression($question);

            if ($arithmeticAnswer !== null) {
                return $arithmeticAnswer;
            }
        }

        $sample = match ($subject) {
            'Science' => $this->scienceAnswer($normalizedQuestion),
            'English' => $this->englishAnswer($normalizedQuestion),
            'History / AP' => $this->historyAnswer($normalizedQuestion),
            'ICT / Computer' => $this->computerAnswer($normalizedQuestion),
            'General study' => $this->generalStudyAnswer($normalizedQuestion),
            'Any subject' => $this->generalAnswer($normalizedQuestion),
            default => null,
        };

        if ($sample !== null) {
            return $sample;
        }

        $fallback = $this->generalAnswer($normalizedQuestion);

        if ($fallback !== null) {
            return $fallback;
        }

        return [
            'answer' => 'I can’t give a reliable answer to that question while offline. This local helper only has a limited set of built-in explanations and math tools.',
            'steps' => [
                'Try a calculation or a simple linear equation.',
                'Try a built-in topic such as photosynthesis, the water cycle, gravity, or the blue sky.',
                'Reconnect to the internet for detailed answers to other questions.',
            ],
            'supported' => false,
            'follow_up' => 'Check your textbook or ask your teacher if you need help before the AI service is available again.',
        ];
    }

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string, offline_fallback?: bool}
     */
    private function answerWithOpenAi(
        string $subject,
        string $question,
        string $apiKey,
        string $readingLevel,
        string $language,
        string $mode,
        ?UploadedFile $image,
    ): array {
        try {
            $studentPrompt = "Subject: {$subject}\nReading level: {$readingLevel}\nAnswer language: {$language}\nMode: {$mode}\nStudent question: {$question}";

            if ($image !== null) {
                $mimeType = $image->getMimeType();

                if (! is_string($mimeType)) {
                    throw new StudyHelperException('The uploaded photo type could not be identified. Try a JPG, PNG, or WebP image.');
                }

                $studentPrompt = [
                    ['type' => 'text', 'text' => $studentPrompt."\nRead the problem in the attached photo. If the text is unclear, ask the student to upload a clearer photo instead of guessing."],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:'.$mimeType.';base64,'.base64_encode($image->get()),
                        ],
                    ],
                ];
            }

            $response = Http::acceptJson()
                ->asJson()
                ->withToken($apiKey)
                ->connectTimeout(5)
                ->timeout((int) config('services.study_helper.timeout', 45))
                ->post(config('services.study_helper.endpoint'), [
                    'model' => config('services.study_helper.model'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a careful, encouraging tutor for school students. Answer accurately in the requested language and at the requested reading level. Explain reasoning in short steps and give the final answer. In practice mode, create exactly three new practice questions with their answers; otherwise return an empty practice_questions list. If a question or image is unclear, ask for the missing information instead of guessing. Check calculations. Return JSON with exactly these fields: "answer" (string), "steps" (array of strings), "follow_up" (string), "practice_questions" (array of objects with "question" and "answer" strings). Do not include markdown fences.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $studentPrompt,
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2,
                    'max_completion_tokens' => 800,
                ]);

            $response->throw();
        } catch (RequestException $exception) {
            throw new StudyHelperException(
                'The AI service rejected the request (HTTP '.$exception->response->status().'). Check your API key and try again.',
                previous: $exception,
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new StudyHelperException('The AI service returned an unexpected response. Please try again.');
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new StudyHelperException('The AI service returned an unreadable answer. Please try again.', previous: $exception);
        }

        if (! is_array($data)
            || ! is_string($data['answer'] ?? null)
            || trim($data['answer']) === ''
            || ! is_array($data['steps'] ?? null)
            || ! array_is_list($data['steps'])
            || $data['steps'] === []
            || ! is_string($data['follow_up'] ?? null)
            || trim($data['follow_up']) === ''
            || ! is_array($data['practice_questions'] ?? null)
            || ! array_is_list($data['practice_questions'])) {
            throw new StudyHelperException('The AI service returned an incomplete answer. Please try again.');
        }

        foreach ($data['steps'] as $step) {
            if (! is_string($step) || trim($step) === '') {
                throw new StudyHelperException('The AI service returned incomplete steps. Please try again.');
            }
        }

        foreach ($data['practice_questions'] as $practiceQuestion) {
            if (! is_array($practiceQuestion)
                || ! is_string($practiceQuestion['question'] ?? null)
                || trim($practiceQuestion['question']) === ''
                || ! is_string($practiceQuestion['answer'] ?? null)
                || trim($practiceQuestion['answer']) === '') {
                throw new StudyHelperException('The AI service returned incomplete practice questions. Please try again.');
            }
        }

        return [
            'answer' => trim($data['answer']),
            'steps' => array_map('trim', $data['steps']),
            'supported' => true,
            'follow_up' => trim($data['follow_up']),
            'practice_questions' => array_map(fn (array $item): array => [
                'question' => trim($item['question']),
                'answer' => trim($item['answer']),
            ], $data['practice_questions']),
        ];
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    private function sourcesFor(string $subject): array
    {
        return match ($subject) {
            'Mathematics' => [
                ['title' => 'Khan Academy Mathematics', 'url' => 'https://www.khanacademy.org/math'],
                ['title' => 'OpenStax Mathematics', 'url' => 'https://openstax.org/subjects/math'],
            ],
            'Science' => [
                ['title' => 'Khan Academy Science', 'url' => 'https://www.khanacademy.org/science'],
                ['title' => 'NASA Science', 'url' => 'https://science.nasa.gov/'],
            ],
            'English' => [
                ['title' => 'Purdue Online Writing Lab', 'url' => 'https://owl.purdue.edu/'],
                ['title' => 'British Council LearnEnglish', 'url' => 'https://learnenglish.britishcouncil.org/'],
            ],
            'History / AP' => [
                ['title' => 'Library of Congress Research Guides', 'url' => 'https://guides.loc.gov/'],
                ['title' => 'National Archives Education', 'url' => 'https://www.archives.gov/education'],
            ],
            'ICT / Computer' => [
                ['title' => 'CS50', 'url' => 'https://cs50.harvard.edu/'],
                ['title' => 'MDN Web Docs', 'url' => 'https://developer.mozilla.org/'],
            ],
            default => [
                ['title' => 'Khan Academy', 'url' => 'https://www.khanacademy.org/'],
                ['title' => 'OpenStax Free Textbooks', 'url' => 'https://openstax.org/subjects'],
            ],
        };
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private function practiceQuestionsFor(string $subject): array
    {
        return match ($subject) {
            'Mathematics' => [
                ['question' => 'What is 7 + 8?', 'answer' => '15'],
                ['question' => 'Solve: 2x + 4 = 10.', 'answer' => 'x = 3'],
                ['question' => 'What is 24 ÷ 6?', 'answer' => '4'],
            ],
            'Science' => [
                ['question' => 'What gas do plants take in during photosynthesis?', 'answer' => 'Carbon dioxide.'],
                ['question' => 'What is the change from liquid water to water vapor called?', 'answer' => 'Evaporation.'],
                ['question' => 'What force pulls objects toward Earth?', 'answer' => 'Gravity.'],
            ],
            'English' => [
                ['question' => 'Which part of speech names a person, place, thing, or idea?', 'answer' => 'A noun.'],
                ['question' => 'Which part of speech shows an action?', 'answer' => 'A verb.'],
                ['question' => 'In “the blue book,” which word is the adjective?', 'answer' => 'Blue.'],
            ],
            'History / AP' => [
                ['question' => 'What is a primary source?', 'answer' => 'Evidence created during the time being studied or by someone directly involved.'],
                ['question' => 'Name one example of a primary source.', 'answer' => 'A diary, letter, photograph, speech, or official record.'],
                ['question' => 'Why compare multiple historical sources?', 'answer' => 'To evaluate different perspectives and check evidence.'],
            ],
            'ICT / Computer' => [
                ['question' => 'What is an algorithm?', 'answer' => 'A clear, ordered set of steps for completing a task.'],
                ['question' => 'What should you do after writing an algorithm?', 'answer' => 'Test it and correct steps that do not work.'],
                ['question' => 'Why should algorithm steps be specific?', 'answer' => 'So they can be followed consistently.'],
            ],
            default => [
                ['question' => 'What is the main idea of the topic you are studying?', 'answer' => 'State the central point in one clear sentence.'],
                ['question' => 'What is one example that supports the main idea?', 'answer' => 'Give a relevant example from your lesson or reading.'],
                ['question' => 'How can you check that you understand the topic?', 'answer' => 'Explain it from memory and try a practice question.'],
            ],
        };
    }

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string}|null
     */
    private function solveLinearEquation(string $question): ?array
    {
        $number = '(?:\\d{1,9}(?:\\.\\d{1,6})?|\\.\\d{1,6})';
        $term = '[+-]?(?:'.$number.'?x|'.$number.')';
        $expression = $term.'(?:[+-](?:'.$number.'?x|'.$number.'))*';
        $equation = mb_strtolower((string) preg_replace('/\\s+/u', '', $question));

        if (! preg_match('/^(?<left>'.$expression.')=(?<right>'.$expression.')$/', $equation, $matches)) {
            return null;
        }

        $left = $this->linearParts($matches['left']);
        $right = $this->linearParts($matches['right']);
        $coefficient = $left['coefficient'] - $right['coefficient'];
        $constant = $right['constant'] - $left['constant'];

        if (abs($coefficient) < 0.000001) {
            return null;
        }

        $solution = $constant / $coefficient;

        if (! is_finite($solution)) {
            return null;
        }

        $solution = round($solution, 6);
        $leftResult = $left['coefficient'] * $solution + $left['constant'];
        $rightResult = $right['coefficient'] * $solution + $right['constant'];

        return [
            'answer' => 'x = '.$this->formatNumber($solution),
            'steps' => [
                'Start with: '.$this->formatEquation($matches['left'], $matches['right']).'.',
                'Move the x terms to one side and the numbers to the other: '.$this->formatNumber($coefficient).'x = '.$this->formatNumber($constant).'.',
                'Divide both sides by '.$this->formatNumber($coefficient).': x = '.$this->formatNumber($solution).'.',
                'Check: substituting x gives '.$this->formatNumber($leftResult).' on the left and '.$this->formatNumber($rightResult).' on the right.',
            ],
            'supported' => true,
            'follow_up' => 'Try solving a similar equation on your own, then substitute your answer to check it.',
        ];
    }

    /**
     * @return array{coefficient: float, constant: float}
     */
    private function linearParts(string $expression): array
    {
        preg_match_all('/[+-]?[^+-]+/', $expression, $matches);

        $coefficient = 0.0;
        $constant = 0.0;

        foreach ($matches[0] as $term) {
            if (str_ends_with($term, 'x')) {
                $value = substr($term, 0, -1);
                $coefficient += match ($value) {
                    '', '+' => 1.0,
                    '-' => -1.0,
                    default => (float) $value,
                };
            } else {
                $constant += (float) $term;
            }
        }

        return [
            'coefficient' => $coefficient,
            'constant' => $constant,
        ];
    }

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string}|null
     */
    private function scienceAnswer(string $question): ?array
    {
        if (str_contains($question, 'photosynthesis')) {
            return [
                'answer' => 'Photosynthesis is how green plants use sunlight to make glucose (sugar) from water and carbon dioxide. Oxygen is released.',
                'steps' => [
                    'Leaves take in carbon dioxide from the air, and roots absorb water from the soil.',
                    'Chlorophyll in the leaves captures energy from sunlight.',
                    'The plant uses that energy to make glucose and releases oxygen.',
                    'Word equation: carbon dioxide + water + light energy -> glucose + oxygen.',
                ],
                'supported' => true,
                'follow_up' => 'Try explaining why sunlight is needed in this process.',
            ];
        }

        if (str_contains($question, 'water cycle')) {
            return [
                'answer' => 'The water cycle moves water around Earth through evaporation, condensation, precipitation, and collection.',
                'steps' => [
                    'The Sun warms water and causes evaporation into water vapor.',
                    'Water vapor cools and condenses into clouds.',
                    'Water falls from clouds as precipitation, such as rain or snow.',
                    'Water collects in rivers, lakes, and oceans, and the cycle continues.',
                ],
                'supported' => true,
                'follow_up' => 'Can you name the part of the cycle powered by the Sun?',
            ];
        }

        return null;
    }

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string}|null
     */
    private function englishAnswer(string $question): ?array
    {
        foreach ([
            'noun' => ['a noun', 'A noun names a person, place, thing, or idea.', 'Example: In “The student reads,” student is the noun.'],
            'verb' => ['a verb', 'A verb shows an action or a state of being.', 'Example: In “The student reads,” reads is the verb.'],
            'adjective' => ['an adjective', 'An adjective describes a noun or pronoun.', 'Example: In “The bright book,” bright is the adjective describing book.'],
        ] as $keyword => [$term, $answer, $example]) {
            if (str_contains($question, $keyword)) {
                return [
                    'answer' => $answer,
                    'steps' => [
                        'Find the word that names, acts, or is being described.',
                        $example,
                        'Try making your own sentence and identify the '.$term.' in it.',
                    ],
                    'supported' => true,
                    'follow_up' => 'Write a sentence of your own and see if you can identify the word type.',
                ];
            }
        }

        return null;
    }

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string}|null
     */
    private function historyAnswer(string $question): ?array
    {
        if (! str_contains($question, 'primary source')) {
            return null;
        }

        return [
            'answer' => 'A primary source is evidence created during the time being studied or by someone directly involved, such as a letter, diary, photograph, speech, or official record.',
            'steps' => [
                'Check who created the source.',
                'Find out when it was created and what was happening at that time.',
                'Ask why it was made and whose point of view it shows.',
                'Compare it with other evidence before drawing a conclusion.',
            ],
            'supported' => true,
            'follow_up' => 'Choose an old photograph and list one thing it shows and one question it cannot answer.',
        ];
    }

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string}|null
     */
    private function computerAnswer(string $question): ?array
    {
        if (! str_contains($question, 'algorithm')) {
            return null;
        }

        return [
            'answer' => 'An algorithm is a clear, ordered set of steps for completing a task or solving a problem.',
            'steps' => [
                'State the goal.',
                'List the steps in the order they must happen.',
                'Make each step specific enough to follow.',
                'Test the steps and correct anything that does not work.',
            ],
            'supported' => true,
            'follow_up' => 'Try writing an algorithm for making a sandwich, then ask a friend to follow it exactly.',
        ];
    }

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string}|null
     */
    private function generalStudyAnswer(string $question): ?array
    {
        if (! str_contains($question, 'study') && ! str_contains($question, 'plan')) {
            return null;
        }

        return [
            'answer' => 'A useful study session has one clear goal, short focused practice, and a quick review from memory.',
            'steps' => [
                'Choose one topic and write down what you want to understand.',
                'Study for 20 to 25 minutes without distractions.',
                'Close your notes and explain the main idea from memory.',
                'Try one practice question and check where you got stuck.',
                'Take a short break, then review the difficult part.',
            ],
            'supported' => true,
            'follow_up' => 'Pick one small topic now and write a one-sentence goal for your next study session.',
        ];
    }

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string}|null
     */
    private function generalAnswer(string $question): ?array
    {
        if (str_contains($question, 'gravity')) {
            return [
                'answer' => 'Gravity is the force of attraction between objects with mass. Earth’s gravity pulls objects toward its center and gives them weight.',
                'steps' => [
                    'Objects with mass attract one another through gravity.',
                    'Earth has a very large mass, so it pulls nearby objects toward it.',
                    'That pull is why unsupported objects fall toward the ground.',
                ],
                'supported' => true,
                'follow_up' => 'Try comparing an object’s mass with its weight, which depends on gravity.',
            ];
        }

        if (str_contains($question, 'sky blue') || str_contains($question, 'blue sky')) {
            return [
                'answer' => 'The sky looks blue because air molecules scatter shorter blue wavelengths of sunlight more strongly than longer red wavelengths. The scattered blue light reaches our eyes from across the sky.',
                'steps' => [
                    'Sunlight contains many colors, including blue and red.',
                    'Air molecules scatter the shorter blue wavelengths more strongly.',
                    'That scattered blue light comes toward us from many directions.',
                ],
                'supported' => true,
                'follow_up' => 'This scattering effect is called Rayleigh scattering.',
            ];
        }

        return null;
    }

    /**
     * @return array{answer: string, steps: list<string>, supported: bool, follow_up: string}|null
     */
    private function solveArithmeticExpression(string $question): ?array
    {
        $normalizedQuestion = mb_strtolower((string) preg_replace('/\s+/u', '', $question));
        $normalizedQuestion = str_replace(['×', '÷'], ['*', '/'], $normalizedQuestion);

        if (! preg_match('/\d/', $normalizedQuestion)) {
            return null;
        }

        if (preg_match('/[a-z]/u', $normalizedQuestion)) {
            $normalizedQuestion = preg_replace('/^(?:what|calculate|solve|simplify|evaluate|find|what\s+is|what\s+is\s+the\s+value\s+of)\s*/u', '', $normalizedQuestion);
        }

        $normalizedQuestion = preg_replace('/[?!=]+$/u', '', $normalizedQuestion);
        $normalizedQuestion = preg_replace('/^.*?([0-9][0-9.()+\-*\/\s]*)$/u', '$1', $normalizedQuestion);
        $normalizedQuestion = preg_replace('/\s+/u', '', $normalizedQuestion);

        if ($normalizedQuestion === '' || preg_match('/[a-z]/u', $normalizedQuestion)) {
            return null;
        }

        if (! preg_match('/^[0-9.()+\-*\/%\s]+$/u', $normalizedQuestion)) {
            return null;
        }

        $result = $this->evaluateSimpleArithmetic($normalizedQuestion);

        if ($result === null) {
            return null;
        }

        return [
            'answer' => $this->formatNumber($result),
            'steps' => [
                'Start with: '.$this->formatAsExpression($normalizedQuestion).'.',
                'Work through the calculation in order: '.$this->formatAsExpression($normalizedQuestion).' = '.$this->formatNumber($result).'.',
                'Final answer: '.$this->formatNumber($result).'.',
            ],
            'supported' => true,
            'follow_up' => 'Try another quick calculation and check each step before you finish.',
        ];
    }

    private function evaluateSimpleArithmetic(string $expression): ?float
    {
        $tokens = [];
        preg_match_all('/\d+(?:\.\d+)?|[()+\-*\/%]/', $expression, $matches);

        if ($matches[0] === []) {
            return null;
        }

        $tokens = $matches[0];
        $index = 0;

        $parseExpression = function () use (&$index, $tokens, &$parseExpression, &$parseTerm): float {
            $value = $parseTerm();

            while ($index < count($tokens) && ($tokens[$index] === '+' || $tokens[$index] === '-')) {
                $operator = $tokens[$index++];
                $right = $parseTerm();
                $value = $operator === '+' ? $value + $right : $value - $right;
            }

            return $value;
        };

        $parseTerm = function () use (&$index, $tokens, &$parseFactor): float {
            $value = $parseFactor();

            while ($index < count($tokens) && ($tokens[$index] === '*' || $tokens[$index] === '/' || $tokens[$index] === '%')) {
                $operator = $tokens[$index++];
                $right = $parseFactor();

                if ($operator === '/') {
                    if (abs($right) < 0.0000001) {
                        throw new \RuntimeException('Division by zero');
                    }

                    $value = $value / $right;

                    continue;
                }

                if ($operator === '%') {
                    if (abs($right) < 0.0000001) {
                        throw new \RuntimeException('Division by zero');
                    }

                    $value = fmod($value, $right);

                    continue;
                }

                $value = $value * $right;
            }

            return $value;
        };

        $parseFactor = function () use (&$index, $tokens, &$parseFactor, &$parseExpression): float {
            if ($index >= count($tokens)) {
                throw new \RuntimeException('Unexpected end of expression');
            }

            $token = $tokens[$index];

            if ($token === '(') {
                $index++;
                $value = $parseExpression();

                if (! isset($tokens[$index]) || $tokens[$index] !== ')') {
                    throw new \RuntimeException('Missing closing bracket');
                }

                $index++;

                return $value;
            }

            if ($token === '+') {
                $index++;

                return $parseFactor();
            }

            if ($token === '-') {
                $index++;

                return -$parseFactor();
            }

            if (! is_numeric($token)) {
                throw new \RuntimeException('Unexpected token');
            }

            $index++;

            return (float) $token;
        };

        try {
            $result = $parseExpression();

            if ($index !== count($tokens)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return is_finite($result) ? $result : null;
    }

    private function formatAsExpression(string $expression): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['*', '/'], ['×', '÷'], $expression)) ?? $expression);
    }

    private function formatNumber(float $number): string
    {
        $formatted = rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');

        return $formatted === '-0' || $formatted === '' ? '0' : $formatted;
    }

    private function formatEquation(string $left, string $right): string
    {
        $formattedLeft = preg_replace('/([+-])/', ' $1 ', $left);
        $formattedRight = preg_replace('/([+-])/', ' $1 ', $right);

        return trim((string) $formattedLeft).' = '.trim((string) $formattedRight);
    }
}
