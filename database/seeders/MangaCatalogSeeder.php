<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookActivity;
use Illuminate\Database\Seeder;

class MangaCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $formerCatalogSlugs = [
            'naruto',
            'one-piece',
            'demon-slayer-kimetsu-no-yaiba',
            'my-hero-academia',
            'dragon-ball',
            'bleach',
            'jujutsu-kaisen',
            'attack-on-titan',
            'haikyu',
            'dr-stone',
        ];
        $formerCatalogBooks = Book::query()
            ->whereIn('slug', $formerCatalogSlugs)
            ->withCount(['loans', 'reservations'])
            ->get();

        foreach ($formerCatalogBooks as $book) {
            if ($book->loans_count > 0 || $book->reservations_count > 0 || BookActivity::query()->where('book_id', $book->id)->exists()) {
                throw new \RuntimeException("Cannot replace {$book->title}: its borrowing, reservation, or reading history must be preserved.");
            }
        }

        Book::query()->whereIn('slug', $formerCatalogSlugs)->delete();

        $originalStories = [
            [
                'slug' => 'the-clockwork-messenger',
                'title' => 'The Clockwork Messenger',
                'description' => 'A quick-footed mechanic races to deliver a message that could stop two mountain towns from going to war.',
                'content' => <<<'STORY'
Chapter One: The Broken Bell

In the mountain town of Rill, every message traveled by bell. One evening, the bell tower cracked during a storm, and the warning meant for the neighboring town of Vey never arrived. By morning, both towns believed the other had broken their promise to share the river.

Niko, a young clockmaker, found a paper message wedged inside the tower gears. The bridge between the towns would close at sunset. He built a tiny wind-up runner from a brass bird, a compass spring, and one wheel from his mother's old sewing machine.

Chapter Two: The Narrow Pass

The runner darted along the ridge, but the storm returned and swept it toward the ravine. Niko caught its ribbon just in time. The bridge was already lifting. He could save the machine or cross the remaining planks himself.

He tucked the message inside his coat and ran. A gust tore the bridge loose behind him. On the far side, Vey's gatekeepers raised their spears. Niko rang the brass bird's tiny bell—the old signal both towns had used before the tower was built.

Chapter Three: Two Hands on the Rope

The gatekeepers recognized the sound. They read the message: the river had changed course, and both towns needed to open the same floodgate before the farms were underwater. Vey sent runners back with Niko.

At the river, people from Rill and Vey pulled the gate rope together. The water turned away from the houses and flowed into empty fields. Niko repaired the tower bell with his brass bird's bell as its new heart. From then on, the towns sent messengers by road as well as sound.
STORY,
            ],
            [
                'slug' => 'the-tideglass-map',
                'title' => 'The Tideglass Map',
                'description' => 'Two young deckhands follow a changing sea map to rescue a stranded research crew before the tide turns.',
                'content' => <<<'STORY'
Chapter One: A Map That Would Not Dry

Lio found the map in a bottle caught beneath the harbor pier. It was drawn on clear glass and showed the coast as it would look at the next high tide. A small red mark blinked beside the outer reef.

The harbor keeper recognized the mark. A survey boat had missed its return signal. Lio and his friend Sena took the town's little sailboat, packed water and rope, and followed the map past the last buoy.

Chapter Two: The Reef Road

At the reef, the glass map changed. A safe channel appeared, then vanished as the waves moved. Sena watched the birds; Lio watched the water. Together they steered between the rocks, but a wave split their sail.

They found the survey crew on a low sandbar with their boat wedged against coral. The tide was rising. Lio tied the boats together while Sena used the broken sail as a signal flag.

Chapter Three: The Harbor Light

The crew climbed aboard just as the sandbar disappeared. The damaged boat could not carry everyone through the reef, so they followed the map's final line toward a quiet inlet.

At dawn, the harbor light answered Sena's flag. Everyone returned safely, and the surveyors gave the glass map to the town library. It no longer showed the future; it showed the routes Lio and Sena had discovered together. Children studied it before every sea trip, adding a new safe mark whenever someone found one.
STORY,
            ],
            [
                'slug' => 'the-ash-lantern-keepers',
                'title' => 'The Ash Lantern Keepers',
                'description' => 'A careful apprentice and a retired lantern keeper protect their valley from a storm of drifting fire.',
                'content' => <<<'STORY'
Chapter One: The Dark on the Ridge

Every evening, Iri lit the lanterns along the ridge so travelers could find the valley road. One night, the flames went out in a line, one after another. A red cloud drifted down from the old mountain kiln.

Iri's teacher, Sola, had guarded the ridge for many years. She showed Iri how to read the wind by watching the grass bend. The cloud was not alive, she said, but the hot ash could start fires wherever it landed.

Chapter Two: The Windward Tower

They climbed to the highest lantern tower with a box of mirrors and a coil of silver wire. Iri wanted to light every flame at once. Sola reminded her that the valley wind had to be guided first.

Iri angled the mirrors toward the moonlit clouds. Sola fixed the wire between the towers, making a path that directed the wind upward. The ash swirled around them, and one lantern began to glow.

Chapter Three: The First Light

Iri carried that flame down the ridge. At each tower, the next keeper used it to relight the lantern. The last light shone just as the ash cloud passed over the valley roofs.

In the morning, the villagers found only a thin gray blanket on the fields. They planted the ash-rich soil with new seeds. Iri became the youngest keeper, but she changed the rule: every tower would keep a mirror as well as a flame, so the next keeper could see the whole ridge before acting.
STORY,
            ],
            [
                'slug' => 'the-paper-comet-club',
                'title' => 'The Paper Comet Club',
                'description' => 'A nervous student builds a helpful invention with new friends for the neighborhood science fair.',
                'content' => <<<'STORY'
Chapter One: The Empty Workbench

Tavi signed up for the neighborhood science fair, then spent a week staring at an empty workbench. The other students had engines, robots, and bright posters. Tavi had only a notebook full of ideas that seemed too small.

On the way home, Tavi saw Mrs. Vale struggling to carry groceries up the long hill. A handcart would help, but the sidewalk was too steep for its wheels. That night, Tavi drew a cart with wide, soft rollers and a brake made from a bicycle handle.

Chapter Two: The Wobbly Test

Friends Jo and Pell joined the project. They built the cart from spare wood and tested it on the school ramp. It tipped over, scattering oranges everywhere.

Instead of hiding the mistake, the team measured the cart's balance. They moved the handle, widened the wheels, and added a simple strap to keep boxes steady. Each test made the cart safer.

Chapter Three: The Fair Day

At the fair, the cart carried a stack of books up the ramp without tipping. Mrs. Vale tried it and smiled. The judges asked what made the invention special.

Tavi looked at the friends who had helped measure, build, and test. “It works because we listened to the people who needed it,” Tavi said.

The team won no grand prize, but the school workshop offered to build more carts for neighbors. Tavi left the fair with an empty notebook page and a new idea: the next invention would begin with another good question.
STORY,
            ],
            [
                'slug' => 'the-seven-sky-bridges',
                'title' => 'The Seven Sky Bridges',
                'description' => 'A young martial artist learns that patience and teamwork matter more than winning a mountain challenge.',
                'content' => <<<'STORY'
Chapter One: The First Bridge

On the island of Koro, students crossed seven rope bridges to reach the mountain school. Mina wanted to cross them faster than anyone. She trained every morning, racing the wind from the shore.

On the day of the school trial, a storm snapped the first bridge. The younger students were stranded on the far side, and the teacher was injured. Mina could attempt the long jump alone, but the others could not follow.

Chapter Two: The Slow Crossing

Mina tied a practice rope around a tree and crawled across with a basket of supplies. Her friend Ren held the rope from the shore, while the younger students crossed one by one. Mina's arms shook, but she waited until every foot reached the safe side.

At the next bridge, they repaired the loose boards together. They used Mina's careful footwork, Ren's knots, and the students' counted rhythm to keep the bridge steady.

Chapter Three: The Mountain School

By sunset, everyone had reached the school. Mina had not set a speed record. She had learned the route well enough to show others how to cross safely.

The teacher gave Mina the school's red sash, not for being first, but for turning back when others needed her. In the following year, Mina led the bridge crew. She still raced the wind sometimes—but now she always made sure her friends were beside her at the finish.
STORY,
            ],
            [
                'slug' => 'the-midnight-signal-station',
                'title' => 'The Midnight Signal Station',
                'description' => 'A new night-shift radio operator traces a mysterious signal to help a lost mountain train.',
                'content' => <<<'STORY'
Chapter One: Three Soft Taps

At midnight, the old signal station received three soft taps through its radio. No voice followed. Dara, the new operator, checked the schedule: no train was due until morning.

She called the station chief, who heard only static. Dara remembered the repair guide: damaged radios sometimes repeated sounds from nearby relay towers. She marked each tap on a map. Together they formed a line toward the northern pass.

Chapter Two: The Silent Train

Dara called the pass station. A storm had cut their power, and a small maintenance train was missing. Its emergency transmitter had only enough charge to send three taps.

Dara switched the station's backup antenna toward the ridge. She sent a sequence of long and short tones, the code used to guide trains through fog. From the far side came a reply.

Chapter Three: Dawn on the Rails

Dara kept the signal steady while the crew restarted the relay tower by hand. The maintenance train's lamps appeared below the pass, moving slowly along the safe track.

At dawn, the train returned with everyone aboard. The station chief offered Dara the morning shift, but she preferred the quiet hours. She added the new signal pattern to the operator handbook so that no lost train would have to call alone again.
STORY,
            ],
            [
                'slug' => 'the-curse-of-the-empty-mask',
                'title' => 'The Curse of the Empty Mask',
                'description' => 'A young archive assistant investigates a strange mask that makes a whole town forget its promises.',
                'content' => <<<'STORY'
Chapter One: The Missing Promise

After the spring festival, the people of Yori could not remember the promises they had made. The baker forgot to deliver bread to the school. The mayor forgot the new footpath plan. Only Aki, an archive assistant, remembered every promise written in the town ledger.

At the museum, Aki found an empty mask tucked behind an old banner. Whenever someone looked at it, a name faded from the ledger.

Chapter Two: The Name in the Margin

Aki asked her friend Bo to guard the ledger while she searched the archive. The oldest page described the mask as a reminder of words spoken but never kept. It grew stronger whenever people blamed one another.

Aki covered the mask and brought it to the town square. She read each faded promise aloud. At first, the crowd argued. Then the baker admitted he had been too busy, and the mayor admitted the path plan was unfinished.

Chapter Three: Words Kept

The mask cracked when the townspeople chose small promises they could truly keep. The baker delivered bread that afternoon. The mayor marked a workday for the footpath, and neighbors volunteered tools.

The names returned to the ledger. Aki placed the broken mask in the archive with a note: “Remembering is the beginning; doing is the rest.” From then on, the town held a promise day each month—not to make grand vows, but to finish one helpful thing together.
STORY,
            ],
            [
                'slug' => 'the-winter-wall-garden',
                'title' => 'The Winter Wall Garden',
                'description' => 'A resourceful village finds a peaceful way to open its snowbound gates and share scarce supplies.',
                'content' => <<<'STORY'
Chapter One: The Closed Gate

For three weeks, snow covered the road to Nera. The village stores were low, and the heavy gate was frozen shut. People worried that the neighboring valley might take what little remained.

Sol, a young gardener, noticed green shoots growing in a crack in the stone wall. Warm air was rising from an old drain beneath the gate. Sol asked the village builder to inspect it.

Chapter Two: A Path Through Stone

The drain was blocked with ice and fallen branches. Sol and the builder cleared it with shovels, while neighbors carried warm water in kettles. When the channel opened, the gate shifted—but only a little.

Rather than force it, Sol set a wooden wedge under the gate and asked everyone to lift together on the count of three. The gate rose enough for one person to pass through.

Chapter Three: The Shared Road

A messenger from the neighboring valley arrived with beans and lamp oil. Nera shared its remaining seed potatoes in return. The villages cleared the road together, laying straw where the snow drifted deepest.

By the time spring came, the wall crack had become a small garden. Sol planted both valleys' seeds there, and children from each place tended the rows. The gate stayed open—not because the villages had no worries, but because they had learned to meet them together.
STORY,
            ],
            [
                'slug' => 'the-last-point-at-sunrise',
                'title' => 'The Last Point at Sunrise',
                'description' => 'Two rival school volleyball teams learn to trust one another when a storm threatens their community gym.',
                'content' => <<<'STORY'
Chapter One: The Rival Captains

Rin and Asa captained rival volleyball teams. They both wanted the final point in the district match, and neither would admit the other had a good serve.

Before the match, rain poured through the gym roof. The teams moved the benches and placed buckets beneath the leaks. A loud crack shook the building: one support beam had shifted.

Chapter Two: One Team

The coach stopped the match and called for help. Rin organized the players into a line to carry equipment away from the damaged wall. Asa spotted water gathering near the electrical panel and warned everyone to keep clear.

Together, they used volleyball nets as barriers to guide the water toward the drains. When the maintenance crew arrived, both teams helped hold lights and move the scoreboards.

Chapter Three: A Different Final Point

The gym reopened a week later. The district match was played again, but Rin and Asa asked to mix the teams for a friendly game. They practiced calling the ball, covering open spaces, and cheering for every save.

At sunrise, the last rally ended with a gentle tip that neither captain could reach alone. A player from each team caught it just before it touched the floor. Everyone laughed.

There was no trophy for the best rescue or the kindest pass, so the teams made one from an old ball and a piece of wood. They put it on the gym shelf as a reminder: a good team is built by the people who lift together.
STORY,
            ],
            [
                'slug' => 'the-clockwork-reef',
                'title' => 'The Clockwork Reef',
                'description' => 'A young marine engineer and a careful diver repair a reef-monitoring device before the coral garden is lost.',
                'content' => <<<'STORY'
Chapter One: The Quiet Reef

Mako's family had watched over the Blueglass Reef for generations. This season, the fish had gone quiet, and the reef monitor stopped sending readings. Mako, an apprentice engineer, took a small repair kit out with Neri, a diver who knew every safe channel.

They found the monitor tangled in a curtain of abandoned fishing line. Its little propeller had stopped turning.

Chapter Two: The Gentle Repair

Mako wanted to pull the line free, but Neri showed how it was wrapped around living coral. They worked slowly, cutting one strand at a time and collecting every piece in a mesh bag.

The monitor still would not start. Mako built a replacement propeller from a smooth shell-shaped piece of recycled plastic. Neri guided the device into the current while Mako tightened the last screw.

Chapter Three: A Reef That Speaks

The monitor blinked awake and sent new readings to the shore. The water was warmer than usual, so the village moved its fishing boats away from the most fragile coral and began a weekly reef check.

Months later, tiny fish returned to the restored garden. Mako's invention was not a machine that could save the sea by itself. It was a tool that helped people notice trouble early and work together. The village kept the mesh bag beside the harbor as a reminder to leave the reef cleaner than they found it.
STORY,
            ],
            [
                'slug' => 'the-glass-orchard',
                'title' => 'The Glass Orchard',
                'description' => 'A patient apprentice discovers why a mountain orchard has stopped blooming and helps its keepers restore the spring.',
                'content' => <<<'STORY'
Chapter One: The Silent Trees

When spring reached the town of Bellglass, the orchard on the hill stayed silent. Its trees had clear, glassy leaves that chimed whenever the wind passed through, but this year not one note sounded. Luma, an apprentice gardener, climbed the hill with a basket of seeds and found the orchard keeper, Oren, trying to polish every branch.

“They only need to shine,” he insisted. Luma noticed that the soil was dry and the stream beside the orchard had disappeared beneath a wall of fallen stones.

Chapter Two: The Buried Spring

Luma asked the children to listen at different places along the hillside. At the oldest tree, they heard a faint trickle under the roots. Oren wanted to dig quickly, but Luma marked the roots with colored string so they would not be cut.

Together they moved the stones one at a time. Beneath them, the spring had been blocked by a cracked wooden wheel from the old water gate. Oren remembered how the gate worked, and Luma shaped a new peg from a fallen branch. They fitted the wheel and waited.

Chapter Three: The Orchard's Song

Water slipped down the channel and soaked the thirsty earth. The next morning, tiny buds appeared along the branches. When the breeze arrived, the glass leaves rang softly, then louder, until the whole hillside seemed to be singing.

Oren apologized for polishing while the roots went dry. Luma planted a ring of ordinary flowers around the oldest tree so the keepers would notice when the ground needed care. Every spring, the town listened to the orchard—and checked the water before touching a single leaf.
STORY,
            ],
            [
                'slug' => 'the-map-of-quiet-streets',
                'title' => 'The Map of Quiet Streets',
                'description' => 'A young mapmaker helps a crowded city find safe paths for people who cannot hear the warning bells.',
                'content' => <<<'STORY'
Chapter One: A Missing Route

In Harbor City, warning bells rang whenever the river rose. Most people hurried uphill, but Neri's neighbor, Mr. Vale, could not hear the bells. One rainy afternoon, Neri found him waiting at the flooded end of a street, unsure which way to go.

Neri was learning to draw city maps. She began marking the loud bells, the quiet gardens, and the ramps between the river road and the hill. Her first map looked messy, but it showed a safe route that no one had noticed before.

Chapter Two: The Color Signals

Neri asked the sign painter, the market sellers, and the schoolchildren to help test the route. They placed bright blue flags at each turn and agreed on a simple hand signal that meant “follow this way.”

When the next rainstorm came, one flag had blown away. The group changed the route so it passed under covered walkways and added a second marker at every corner. Mr. Vale tested the path with Neri, stopping to point out where the map was confusing.

Chapter Three: A City That Could Answer

The river rose again, but this time the market opened its blue shutters, the school raised its flags, and neighbors guided one another uphill. Mr. Vale reached the shelter before the lower street flooded.

The city printed Neri's map in large colors and placed copies in shops and buses. Neri added new paths whenever residents found a safer way. The map was never finished, she decided; a good map listened to the people who used it.
STORY,
            ],
            [
                'slug' => 'the-library-under-the-lake',
                'title' => 'The Library Under the Lake',
                'description' => 'Two friends investigate a mysterious message from an old lakeside library and save its stories from being lost.',
                'content' => <<<'STORY'
Chapter One: The Bottle Bell

Every evening, a small bell rang beneath Lake Orla. The villagers said it was the sound of bubbles, but Ema and her friend Tolen heard a pattern: one ring, then two, then one.

They asked the retired diver, Aunt Suri, about it. She told them that a stone reading room once stood on an island before the lake was built. Its books had been moved to higher ground, except for one metal box of story titles.

Chapter Two: The Sunken Door

Suri helped the friends lower a glass-bottomed boat over the old island. They found the box caught in a doorway, but the lake current tugged at the rope. Ema wanted to pull hard. Tolen noticed that the doorway was covered in reeds that could tear the box apart.

They tied a wide cloth sling around it and waited until the current eased. Together, they lifted the box into the boat. Inside were copper plates engraved with the names of stories and the families who had written them.

Chapter Three: The Names Remembered

The plates were cleaned and placed in the village archive. Families came forward with old notebooks and memories, rebuilding the stories one page at a time. The underwater bell was brought ashore and hung outside the archive.

Now it rang once for a new story and twice for an old one being remembered. Ema and Tolen added their own tale about the sunken reading room. Their names went on a copper plate too, beside everyone who had kept a story alive.
STORY,
            ],
            [
                'slug' => 'the-paper-wing-workshop',
                'title' => 'The Paper Wing Workshop',
                'description' => 'A shy inventor and a lively group of neighbors build paper gliders to carry medicine across a flooded valley.',
                'content' => <<<'STORY'
Chapter One: The Rainbound Valley

The rain had washed out the road to the village clinic. A box of medicine waited at the railway station on the other side of the valley, but the footbridge was unsafe.

Miri loved folding paper but disliked speaking in front of people. She drew a glider with a wide wing and a tiny basket beneath it. Her grandfather tested it in the workshop. It flew across the room, then landed in a soup pot.

Chapter Two: Learning the Wind

The neighbors gathered to help. A kite maker widened the wing, a baker found light string, and the clinic nurse showed them how to pack the medicine safely. Their first glider spun into a tree. The next fell short of the stream.

Miri watched the flags on the hill. The wind was strongest just after sunrise, when it climbed the warm rock face. She changed the launch angle and added a folded tail to steady the paper wing.

Chapter Three: A Flight for Everyone

At sunrise, the team released the glider. It rose above the stream, crossed the valley, and landed in a cloth net the station workers had stretched between two poles. The medicine reached the clinic before noon.

The road was repaired the following week, but the workshop stayed open. Miri taught children how to fold safe, sturdy gliders. She spoke softly at first, then with confidence. Each wing carried a note of thanks to someone across the valley.
STORY,
            ],
            [
                'slug' => 'the-moonstone-train',
                'title' => 'The Moonstone Train',
                'description' => 'A station apprentice uncovers why a night train has stopped and reunites a mountain town before the first snow.',
                'content' => <<<'STORY'
Chapter One: The Empty Platform

The Moonstone Train carried letters and supplies to three mountain villages. One night, it stopped halfway up the ridge and did not arrive at the station. An apprentice named Sella found a trail of pale dust on the platform and followed it to the signal shed.

The signal lamp was dark. Its moonstone lens had cracked, and the spare lens was locked in the station safe. The station keeper had gone to help at the lower village.

Chapter Two: The Borrowed Light

Sella knew the train could not move without a signal. She asked the night baker to lend a polished tray, then set it behind the old lamp to reflect the moonlight. The light was faint, but it reached the next relay post.

At each post, a keeper angled the signal toward the next. The chain of light traveled up the mountain. The train driver saw it and stopped safely before the broken track.

Chapter Three: The First Snow

The villages sent a repair crew by morning. They replaced the damaged track and fitted a new lens, cut from a clear stone at the station. The train arrived before the first snow covered the pass.

Sella's reflected-light signal became the emergency plan for every station. The train carried more than parcels after that; it carried news between neighbors. On quiet nights, Sella watched the lamps blink across the ridge and knew no village was too far away to answer.
STORY,
            ],
            [
                'slug' => 'the-saffron-sky-kitchen',
                'title' => 'The Saffron Sky Kitchen',
                'description' => 'A young cook brings a divided neighborhood together by recreating a lost festival recipe.',
                'content' => <<<'STORY'
Chapter One: The Torn Recipe

The rooftop festival of Saffron Street had been canceled for years. The old recipe for the festival soup was missing, and each family remembered a different version. Lina, a new kitchen helper, found half a recipe tucked inside a flour tin.

It called for golden roots, green leaves, and “one ingredient offered by a neighbor.” Lina thought the last part was only a saying. She started cooking alone, and the soup tasted flat.

Chapter Two: A Table of Memories

Lina carried bowls to the next rooftop. One neighbor brought a lemon; another brought roasted corn. An elder remembered the soup's toasted seeds, and a child suggested adding fresh herbs from the stairwell garden.

They cooked a new pot together. Each addition changed the flavor, but nothing overwhelmed the rest. Lina wrote down every ingredient and the name of the person who shared it.

Chapter Three: The Festival Returns

At sunset, the rooftops filled with bowls of golden soup. People who had disagreed about the old recipe tasted the new one and recognized pieces of their memories in it.

The festival returned the next year, with a long table connecting every rooftop. Lina kept the torn recipe in the community kitchen, beside the new one. Under both she wrote: “The secret ingredient is making room for another person's taste.”
STORY,
            ],
            [
                'slug' => 'the-wind-beneath-the-stairs',
                'title' => 'The Wind Beneath the Stairs',
                'description' => 'A curious student follows a draft through an old school and helps her classmates repair its storm shelter.',
                'content' => <<<'STORY'
Chapter One: The Cold Classroom

Every winter, the east classroom grew cold even when the stove was lit. A student named Pavi noticed that a paper bird on her desk always pointed toward the stairs. She followed the draft to a locked basement door.

The custodian opened it and found a ventilation tunnel packed with leaves. It connected the school to an old storm shelter under the courtyard, a place no one had used in years.

Chapter Two: The Hidden Shelter

Pavi and her classmates cleared the tunnel with the custodian's permission. They found benches, a rainwater tank, and a wall map showing a safe exit. The shelter was dusty but sound.

A storm warning arrived that afternoon. The class tested the route, checking the door, tank, and emergency lamps. One lamp had no battery, so the students brought a hand-crank lantern from the science room.

Chapter Three: Warm Air, Safe People

The storm passed without damage, but the shelter was ready. The class cleaned it each month and planted shrubs around the outside drain so leaves would not block it again.

The east classroom stayed warmer after the tunnel was cleared. Pavi kept the paper bird above the basement door. It no longer pointed toward trouble; it pointed toward a place the whole school had made safe together.
STORY,
            ],
            [
                'slug' => 'the-starling-post',
                'title' => 'The Starling Post',
                'description' => 'A young courier and a flock of clever birds help deliver letters after a storm scatters the town post.',
                'content' => <<<'STORY'
Chapter One: Letters in the Trees

A storm scattered the town's mail across the orchard. Rafi, the youngest courier, found letters caught in branches and under stones. The road to the post office was blocked, and people were waiting for news from family far away.

A flock of starlings gathered near Rafi's basket. Their leader wore a blue thread around one leg, left by a child who had rescued it the previous winter.

Chapter Two: The Orchard Route

Rafi could not ask the birds to carry every letter, but he noticed they flew safely between the orchard and the hill. He tied bright ribbons on the safe branches and sorted the letters by street.

The birds led him to a dry path behind the trees. Neighbors joined, carrying bundles by hand. When they found a letter with no address, Rafi read the return mark and brought it to the old station.

Chapter Three: Delivered Together

By evening, nearly every letter had reached its reader. The last one belonged to the child who had tied the blue thread. It was a thank-you note from a cousin returning home.

Rafi built a weatherproof mail shelf at the station and marked a safe orchard route on the town map. The starlings still visited, but the mail no longer depended on their wings. Their help had taught the town to become a better courier service for one another.
STORY,
            ],
            [
                'slug' => 'the-river-of-small-lights',
                'title' => 'The River of Small Lights',
                'description' => 'A young boat builder organizes a riverside town to guide a lost family home through the fog.',
                'content' => <<<'STORY'
Chapter One: Fog on the Water

The river fog arrived before sunset and covered the bends near Willow Town. A family returning from the far bank missed the landing and drifted downstream. Their lantern was too small to be seen from shore.

Toma, a young boat builder, heard a faint whistle. He knew the river was too dangerous to search alone, so he ran to the market square and asked everyone to bring a light.

Chapter Two: A Chain Along the Bank

People placed lamps at safe places along the river. Toma and his aunt took a sturdy boat upstream while the others watched from the bank. At each bend, someone raised a lantern and waited for the family's whistle.

The boat found the family near a reed island. Toma tied their canoe beside his and followed the lights home. The river current tugged hard, but the lamps showed each turn.

Chapter Three: The Bright Landing

The family reached the landing unharmed. The town built a row of permanent signal posts and agreed on three whistle patterns: one for help, one for safe passage, and one to say “we are home.”

Years later, Toma taught new boat builders how to make boats that could carry both people and lanterns. The river still filled with fog, but every bend held a light—and every light meant someone was watching out for the others.
STORY,
            ],
            [
                'slug' => 'the-rooftop-garden-crew',
                'title' => 'The Rooftop Garden Crew',
                'description' => 'A group of young neighbors turn a forgotten roof into a garden that helps their apartment block through a heatwave.',
                'content' => <<<'STORY'
Chapter One: The Hot Roof

The summer heat lingered between the tall buildings of Sunward Block. On the top floor, Inez noticed that the rooftop was warmer than the street and covered with cracked pots. She asked the building caretaker if the space could become a garden.

The caretaker agreed if the neighbors made a safe plan. Inez gathered the children, drew a map of the roof, and marked the drain, the water barrel, and the areas that could hold planters.

Chapter Two: The Garden Plan

The first seedlings wilted in the afternoon sun. Inez's friend Pao suggested moving them beside the old water tank, but the roof there was too weak for heavy pots. The caretaker helped them build light wooden shelves near the stairwell, where the morning sun was gentle.

Each family brought a small amount of soil or seeds. The children made a watering schedule and painted signs showing which plants needed less water. They saved rainwater in covered containers and checked the roof drain after every storm.

Chapter Three: Shade for Everyone

By the end of summer, vines shaded the stairwell windows and herbs grew along the roof. The garden did not cool the whole city, but it made the top floors more comfortable and gave neighbors a place to rest.

When a heat warning came, the building opened its shaded common room and shared the garden's water with residents who needed it. Inez added a bench beside the planters. The rooftop had become more than a garden: it was a place where people knew one another well enough to notice when someone needed help.
STORY,
            ],
        ];

        foreach ($originalStories as $story) {
            Book::firstOrCreate(
                ['slug' => $story['slug']],
                [
                    'category' => 'manga',
                    'title' => $story['title'],
                    'author' => 'Library Story Team',
                    'description' => $story['description'],
                    'content' => $story['content'],
                    'source_label' => 'Original library manga story',
                    'total_copies' => 1,
                ],
            );
        }
    }
}
