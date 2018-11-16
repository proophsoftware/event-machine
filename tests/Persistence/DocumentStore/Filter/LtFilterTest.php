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

use Prooph\EventMachine\Persistence\DocumentStore\Filter\LtFilter;
use Prooph\EventMachineTest\BasicTestCase;

class LtFilterTest extends BasicTestCase
{
    use FilterTestHelperTrait;

    /**
     * @test
     */
    public function it_filters_docs_with_lt_filter()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs($this->collection, new LtFilter('age', 5));

        $names = \iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Tiger, Quak', \implode(', ', $names));
    }
}
