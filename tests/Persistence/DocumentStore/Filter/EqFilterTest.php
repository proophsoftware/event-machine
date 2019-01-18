<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Persistence\DocumentStore\Filter;

use Prooph\EventMachine\Persistence\DocumentStore\Filter\EqFilter;
use Prooph\EventMachineTest\BasicTestCase;

class EqFilterTest extends BasicTestCase
{
    use FilterTestHelperTrait;

    /**
     * @test
     */
    public function it_filters_docs_with_eq_filter()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs($this->collection, new EqFilter('animal', 'duck'));

        $names = \iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Quak', \implode(', ', $names));
    }

    /**
     * @test
     */
    public function it_filters_docs_with_eq_null_filter()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs($this->collection, new EqFilter('race', null));

        $names = \iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Quak', \implode(', ', $names));
    }
}
