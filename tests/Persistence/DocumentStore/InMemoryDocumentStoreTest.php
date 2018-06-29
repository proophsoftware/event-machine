<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Persistence\DocumentStore;

use Prooph\EventMachine\Persistence\DocumentStore\Filter\AnyFilter;
use Prooph\EventMachine\Persistence\DocumentStore\Filter\EqFilter;
use Prooph\EventMachine\Persistence\DocumentStore\InMemoryDocumentStore;
use Prooph\EventMachine\Persistence\DocumentStore\OrderBy\AndOrder;
use Prooph\EventMachine\Persistence\DocumentStore\OrderBy\Asc;
use Prooph\EventMachine\Persistence\DocumentStore\OrderBy\Desc;
use Prooph\EventMachine\Persistence\InMemoryConnection;
use Prooph\EventMachineTest\BasicTestCase;

final class InMemoryDocumentStoreTest extends BasicTestCase
{
    private const COLLECTION = 'test';

    /**
     * @var InMemoryDocumentStore
     */
    private $store;

    protected function setUp()
    {
        $this->store = new InMemoryDocumentStore(new InMemoryConnection());
    }

    /**
     * @test
     */
    public function it_filters_docs()
    {
        $this->loadFixtures();

        $dogs = $this->store->filterDocs(self::COLLECTION, new EqFilter('animal', 'dog'));

        $dogNames = iterator_to_array($this->extractFieldIntoList('name', $dogs));

        $this->assertEquals('Jack, Hasso', implode(', ', $dogNames));
    }

    /**
     * @test
     */
    public function it_orders_docs_ASC()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs(self::COLLECTION, new AnyFilter(), null, null, Asc::fromString('name'));

        $names = iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Gini, Hasso, Jack, Quak, Tiger', implode(', ', $names));
    }

    /**
     * @test
     */
    public function it_orders_docs_DESC()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs(self::COLLECTION, new AnyFilter(), null, null, Desc::fromString('name'));

        $names = iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Tiger, Quak, Jack, Hasso, Gini', implode(', ', $names));
    }

    /**
     * @test
     */
    public function it_orders_by_multiple_fields()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs(
            self::COLLECTION,
            new AnyFilter(),
            null,
            null,
            AndOrder::by(Asc::byProp('animal'), Desc::byProp('age'))
        );

        $names = iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Gini, Tiger, Hasso, Jack, Quak', implode(', ', $names));
    }

    /**
     * @test
     */
    public function it_skips_docs_after_ordering()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs(self::COLLECTION, new AnyFilter(), 2, null, AndOrder::by(Asc::byProp('animal'), Asc::byProp('age')));

        $names = iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Jack, Hasso, Quak', implode(', ', $names));
    }

    /**
     * @test
     */
    public function it_limits_docs_after_ordering()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs(self::COLLECTION, new AnyFilter(), null, 3, AndOrder::by(Asc::byProp('animal'), Asc::byProp('age')));

        $names = iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Tiger, Gini, Jack', implode(', ', $names));
    }

    /**
     * @test
     */
    public function it_skips_and_limits_docs_after_ordering()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs(self::COLLECTION, new AnyFilter(), 2, 2, AndOrder::by(Asc::byProp('animal'), Asc::byProp('age')));

        $names = iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Jack, Hasso', implode(', ', $names));
    }

    private function extractFieldIntoList(string $field, \Traversable $docs): \Generator
    {
        foreach ($docs as $doc) {
            if (array_key_exists($field, $doc)) {
                yield $doc[$field];
                continue;
            }

            yield null;
        }
    }

    private function loadFixtures()
    {
        $this->store->addCollection(self::COLLECTION);

        $this->store->addDoc(self::COLLECTION, '1', [
            'name' => 'Jack',
            'animal' => 'dog',
            'age' => 6,
        ]);

        $this->store->addDoc(self::COLLECTION, '2', [
            'name' => 'Hasso',
            'animal' => 'dog',
            'age' => 7,
        ]);

        $this->store->addDoc(self::COLLECTION, '3', [
            'name' => 'Gini',
            'animal' => 'cat',
            'age' => 5,
        ]);

        $this->store->addDoc(self::COLLECTION, '4', [
            'name' => 'Tiger',
            'animal' => 'cat',
            'age' => 3,
        ]);

        $this->store->addDoc(self::COLLECTION, '5', [
            'name' => 'Quak',
            'animal' => 'duck',
            'age' => 1,
        ]);
    }
}
