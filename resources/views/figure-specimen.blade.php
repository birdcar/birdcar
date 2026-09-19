<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Development specimen — Reading and figures</title>
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="reading-page figure-specimen">
        <main class="essay">
            <header class="essay-heading">
                <h1>Reading and figures</h1>
                <p class="essay-description">Development specimen. Local review only, not a published essay or a client deliverable.</p>
            </header>
            <div class="article-prose">
                @php
                    $writing = app(\App\Actions\ReadWriting::class);
                    $excerpt = <<<'MARKDOWN'
## Illustrative reading excerpt

A useful explanation keeps the relationship between the work and the next decision visible. These figures use the same components as the site, arranged for a reader who wants to stop and study them.

@aside title="About this specimen"
This excerpt is illustrative. The chart below uses the unchanged source data from [Your AI wrote a bug](/writing/your-ai-wrote-a-bug/), not client results.
@endaside

@figure kind=chart type=bar src=./data/bug-fix-loops.json width=wide caption="Most loops were logic and integration failures, not edge cases."
@endfigure
MARKDOWN;
                    $walkthrough = <<<'MARKDOWN'
@figure kind=diagram name=walkthrough caption="What the Walkthrough gives you."
@endfigure
MARKDOWN;
                    $reporting = <<<'MARKDOWN'
@figure kind=diagram name=reporting caption="How the reporting work fits together."
@endfigure
MARKDOWN;
                @endphp
                {!! $writing->render($excerpt) !!}
                <section class="specimen-figure">
                    <h2>The Walkthrough</h2>
                    {!! $writing->render($walkthrough) !!}
                </section>
                <section class="specimen-figure">
                    <h2>Reporting at Craft &amp; Communicate</h2>
                    {!! $writing->render($reporting) !!}
                </section>
            </div>
        </main>
    </body>
</html>
