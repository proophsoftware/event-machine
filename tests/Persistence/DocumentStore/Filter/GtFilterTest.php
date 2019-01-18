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

use Prooph\EventMachine\Persistence\DocumentStore\Filter\GtFilter;
use Prooph\EventMachineTest\BasicTestCase;

class GtFilterTest extends BasicTestCase
{
    use FilterTestHelperTrait;

    /**
     * @test
     */
    public function it_filters_docs_with_gt_filter()
    {
        $this->loadFixtures();

        $animals = $this->store->filterDocs(
            $this->collection,
            new GtFilter('age', 5)
        );

        $names = \iterator_to_array($this->extractFieldIntoList('name', $animals));

        $this->assertEquals('Jack, Hasso', \implode(', ', $names));
    }
}
