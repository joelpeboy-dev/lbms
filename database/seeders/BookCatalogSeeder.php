<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;

class BookCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $book = Book::firstOrCreate(
            ['slug' => 'the-ember-nest'],
            [
                'category' => 'book',
                'genre' => 'fantasy',
                'title' => 'The Ember Nest',
                'author' => 'Library Story Team',
                'description' => 'An original fantasy adventure about a young mapmaker who discovers a hidden dragon nest and must help its last egg survive a mountain storm.',
                'cover_url' => asset('images/ember-nest-story.svg'),
                'content' => <<<'STORY'
Chapter One: The Warm Stone

Mara was the youngest mapmaker in Bellweather, a town built where three mountain roads met. She had drawn every bridge, stream, and footpath nearby, but one blank space remained on her largest map: the steep northern ridge.

“Nothing lives up there,” the old guides told her.

Then, one snowy evening, Mara found a stone glowing orange beneath a fallen pine. It was warm in her hands, and from inside came a tiny tap.

Chapter Two: The Hidden Nest

She followed a line of blue sparks up the ridge. Behind a curtain of ice, she found a cavern filled with silver feathers and enormous claw marks. In a hollow of soft moss lay a single dark egg, glowing like the stone.

A great dragon rested beside it. One wing was pinned beneath a fallen beam.

Mara wanted to run. Instead, she put down her pack and studied the cave. She could not lift the beam alone, so she wedged her map case beneath one end and pushed a loose rock under the other. Slowly, the dragon pulled its wing free.

The dragon lowered its head. Its name, Mara somehow understood, was Orin.

Chapter Three: The Mountain Wind

A storm rolled over the ridge. Cold wind swept through the broken entrance, and the egg’s orange glow began to fade. Orin could not carry it while injured, and the old nest had grown too cold.

Mara unfolded her map. She had marked a narrow passage leading to a sheltered valley, but the route crossed a bridge made of old roots. She showed Orin the way, and together they guarded the egg from the wind.

At the bridge, the roots began to snap. Mara tied her scarf around the egg’s basket and held tight while Orin spread one great wing to shield them. They crossed just as the bridge fell into the ravine.

Chapter Four: A New Beginning

In the valley, the storm passed. The egg shone bright again. A tiny crack appeared, and a small dragon blinked up at Mara.

Orin touched its nose to the hatchling’s head. Then the dragon looked at Mara and nudged her map case.

Mara returned to Bellweather with a new road drawn across the blank northern ridge. She did not mark the nest’s exact hiding place. Some paths, she decided, were better known only to those who needed them.

Years later, travelers would tell stories of a warm light above the mountains. Mara smiled whenever she heard them. She knew the light was not a warning or a treasure.

It was a home.
STORY,
                'source_label' => 'Original story created for the Library Book Monitoring System',
                'total_copies' => 1,
            ],
        );

        if ($book->cover_url === null) {
            $book->update(['cover_url' => asset('images/ember-nest-story.svg')]);
        }
    }
}
