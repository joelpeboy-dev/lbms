<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Subjects | Library Book Monitoring System</title>
    <style>
        :root { --blue: #102a72; --bright: #3774ff; --red: #e23d4f; --ink: #eef3ff; --muted: #aeb9d4; --paper: #05070d; --panel: #0b1020ef; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 32px; position: relative; overflow-x: hidden; background: radial-gradient(circle at 15% 15%, #102a724d, transparent 32%), radial-gradient(circle at 85% 80%, #e23d4f26, transparent 28%), var(--paper); color: var(--ink); font-family: 'Trebuchet MS', Verdana, sans-serif; }
        body::before, body::after { content: ''; position: fixed; z-index: -1; width: 280px; height: 280px; border-radius: 50%; filter: blur(8px); opacity: .45; pointer-events: none; animation: drift 14s ease-in-out infinite alternate; }
        body::before { top: -130px; right: 8%; background: #3774ff33; } body::after { bottom: -150px; left: 8%; background: #e23d4f2b; animation-delay: -5s; }
        main { width: min(100%, 1080px); margin: auto; animation: reveal .65s ease both; }
        header { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 28px; }
        .eyebrow { color: var(--red); font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; }
        h1 { margin: 8px 0; font-size: clamp(34px, 6vw, 58px); } h2 { margin: 0; font-size: 25px; } h3 { margin: 22px 0 8px; color: var(--red); font-size: 13px; letter-spacing: 1px; text-transform: uppercase; }
        p, li { color: var(--muted); line-height: 1.65; } ul { padding-left: 20px; margin-bottom: 0; }
        header a, .lesson a { display: inline-block; padding: 11px 15px; border-radius: 8px; background: var(--blue); color: white; font-weight: 800; text-decoration: none; transition: transform .2s, background .2s; } header a:hover, .lesson a:hover { transform: translateY(-3px); background: var(--bright); }
        .subjects { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
        .lesson { padding: 24px; min-height: 310px; background: var(--panel); border: 1px solid #3774ff66; border-radius: 14px; box-shadow: 0 20px 50px #0008; animation: cardIn .55s ease both; }
        .lesson:nth-child(2) { animation-delay: .08s; } .lesson:nth-child(3) { animation-delay: .16s; } .lesson:nth-child(4) { animation-delay: .24s; } .lesson:nth-child(5) { animation-delay: .32s; }
        .lesson a { margin-top: 20px; }
        @keyframes reveal { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } } @keyframes cardIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } } @keyframes drift { from { transform: translate3d(-18px, 8px, 0) scale(.9); } to { transform: translate3d(22px, -18px, 0) scale(1.08); } }
        @media (max-width: 760px) { body { padding: 20px; } header { align-items: start; flex-direction: column; } .subjects { grid-template-columns: 1fr; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/library-theme.css') }}">
</head>
<body>
    <main>
        <header>
            <div><div class="eyebrow">Student learning room</div><h1>Study subjects</h1><p>Choose a subject and build one useful idea at a time.</p></div>
            <div><a href="{{ route('study.helper') }}">Study Helper</a> <a href="{{ url('/student/dashboard') }}">Back to dashboard</a></div>
        </header>
        <section class="subjects">
            <article class="lesson"><h2>English</h2><h3>Writing clear sentences</h3><p>A sentence begins with a capital letter and ends with punctuation. A strong sentence has a subject, a verb, and a clear idea.</p><ul><li>Noun: names a person, place, or thing.</li><li>Verb: shows an action or state.</li><li>Adjective: adds detail to a noun.</li></ul><a href="#english-practice">Practice English</a></article>
            <article class="lesson"><h2>Mathematics</h2><h3>Fractions</h3><p>A fraction shows equal parts of a whole. The top number is the numerator and the bottom number is the denominator.</p><ul><li>1/2 means one of two equal parts.</li><li>2/4 is equal to 1/2.</li><li>Always compare equal-sized parts.</li></ul><a href="#math-practice">Practice maths</a></article>
            <article class="lesson"><h2>Science</h2><h3>The water cycle</h3><p>Water moves through evaporation, condensation, precipitation, and collection. The sun gives the cycle its energy.</p><ul><li>Evaporation turns liquid into vapor.</li><li>Condensation forms clouds.</li><li>Precipitation returns water to Earth.</li></ul><a href="#science-practice">Practice science</a></article>
            <article class="lesson"><h2>History</h2><h3>Reading primary sources</h3><p>A primary source comes from the time being studied, such as a letter, photograph, diary, speech, or tool.</p><ul><li>Ask who created the source.</li><li>Ask when and why it was created.</li><li>Separate evidence from opinion.</li></ul><a href="#history-practice">Practice history</a></article>
            <article class="lesson"><h2>Computer Studies</h2><h3>Algorithms</h3><p>An algorithm is a clear set of steps for solving a problem. Good algorithms are ordered, specific, and testable.</p><ul><li>Define the goal.</li><li>Break the task into steps.</li><li>Test and improve the result.</li></ul><a href="#computer-practice">Practice computing</a></article>
        </section>
        <section class="lesson" id="english-practice" style="margin-top:18px"><h2>Practice prompts</h2><p id="math-practice"><strong>English:</strong> Write one sentence using the words “bright” and “library.”</p><p><strong>Mathematics:</strong> Draw a rectangle and shade 3/4 of it.</p><p id="science-practice"><strong>Science:</strong> Describe what happens to a puddle after a sunny morning.</p><p id="history-practice"><strong>History:</strong> Name one question you would ask the creator of an old photograph.</p><p id="computer-practice"><strong>Computer Studies:</strong> Write five steps for making a sandwich as an algorithm.</p></section>
    </main>
</body>
</html>
