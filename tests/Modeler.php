<?php
namespace Morbihanet\Modeler\Test;

class Modeler extends TestCase
{
    /** @test */
    public function it_should_be_empty()
    {
        $this->assertEquals(0, Book::count());
        $this->assertTrue(Book::notExists());
    }

    /** @test */
    public function it_should_be_not_empty()
    {
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $this->assertEquals(1, Book::count());
        $this->assertTrue(Book::exists());
    }

    /** @test */
    public function it_should_be_updatable()
    {
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        Book::first()->update(['title' => 'Fleurs du Mal']);
        $this->assertEquals('Fleurs du Mal', Book::first()->title);

        $row = Book::first();
        $row->title = 'Des Fleurs du Mal';
        $row->save();
        $this->assertEquals('Des Fleurs du Mal', Book::first()->title);

        Book::where('title', 'like', '%Mal%')->update(['title' => 'Les Fleurs du Mal']);
        $this->assertEquals('Les Fleurs du Mal', Book::first()->title);
    }

    /** @test */
    public function it_should_be_deletable()
    {
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $this->assertEquals(2, Book::count());

        Book::first()->delete();
        $this->assertEquals(1, Book::count());
        Book::where('title', 'Notre Dame de Paris')->destroy();
        $this->assertEquals(0, Book::count());

        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        Book::destroy();
        $this->assertEquals(0, Book::count());
    }

    /** @test */
    public function it_should_be_item()
    {
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        $book = Book::find(1);

        $this->assertEquals(\App\Entities\Book::class, get_class($book));
    }

    /** @test */
    public function it_should_be_searchable()
    {
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);

        $this->assertEquals(2, Book::where('year', '<', 1900)->count());
        $this->assertEquals(2, Book::where('year', '>', 1800)->count());
        $this->assertEquals(1, Book::where('year', '<', 1850)->count());
        $this->assertEquals(1, Book::where('year', '>', 1850)->count());
        $this->assertEquals(1, Book::contains('title', 'fleurs')->count());
        $this->assertEquals(1, Book::contains('title', 'paris')->count());
        $this->assertEquals(2, Book::contains('title', 'paris')->orContains('title', 'fleurs')->count());
        $this->assertEquals(1, Book::contains('title', 'paris')->orContains('title', 'dummy')->count());
    }

    /** @test */
    public function it_should_calculate()
    {
        $hugo = Author::create(['name' => 'Victor Hugo']);
        $baudelaire = Author::create(['name' => 'Charles Baudelaire']);

        $notreDame = Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id]);
        $contemplations = Book::create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        $fleurs = Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $this->assertEquals(3, Author::sum('id'));
        $this->assertEquals(1.5, Author::avg('id'));
        $this->assertEquals(6, Book::sum('id'));
        $this->assertEquals(2, Book::avg('id'));

        $this->assertEquals(1, Author::min('id'));
        $this->assertEquals(2, Author::max('id'));
        $this->assertEquals(1, Book::min('id'));
        $this->assertEquals(3, Book::max('id'));

        $this->assertEquals(1, $notreDame->author->sum('id'));
        $this->assertEquals(1, $contemplations->author->sum('id'));
        $this->assertEquals(2, $fleurs->author->sum('id'));

        $this->assertEquals(3, $hugo->books->sum('id'));
        $this->assertEquals(3, $baudelaire->books->sum('id'));
    }

    /** @test */
    public function it_should_be_groupable()
    {
        $hugo = Author::create(['name' => 'Victor Hugo']);
        $baudelaire = Author::create(['name' => 'Charles Baudelaire']);

        $notreDame = Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id]);
        $contemplations = Book::create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        $fleurs = Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $group = Book::groupBy('author_id');
        $this->assertEquals(2, $group->count());
    }

    /** @test */
    public function it_should_be_sortable()
    {
        $hugo = Author::create(['name' => 'Victor Hugo']);
        sleep(1);
        $baudelaire = Author::create(['name' => 'Charles Baudelaire']);

        $notreDame = Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id]);
        $contemplations = Book::create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        $fleurs = Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $this->assertEquals(2, Author::latest()->first()->id);
        $this->assertEquals(1, Author::oldest()->first()->id);
        $this->assertEquals('Les Contemplations', Book::sortBy('title')->first()->title);
        $this->assertEquals('Notre Dame de Paris', Book::sortByDesc('title')->first()->title);
    }

    /** @test */
    public function it_should_have_relations()
    {
        $hugo = Author::create(['name' => 'Victor Hugo']);
        $baudelaire = Author::create(['name' => 'Charles Baudelaire']);

        $notreDame = Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id]);
        $contemplations = Book::create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        $fleurs = Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $this->assertEquals($hugo->id, $notreDame->author->id);
        $this->assertEquals($hugo->id, $contemplations->author->id);
        $this->assertEquals($baudelaire->id, $fleurs->author->id);
        $this->assertEquals(2, $hugo->books->count());
        $this->assertEquals(1, $baudelaire->books->count());
    }
}