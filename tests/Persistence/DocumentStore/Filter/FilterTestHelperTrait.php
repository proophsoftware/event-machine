<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Persistence\DocumentStore\Filter;

use Prooph\EventMachine\Persistence\DocumentStore\InMemoryDocumentStore;
use Prooph\EventMachine\Persistence\InMemoryConnection;

trait FilterTestHelperTrait
{
    /**
     * @var InMemoryDocumentStore
     */
    private $store;

    /**
     * @var string test collection
     */
    private $collection;

    protected function setUp()
    {
        $this->store = new InMemoryDocumentStore(new InMemoryConnection());
        $this->collection = 'test';
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
        $this->store->addCollection($this->collection);

        $this->store->addDoc($this->collection, '1', [
            'name' => 'Jack',
            'animal' => 'dog',
            'age' => 6,
        ]);

        $this->store->addDoc($this->collection, '2', [
            'name' => 'Hasso',
            'animal' => 'dog',
            'age' => 7,
            'race' => 'Golden Retriever',
        ]);

        $this->store->addDoc($this->collection, '3', [
            'name' => 'Gini',
            'animal' => 'cat',
            'age' => 5,
        ]);

        $this->store->addDoc($this->collection, '4', [
            'name' => 'Tiger',
            'animal' => 'cat',
            'age' => 3,
            'status' => ['hungry', 'tired'],
        ]);

        $this->store->addDoc($this->collection, '5', [
            'name' => 'Quak',
            'animal' => 'duck',
            'age' => 1,
            'race' => null,
        ]);
    }
}
