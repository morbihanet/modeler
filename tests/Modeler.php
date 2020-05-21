<?php
namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Swap;
use Morbihanet\Modeler\Core;
use Morbihanet\Modeler\Config;
use Morbihanet\Modeler\Valued;
use Morbihanet\Modeler\Schedule;
use Morbihanet\Modeler\Scheduler;

class Modeler extends TestCase
{
    /** @test */
    public function it_should_be_resolvable()
    {
        $data = redis_data('core', ['bar' => 'baz', 'foo' => 'bar']);

        $data['test'] = true;

        $this->assertTrue($data['test']);
        $this->assertNull($data['baz']);
        $this->assertSame('baz', $data['bar']);
        $this->assertSame('bar', $data['foo']);

        $bag = resolver('bag', function () {
            return new Valued;
        });

        $bag2 = resolver('bag2', new Valued);

        $bag->set('bar', 'baz');
        $bag2->set('baz', 'bar');

        $this->assertNotSame($bag::resolver(), $bag2::resolver());
        $this->assertSame($bag2->get($bag->get('bar')), $bag2->get('baz'));
    }

    /** @test */
    public function it_should_be_valuable()
    {
        $config  = Core::config();
        $config['foo'] = 'baz';
        $config['bar'] = 'foo';
        $config['baz'] = 'bar';

        $this->assertSame('foo', Core::config()['bar']);
        $this->assertSame('bar', Core::config()['baz']);
        $this->assertSame('baz', Core::config()['foo']);
        $this->assertSame(3, Config::count());
        $this->assertSame(3, $config->count());
        $this->assertTrue(isset($config['baz']));
        $this->assertFalse(isset($config['foobar']));
        $this->assertSame(3, count($config->values()));
    }

    /** @test */
    public function it_should_be_swappable()
    {
        $this->assertSame(50, Swap::call(Swappable::class . '@withParams', 5, 10));

        Swap::swap(Swappable::class . '@withParams', function (int $a, int $b) {
            return $a + $b;
        });

        $this->assertSame(15, Swap::call(Swappable::class . '@withParams', 5, 10));

        Swap::swap(Swappable::class . '@dummy', function () {
            return 'foo';
        });

        $this->assertSame('foo', Swap::call(Swappable::class . '@dummy'));
        $this->assertSame('baz', Swap::call(Swappable::class . '@test'));

        Swap::swap(Swappable::class . '@test', function () {
            return 'bar';
        });

        $this->assertSame('bar', Swap::call(Swappable::class . '@test'));
    }

    /** @test */
    public function it_should_be_scheduled()
    {
        $event = Scheduler::define(function ($e) {
            $e['test'] = $e['test'] + 1;
        })->everyFifteenSeconds();

        $event['test'] = 0;

        $this->assertEquals(0, Schedule::count());
        $this->assertEquals(1, Scheduler::run());
        $this->assertEquals(1, Schedule::count());
        $this->assertEquals(1, $event['test']);

        $this->assertEquals(0, Scheduler::run());
        $this->assertEquals(1, $event['test']);
    }

    /** @test */
    public function it_should_be_empty_lite()
    {
        $this->assertEquals(0, LiteBook::count());
        $this->assertTrue(LiteBook::notExists());
    }

    /** @test */
    public function it_should_be_empty()
    {
        $this->assertEquals(0, Book::count());
        $this->assertTrue(Book::notExists());
    }

    /** @test */
    public function it_should_be_empty_redis()
    {
        $this->assertEquals(0, RedisBook::count());
        $this->assertTrue(RedisBook::notExists());
    }

    /** @test */
    public function it_should_be_empty_memory()
    {
        $this->assertEquals(0, MemoryBook::count());
        $this->assertTrue(MemoryBook::notExists());
    }

    /** @test */
    public function it_should_be_empty_file()
    {
        $this->assertEquals(0, FileBook::count());
        $this->assertTrue(FileBook::notExists());
    }

    /** @test */
    public function it_should_be_not_empty()
    {
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $this->assertEquals(1, Book::count());
        $this->assertTrue(Book::exists());
    }

    /** @test */
    public function it_should_be_not_empty_lite()
    {
        LiteBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $this->assertEquals(1, LiteBook::count());
        $this->assertTrue(LiteBook::exists());
    }

    /** @test */
    public function it_should_be_not_empty_redis()
    {
        RedisBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $this->assertEquals(1, RedisBook::count());
        $this->assertTrue(RedisBook::exists());
    }

    /** @test */
    public function it_should_be_not_empty_memory()
    {
        MemoryBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $this->assertEquals(1, MemoryBook::count());
        $this->assertTrue(MemoryBook::exists());
    }

    /** @test */
    public function it_should_be_not_empty_file()
    {
        FileBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $this->assertEquals(1, FileBook::count());
        $this->assertTrue(FileBook::exists());
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
    public function it_should_be_updatable_lite()
    {
        LiteBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        LiteBook::first()->update(['title' => 'Fleurs du Mal']);
        $this->assertEquals('Fleurs du Mal', LiteBook::first()->title);

        $row = LiteBook::first();
        $row->title = 'Des Fleurs du Mal';
        $row->save();
        $this->assertEquals('Des Fleurs du Mal', LiteBook::first()->title);

        LiteBook::where('title', 'like', '%Mal%')->update(['title' => 'Les Fleurs du Mal']);
        $this->assertEquals('Les Fleurs du Mal', LiteBook::first()->title);
    }

    /** @test */
    public function it_should_be_updatable_redis()
    {
        RedisBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        RedisBook::first()->update(['title' => 'Fleurs du Mal']);
        $this->assertEquals('Fleurs du Mal', RedisBook::first()->title);

        $row = RedisBook::first();
        $row->title = 'Des Fleurs du Mal';
        $row->save();
        $this->assertEquals('Des Fleurs du Mal', RedisBook::first()->title);

        RedisBook::where('title', 'like', '%Mal%')->update(['title' => 'Les Fleurs du Mal']);
        $this->assertEquals('Les Fleurs du Mal', RedisBook::first()->title);
    }

    /** @test */
    public function it_should_be_updatable_memory()
    {
        MemoryBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        MemoryBook::first()->update(['title' => 'Fleurs du Mal']);
        $this->assertEquals('Fleurs du Mal', MemoryBook::first()->title);

        $row = MemoryBook::first();
        $row->title = 'Des Fleurs du Mal';
        $row->save();
        $this->assertEquals('Des Fleurs du Mal', MemoryBook::first()->title);

        MemoryBook::where('title', 'like', '%Mal%')->update(['title' => 'Les Fleurs du Mal']);
        $this->assertEquals('Les Fleurs du Mal', MemoryBook::first()->title);
    }

    /** @test */
    public function it_should_be_updatable_file()
    {
        FileBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        FileBook::first()->update(['title' => 'Fleurs du Mal']);
        $this->assertEquals('Fleurs du Mal', FileBook::first()->title);

        $row = FileBook::first();
        $row->title = 'Des Fleurs du Mal';
        $row->save();
        $this->assertEquals('Des Fleurs du Mal', FileBook::first()->title);

        FileBook::where('title', 'like', '%Mal%')->update(['title' => 'Les Fleurs du Mal']);
        $this->assertEquals('Les Fleurs du Mal', FileBook::first()->title);
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
    public function it_should_be_deletable_lite()
    {
        LiteBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        LiteBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $this->assertEquals(2, LiteBook::count());

        LiteBook::first()->delete();
        $this->assertEquals(1, LiteBook::count());
        LiteBook::where('title', 'Notre Dame de Paris')->destroy();
        $this->assertEquals(0, LiteBook::count());

        LiteBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        LiteBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        LiteBook::destroy();
        $this->assertEquals(0, LiteBook::count());
    }

    /** @test */
    public function it_should_be_deletable_redis()
    {
        RedisBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        RedisBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $this->assertEquals(2, RedisBook::count());

        RedisBook::first()->delete();
        $this->assertEquals(1, RedisBook::count());
        RedisBook::where('title', 'Notre Dame de Paris')->destroy();
        $this->assertEquals(0, RedisBook::count());

        RedisBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        RedisBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        RedisBook::destroy();
        $this->assertEquals(0, RedisBook::count());
    }

    /** @test */
    public function it_should_be_deletable_memory()
    {
        MemoryBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        MemoryBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $this->assertEquals(2, MemoryBook::count());

        MemoryBook::first()->delete();
        $this->assertEquals(1, MemoryBook::count());
        MemoryBook::where('title', 'Notre Dame de Paris')->destroy();
        $this->assertEquals(0, MemoryBook::count());

        MemoryBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        MemoryBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        MemoryBook::destroy();
        $this->assertEquals(0, MemoryBook::count());
    }

    /** @test */
    public function it_should_be_deletable_file()
    {
        FileBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        FileBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $this->assertEquals(2, FileBook::count());

        FileBook::first()->delete();
        $this->assertEquals(1, FileBook::count());
        FileBook::where('title', 'Notre Dame de Paris')->destroy();
        $this->assertEquals(0, FileBook::count());

        FileBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        FileBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        FileBook::destroy();
        $this->assertEquals(0, FileBook::count());
    }

    /** @test */
    public function it_should_be_transactionable()
    {
        Book::beginTransaction();

        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $this->assertEquals(1, Book::count());
        Book::rollback();
        $this->assertEquals(0, Book::count());

        Book::beginTransaction();
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $this->assertEquals(1, Book::count());
        Book::commit();
        $this->assertEquals(1, Book::count());
    }

    /** @test */
    public function it_should_be_transactionable_memory()
    {
        MemoryBook::beginTransaction();

        MemoryBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $this->assertEquals(1, MemoryBook::count());
        MemoryBook::rollback();
        $this->assertEquals(0, MemoryBook::count());

        MemoryBook::beginTransaction();
        MemoryBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $this->assertEquals(1, MemoryBook::count());
        MemoryBook::commit();
        $this->assertEquals(1, MemoryBook::count());
    }

    /** @test */
    public function it_should_be_transactionable_file()
    {
        $fileBook = new FileBook;
        $fileBook = $fileBook->beginTransaction();

        $fileBook->create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $fileBook->create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $this->assertEquals(2, $fileBook->count());
        $fileBook->rollback();
        $this->assertEquals(0, $fileBook->count());

        if ($fileBook = $fileBook->beginTransaction()) {
            $fileBook->create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
            $this->assertEquals(1, $fileBook->count());
            $fileBook->commit();
            $this->assertEquals(1, $fileBook->count());
        }
    }

    /** @test */
    public function it_should_be_item()
    {
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        $book = Book::find(1);

        $this->assertEquals(\App\Entities\Book::class, get_class($book));
    }

    /** @test */
    public function it_should_be_item_by_model()
    {
        $db = datum('book');
        $db::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        $book = $db::find(1);

        $this->assertEquals(\App\Entities\Book::class, get_class($book));
    }

    /** @test */
    public function it_should_be_item_redis_model()
    {
        $db = redis_model('book');
        $db::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        $book = $db::find(1);

        $this->assertEquals(\App\Entities\RedisBook::class, get_class($book));
    }

    /** @test */
    public function it_should_be_item_redis()
    {
        RedisBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        $book = RedisBook::find(1);

        $this->assertEquals(\App\Entities\RedisBook::class, get_class($book));
    }

    /** @test */
    public function it_should_be_item_memory()
    {
        MemoryBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        $book = MemoryBook::find(1);

        $this->assertEquals(\App\Entities\MemoryBook::class, get_class($book));
    }

    /** @test */
    public function it_should_be_item_lite()
    {
        FileBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);

        $book = FileBook::find(1);

        $this->assertEquals(\App\Entities\FileBook::class, get_class($book));
    }

    /** @test */
    public function it_should_be_searchable()
    {
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);

        $this->assertEquals(2, Book::where('year', '<', 1900)->count());
        $this->assertEquals(2, Book::lt('year', 1900)->count());
        $this->assertEquals(2, Book::where('year', '>', 1800)->count());
        $this->assertEquals(2, Book::gt('year', 1800)->count());
        $this->assertEquals(1, Book::where('year', '<', 1850)->count());
        $this->assertEquals(1, Book::where('year', '>', 1850)->count());
        $this->assertEquals(1, Book::like('title', '%Fleurs%')->count());
        $this->assertEquals(1, Book::likeI('title', '%fleurs%')->count());
    }

    /** @test */
    public function it_should_be_searchable_by_model()
    {
        $db = datum('book');
        $db::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        $db::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);

        $this->assertEquals(2, $db::where('year', '<', 1900)->count());
        $this->assertEquals(2, $db::lt('year', 1900)->count());
        $this->assertEquals(2, $db::where('year', '>', 1800)->count());
        $this->assertEquals(2, $db::gt('year', 1800)->count());
        $this->assertEquals(1, $db::where('year', '<', 1850)->count());
        $this->assertEquals(1, $db::where('year', '>', 1850)->count());
        $this->assertEquals(1, $db::like('title', '%Fleurs%')->count());
        $this->assertEquals(1, $db::likeI('title', '%fleurs%')->count());
    }

    /** @test */
    public function it_should_be_searchable_redis()
    {
        RedisBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        RedisBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);

        $this->assertEquals(2, RedisBook::where('year', '<', 1900)->count());
        $this->assertEquals(2, RedisBook::lt('year', 1900)->count());
        $this->assertEquals(2, RedisBook::where('year', '>', 1800)->count());
        $this->assertEquals(2, RedisBook::gt('year', 1800)->count());
        $this->assertEquals(1, RedisBook::where('year', '<', 1850)->count());
        $this->assertEquals(1, RedisBook::where('year', '>', 1850)->count());
        $this->assertEquals(1, RedisBook::like('title', '%Fleurs%')->count());
        $this->assertEquals(1, RedisBook::likeI('title', '%fleurs%')->count());
    }

    /** @test */
    public function it_should_be_searchable_memory()
    {
        MemoryBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        MemoryBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);

        $this->assertEquals(2, MemoryBook::where('year', '<', 1900)->count());
        $this->assertEquals(2, MemoryBook::lt('year', 1900)->count());
        $this->assertEquals(2, MemoryBook::where('year', '>', 1800)->count());
        $this->assertEquals(2, MemoryBook::gt('year', 1800)->count());
        $this->assertEquals(1, MemoryBook::where('year', '<', 1850)->count());
        $this->assertEquals(1, MemoryBook::where('year', '>', 1850)->count());
        $this->assertEquals(1, MemoryBook::like('title', '%Fleurs%')->count());
        $this->assertEquals(1, MemoryBook::likeI('title', '%fleurs%')->count());
    }

    /** @test */
    public function it_should_be_searchable_file()
    {
        FileBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867]);
        FileBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);

        $this->assertEquals(2, FileBook::where('year', '<', 1900)->count());
        $this->assertEquals(2, FileBook::lt('year', 1900)->count());
        $this->assertEquals(2, FileBook::where('year', '>', 1800)->count());
        $this->assertEquals(2, FileBook::gt('year', 1800)->count());
        $this->assertEquals(1, FileBook::where('year', '<', 1850)->count());
        $this->assertEquals(1, FileBook::where('year', '>', 1850)->count());
        $this->assertEquals(1, FileBook::like('title', '%Fleurs%')->count());
        $this->assertEquals(1, FileBook::likeI('title', '%fleurs%')->count());
    }

    /** @test */
    public function it_should_calculate_by_model()
    {
        $hugo = datum('author')::create(['name' => 'Victor Hugo']);
        $baudelaire = datum('author')::create(['name' => 'Charles Baudelaire']);

        datum('book')::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id]);
        datum('book')::create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        datum('book')::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $this->assertEquals(3, datum('author')::sum('id'));
        $this->assertEquals(1.5, datum('author')::avg('id'));
        $this->assertEquals(6, datum('book')::sum('id'));
        $this->assertEquals(2, datum('book')::avg('id'));

        $this->assertEquals(1, datum('author')::min('id'));
        $this->assertEquals(2, datum('author')::max('id'));
        $this->assertEquals(1, datum('book')::min('id'));
        $this->assertEquals(3, datum('book')::max('id'));

        $this->assertEquals(3, $hugo->books->sum('id'));
        $this->assertEquals(3, $baudelaire->books->sum('id'));
    }

    /** @test */
    public function it_should_calculate()
    {
        $hugo = Author::create(['name' => 'Victor Hugo']);
        $baudelaire = Author::create(['name' => 'Charles Baudelaire']);

        Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id]);
        Book::create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $this->assertEquals(3, Author::sum('id'));
        $this->assertEquals(1.5, Author::avg('id'));
        $this->assertEquals(6, Book::sum('id'));
        $this->assertEquals(2, Book::avg('id'));

        $this->assertEquals(1, Author::min('id'));
        $this->assertEquals(2, Author::max('id'));
        $this->assertEquals(1, Book::min('id'));
        $this->assertEquals(3, Book::max('id'));

        $this->assertEquals(3, $hugo->books->sum('id'));
        $this->assertEquals(3, $baudelaire->books->sum('id'));
    }

    /** @test */
    public function it_should_calculate_redis()
    {
        $hugo = RedisAuthor::create(['name' => 'Victor Hugo']);
        $baudelaire = RedisAuthor::create(['name' => 'Charles Baudelaire']);

        RedisBook::create([
            'title' => 'Notre Dame de Paris',
            'year' => 1831, 'redis_author_id' => $hugo->id
        ]);

        RedisBook::create([
            'title' => 'Les Contemplations',
            'year' => 1855,
            'redis_author_id' => $hugo->id
        ]);

        RedisBook::create([
            'title' => 'Les Fleurs du Mal',
            'year' => 1867,
            'redis_author_id' => $baudelaire->id
        ]);

        $this->assertEquals(3, RedisAuthor::sum('id'));
        $this->assertEquals(1.5, RedisAuthor::avg('id'));
        $this->assertEquals(6, RedisBook::sum('id'));
        $this->assertEquals(2, RedisBook::avg('id'));

        $this->assertEquals(1, RedisAuthor::min('id'));
        $this->assertEquals(2, RedisAuthor::max('id'));
        $this->assertEquals(1, RedisBook::min('id'));
        $this->assertEquals(3, RedisBook::max('id'));
//
        $this->assertEquals(3, $baudelaire->redis_books->sum('id'));
        $this->assertEquals(3, $hugo->redis_books->sum('id'));
    }

    /** @test */
    public function it_should_calculate_memory()
    {
        $hugo = MemoryAuthor::create(['name' => 'Victor Hugo']);
        $baudelaire = MemoryAuthor::create(['name' => 'Charles Baudelaire']);

        MemoryBook::create([
            'title' => 'Notre Dame de Paris',
            'year' => 1831, 'memory_author_id' => $hugo->id
        ]);

        MemoryBook::create([
            'title' => 'Les Contemplations',
            'year' => 1855,
            'memory_author_id' => $hugo->id
        ]);

        MemoryBook::create([
            'title' => 'Les Fleurs du Mal',
            'year' => 1867,
            'memory_author_id' => $baudelaire->id
        ]);

        $this->assertEquals(3, MemoryAuthor::sum('id'));
        $this->assertEquals(1.5, MemoryAuthor::avg('id'));
        $this->assertEquals(6, MemoryBook::sum('id'));
        $this->assertEquals(2, MemoryBook::avg('id'));

        $this->assertEquals(1, MemoryAuthor::min('id'));
        $this->assertEquals(2, MemoryAuthor::max('id'));
        $this->assertEquals(1, MemoryBook::min('id'));
        $this->assertEquals(3, MemoryBook::max('id'));

        $this->assertEquals(3, $baudelaire->memory_books->sum('id'));
        $this->assertEquals(3, $hugo->memory_books->sum('id'));
    }

    /** @test */
    public function it_should_calculate_file()
    {
        $hugo = FileAuthor::create(['name' => 'Victor Hugo']);
        $baudelaire = FileAuthor::create(['name' => 'Charles Baudelaire']);

        FileBook::create([
            'title' => 'Notre Dame de Paris',
            'year' => 1831,
            'file_author_id' => $hugo->id
        ]);

        FileBook::create([
            'title' => 'Les Contemplations',
            'year' => 1855,
            'file_author_id' => $hugo->id
        ]);

        FileBook::create([
            'title' => 'Les Fleurs du Mal',
            'year' => 1867,
            'file_author_id' => $baudelaire->id
        ]);

        $this->assertEquals(3, FileAuthor::sum('id'));
        $this->assertEquals(1.5, FileAuthor::avg('id'));
        $this->assertEquals(6, FileBook::sum('id'));
        $this->assertEquals(2, FileBook::avg('id'));

        $this->assertEquals(1, FileAuthor::min('id'));
        $this->assertEquals(2, FileAuthor::max('id'));
        $this->assertEquals(1, FileBook::min('id'));
        $this->assertEquals(3, FileBook::max('id'));

        $this->assertEquals(3, $baudelaire->file_books->sum('id'));
        $this->assertEquals(3, $hugo->file_books->sum('id'));
    }

    /** @test */
    public function it_should_be_groupable_by_model()
    {
        $hugo = datum('author', 'test', ['name' => 'Victor Hugo'])->save();
        $baudelaire = datum('author', 'test', ['name' => 'Charles Baudelaire'])->save();

        datum('book', 'test', ['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id])->save();
        datum('book', 'test', ['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id])->save();
        datum('book', 'test', ['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id])
            ->save();

        $group = datum('book', 'test')->groupBy('author_id');
        $this->assertEquals(2, $group->count());
    }

    /** @test */
    public function it_should_be_groupable()
    {
        $hugo = Author::create(['name' => 'Victor Hugo']);
        $baudelaire = Author::create(['name' => 'Charles Baudelaire']);

        Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id]);
        Book::create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $group = Book::groupBy('author_id');
        $this->assertEquals(2, $group->count());
    }

    /** @test */
    public function it_should_be_groupable_redis()
    {
        $hugo = RedisAuthor::create(['name' => 'Victor Hugo']);
        $baudelaire = RedisAuthor::create(['name' => 'Charles Baudelaire']);

        RedisBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'redis_author_id' => $hugo->id]);
        RedisBook::create(['title' => 'Les Contemplations', 'year' => 1855, 'redis_author_id' => $hugo->id]);
        RedisBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'redis_author_id' => $baudelaire->id]);

        $group = RedisBook::groupBy('redis_author_id');
        $this->assertEquals(2, $group->count());
    }

    /** @test */
    public function it_should_be_groupable_memory()
    {
        $hugo = MemoryAuthor::create(['name' => 'Victor Hugo']);
        $baudelaire = MemoryAuthor::create(['name' => 'Charles Baudelaire']);

        MemoryBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'memory_author_id' => $hugo->id]);
        MemoryBook::create(['title' => 'Les Contemplations', 'year' => 1855, 'memory_author_id' => $hugo->id]);
        MemoryBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'memory_author_id' => $baudelaire->id]);

        $group = MemoryBook::groupBy('memory_author_id');
        $this->assertEquals(2, $group->count());
    }

    /** @test */
    public function it_should_be_groupable_file()
    {
        $hugo = FileAuthor::create(['name' => 'Victor Hugo']);
        $baudelaire = FileAuthor::create(['name' => 'Charles Baudelaire']);

        FileBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'file_author_id' => $hugo->id]);
        FileBook::create(['title' => 'Les Contemplations', 'year' => 1855, 'file_author_id' => $hugo->id]);
        FileBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'file_author_id' => $baudelaire->id]);

        $group = FileBook::groupBy('file_author_id');
        $this->assertEquals(2, $group->count());
    }

    /** @test */
    public function it_should_be_sortable_by_model()
    {
        $hugo = datum('author')::create(['name' => 'Victor Hugo']);
        sleep(1);
        $baudelaire = datum('author')::create(['name' => 'Charles Baudelaire']);

        datum('book')::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id]);
        datum('book')::create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        datum('book')::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $this->assertEquals(2, datum('author')::latest()->first()->id);
        $this->assertEquals(1, datum('author')::oldest()->first()->id);
        $this->assertEquals('Les Contemplations', datum('book')::sortBy('title')->first()->title);
        $this->assertEquals('Notre Dame de Paris', datum('book')::sortByDesc('title')->first()->title);
    }

    /** @test */
    public function it_should_be_sortable()
    {
        $hugo = Author::create(['name' => 'Victor Hugo']);
        sleep(1);
        $baudelaire = Author::create(['name' => 'Charles Baudelaire']);

        Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id]);
        Book::create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        Book::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $this->assertEquals(2, Author::latest()->first()->id);
        $this->assertEquals(1, Author::oldest()->first()->id);
        $this->assertEquals('Les Contemplations', Book::sortBy('title')->first()->title);
        $this->assertEquals('Notre Dame de Paris', Book::sortByDesc('title')->first()->title);
    }

    /** @test */
    public function it_should_be_sortable_redis()
    {
        $hugo = RedisAuthor::create(['name' => 'Victor Hugo']);
        sleep(1);
        $baudelaire = RedisAuthor::create(['name' => 'Charles Baudelaire']);

        RedisBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' => $hugo->id]);
        RedisBook::create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        RedisBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $this->assertEquals(2, RedisAuthor::latest()->first()->id);
        $this->assertEquals(1, RedisAuthor::oldest()->first()->id);
        $this->assertEquals('Les Contemplations', RedisBook::sortBy('title')->first()->title);
        $this->assertEquals('Notre Dame de Paris', RedisBook::sortByDesc('title')->first()->title);
    }

    /** @test */
    public function it_should_be_sortable_memory()
    {
        $hugo = MemoryAuthor::create(['name' => 'Victor Hugo']);
        sleep(1);
        $baudelaire = MemoryAuthor::create(['name' => 'Charles Baudelaire']);

        MemoryBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'memory_author_id' => $hugo->id]);
        MemoryBook::create(['title' => 'Les Contemplations', 'year' => 1855, 'memory_author_id' => $hugo->id]);
        MemoryBook::create([
            'title' => 'Les Fleurs du Mal',
            'year' => 1867,
            'memory_author_id' => $baudelaire->id
        ]);

        $this->assertEquals(2, MemoryAuthor::latest()->first()->id);
        $this->assertEquals(1, MemoryAuthor::oldest()->first()->id);
        $this->assertEquals('Les Contemplations', MemoryBook::sortBy('title')->first()->title);
        $this->assertEquals('Notre Dame de Paris', MemoryBook::sortByDesc('title')->first()->title);
    }

    /** @test */
    public function it_should_be_sortable_file()
    {
        $hugo = FileAuthor::create(['name' => 'Victor Hugo']);
        sleep(1);
        $baudelaire = FileAuthor::create(['name' => 'Charles Baudelaire']);

        FileBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'file_author_id' => $hugo->id]);
        FileBook::create(['title' => 'Les Contemplations', 'year' => 1855, 'file_author_id' => $hugo->id]);
        FileBook::create([
            'title' => 'Les Fleurs du Mal',
            'year' => 1867,
            'file_author_id' => $baudelaire->id
        ]);

        $this->assertEquals(2, FileAuthor::latest()->first()->id);
        $this->assertEquals(1, FileAuthor::oldest()->first()->id);
        $this->assertEquals('Les Contemplations', FileBook::sortBy('title')->first()->title);
        $this->assertEquals('Notre Dame de Paris', FileBook::sortByDesc('title')->first()->title);
    }

    /** @test */
    public function it_should_be_manytomanyable_by_model()
    {
        $tag1 = datum('hyper_test_super_tag')::create(['name' => 'tag1']);
        $tag2 = datum('hyper_test_super_tag')::create(['name' => 'tag2']);
        $notreDame = datum('hyper_test_super_book')::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $this->assertEquals(0, datum('hyper_test_super_book_tag')::count());
        $notreDame->sync($tag1);
        $this->assertEquals(1, datum('hyper_test_super_book_tag')::count());
        $notreDame->sync($tag2);
        $this->assertEquals(2, datum('hyper_test_super_book_tag')::count());
        $p1 = $notreDame->sync($tag1, ['bar' => 'baz']);
        $notreDame->sync($tag2);

        $this->assertEquals(2, datum('hyper_test_super_book_tag')::count());
        $this->assertEquals('tag1', $notreDame->getPivots(get_class(datum('hyper_test_super_tag')))->first()->name);
        $this->assertEquals('baz', $p1->bar);

        $notreDame->detach($tag2);

        $this->assertEquals(1, datum('hyper_test_super_book_tag')::count());
    }


    /** @test */
    public function it_should_be_manytomanyable()
    {
        $tag1 = Tag::create(['name' => 'tag1']);
        $tag2 = Tag::create(['name' => 'tag2']);
        $notreDame = Book::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $this->assertEquals(0, BookTag::count());
        $notreDame->sync($tag1);
        $this->assertEquals(1, BookTag::count());
        $notreDame->sync($tag2);
        $this->assertEquals(2, BookTag::count());
        $p1 = $notreDame->sync($tag1, ['bar' => 'baz']);
        $notreDame->sync($tag2);

        $this->assertEquals(2, BookTag::count());
        $this->assertEquals('tag1', $notreDame->getPivots(Tag::class)->first()->name);
        $this->assertEquals('baz', $p1->bar);

        $notreDame->detach($tag2);

        $this->assertEquals(1, BookTag::count());
    }

    /** @test */
    public function it_should_be_manytomanyable_redis()
    {
        $tag1 = RedisTag::create(['name' => 'tag1']);
        $tag2 = RedisTag::create(['name' => 'tag2']);
        $notreDame = RedisBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $notreDame->sync($tag1);
        $notreDame->sync($tag2);
        $notreDame->sync($tag1);
        $notreDame->sync($tag2);

        $this->assertEquals(2, RedisBookTag::count());
        $this->assertEquals('tag1', $notreDame->getPivots(RedisTag::class)->first()->name);

        $notreDame->detach($tag2);

        $this->assertEquals(1, RedisBookTag::count());
    }

    /** @test */
    public function it_should_be_manytomanyable_memory()
    {
        $tag1 = MemoryTag::create(['name' => 'tag1']);
        $tag2 = MemoryTag::create(['name' => 'tag2']);
        $notreDame = MemoryBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $notreDame->sync($tag1);
        $notreDame->sync($tag2);
        $notreDame->sync($tag1);
        $notreDame->sync($tag2);

        $this->assertEquals(2, MemoryBookTag::count());
        $this->assertEquals('tag1', $notreDame->getPivots(MemoryTag::class)->first()->name);

        $notreDame->detach($tag2);

        $this->assertEquals(1, MemoryBookTag::count());
    }

    /** @test */
    public function it_should_be_manytomanyable_file()
    {
        $tag1 = FileTag::create(['name' => 'tag1']);
        $tag2 = FileTag::create(['name' => 'tag2']);
        $notreDame = FileBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831]);
        $notreDame->sync($tag1);
        $notreDame->sync($tag2);
        $notreDame->sync($tag1);
        $notreDame->sync($tag2);

        $this->assertEquals(2, FileBookTag::count());
        $this->assertEquals('tag1', $notreDame->getPivots(FileTag::class)->first()->name);

        $notreDame->detach($tag2);

        $this->assertEquals(1, FileBookTag::count());
    }

    /** @test */
    public function it_should_be_selectable_by_model()
    {
        datum('dummy')::create(['name' => 'foo', 'label' => 'bar']);
        $row = datum('dummy')::select('name')->first();

        $this->assertArrayNotHasKey('label', $row->toArray());
        $this->assertArrayNotHasKey('created_at', $row->toArray());
    }

    /** @test */
    public function it_should_be_selectable()
    {
        Dummy::create(['name' => 'foo', 'label' => 'bar']);
        $row = Dummy::select('name')->first();

        $this->assertArrayNotHasKey('label', $row->toArray());
        $this->assertArrayNotHasKey('created_at', $row->toArray());
    }

    /** @test */
    public function it_should_be_selectable_redis()
    {
        RedisAuthor::create(['name' => 'foo', 'label' => 'bar']);
        $row = RedisAuthor::select('name')->first();

        $this->assertArrayNotHasKey('label', $row->toArray());
        $this->assertArrayNotHasKey('created_at', $row->toArray());
    }

    /** @test */
    public function it_should_be_selectable_memory()
    {
        MemoryAuthor::create(['name' => 'foo', 'label' => 'bar']);
        $row = MemoryAuthor::select('name')->first();

        $this->assertArrayNotHasKey('label', $row->toArray());
        $this->assertArrayNotHasKey('created_at', $row->toArray());
    }

    /** @test */
    public function it_should_be_selectable_file()
    {
        FileAuthor::create(['name' => 'foo', 'label' => 'bar']);
        $row = FileAuthor::select('name')->first();

        $this->assertArrayNotHasKey('label', $row->toArray());
        $this->assertArrayNotHasKey('created_at', $row->toArray());
    }

    /** @test */
    public function we_can_use_or_query_by_model()
    {
        $country = datum('country', 0, ['name' => 'Canada']);
        datum('product', 0)->create(['name' => 'TV', 'price' => 500]);
        datum('product', 0)->create(['name' => 'Computer', 'price' => 1000]);
        datum('product', 0)->create(['name' => 'Book', 'price' => 15, 'country_id' => $country->getId()]);

        $this->assertEquals(3, datum('product', 0)->gt('price', 10)->count());
        $this->assertEquals(2, datum('product', 0)->gt('price', 100)->count());
        $this->assertEquals(3, datum('product', 0)->gt('price', 100)->orGt('price', 10)->count());
        $this->assertEquals(1, datum('product', 0)->gt('price', 100)->orGt('price', 10)->gte('price', 1000)->count());
        $this->assertEquals(1, datum('product', 0)->gt('price', 10)->whereCountryId($country->getId())->count());
        $this->assertEquals(1, datum('product', 0)->hasCountry()->count());
        $this->assertEquals(2, datum('product', 0)->doesntHaveCountry()->count());
    }

    /** @test */
    public function it_should_have_relations_by_model()
    {
        $hugo = datum('author')->create(['name' => 'Victor Hugo']);
        $baudelaire = datum('author')->create(['name' => 'Charles Baudelaire']);

        $notreDame = datum('book')->create([
            'title' => 'Notre Dame de Paris', 'year' => 1831, 'author_id' =>
            $hugo->id
        ]);

        $contemplations = datum('book')->create(['title' => 'Les Contemplations', 'year' => 1855, 'author_id' => $hugo->id]);
        $fleurs = datum('book')->create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'author_id' => $baudelaire->id]);

        $this->assertEquals($hugo->id, $notreDame->author->id);
        $this->assertEquals($hugo->id, $contemplations->author->id);
        $this->assertEquals($baudelaire->id, $fleurs->author->id);
        $this->assertEquals(2, $hugo->books->count());
        $this->assertEquals(1, $baudelaire->books->count());
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

    /** @test */
    public function it_should_have_relations_redis()
    {
        $hugo = RedisAuthor::create(['name' => 'Victor Hugo']);
        $baudelaire = RedisAuthor::create(['name' => 'Charles Baudelaire']);

        $notreDame = RedisBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'redis_author_id' => $hugo->id]);
        $contemplations = RedisBook::create(['title' => 'Les Contemplations', 'year' => 1855, 'redis_author_id' => $hugo->id]);
        $fleurs = RedisBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'redis_author_id' =>
            $baudelaire->id]);

        $this->assertEquals($hugo->id, $notreDame->redis_author->id);
        $this->assertEquals($hugo->id, $contemplations->redis_author->id);
        $this->assertEquals($baudelaire->id, $fleurs->redis_author->id);

        $this->assertEquals(2, $hugo->redis_books->count());
        $this->assertEquals(1, $baudelaire->redis_books->count());
    }

    /** @test */
    public function it_should_have_relations_memory()
    {
        $hugo = MemoryAuthor::create(['name' => 'Victor Hugo']);
        $baudelaire = MemoryAuthor::create(['name' => 'Charles Baudelaire']);

        $notreDame = MemoryBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'memory_author_id' => $hugo->id]);
        $contemplations = MemoryBook::create(['title' => 'Les Contemplations', 'year' => 1855, 'memory_author_id' => $hugo->id]);
        $fleurs = MemoryBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'memory_author_id' =>
            $baudelaire->id]);

        $this->assertEquals($hugo->id, $notreDame->memory_author->id);
        $this->assertEquals($hugo->id, $contemplations->memory_author->id);
        $this->assertEquals($baudelaire->id, $fleurs->memory_author->id);

        $this->assertEquals(2, $hugo->memory_books->count());
        $this->assertEquals(1, $baudelaire->memory_books->count());
    }

    /** @test */
    public function it_should_have_relations_file()
    {
        $hugo = FileAuthor::create(['name' => 'Victor Hugo']);
        $baudelaire = FileAuthor::create(['name' => 'Charles Baudelaire']);

        $notreDame = FileBook::create(['title' => 'Notre Dame de Paris', 'year' => 1831, 'file_author_id' => $hugo->id]);
        $contemplations = FileBook::create(['title' => 'Les Contemplations', 'year' => 1855, 'file_author_id' => $hugo->id]);
        $fleurs = FileBook::create(['title' => 'Les Fleurs du Mal', 'year' => 1867, 'file_author_id' =>
            $baudelaire->id]);

        $this->assertEquals($hugo->id, $notreDame->file_author->id);
        $this->assertEquals($hugo->id, $contemplations->file_author->id);
        $this->assertEquals($baudelaire->id, $fleurs->file_author->id);

        $this->assertEquals(2, $hugo->file_books->count());
        $this->assertEquals(1, $baudelaire->file_books->count());
    }
}
