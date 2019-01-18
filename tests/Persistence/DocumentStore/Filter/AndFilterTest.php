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

use Prooph\EventMachine\Persistence\DocumentStore\Filter\AndFilter;
use Prooph\EventMachine\Persistence\DocumentStore\Filter\GtFilter;
use Prooph\EventMachine\Persistence\DocumentStore\Filter\LtFilter;
use Prooph\EventMachineTest\BasicTestCase;

class AndFilterTest extends BasicTestCase
{
    use FilterTestHelperTrait;

    /**
     * @test
     */
    public function it_filters_docs_with_and_filter()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs(
            $this->collection,
            new AndFilter(new LtFilter('age', 5), new GtFilter('age', 1))
        );

        $names = \iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Tiger', \implode(', ', $names));
    }
}
