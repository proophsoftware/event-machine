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

use Prooph\EventMachine\Persistence\DocumentStore\MultiFieldIndex;
use Prooph\EventMachineTest\BasicTestCase;

final class MultiFieldIndexTest extends BasicTestCase
{
    /**
     * @test
     */
    public function it_creates_multi_field_index_for_fields()
    {
        $index = MultiFieldIndex::forFields(['testField1', 'testField2'], true);

        $this->assertEquals(
            [
                '{"field":"testField1","sort":1,"unique":false}',
                '{"field":"testField2","sort":1,"unique":false}',
            ],
            \array_map(
                function ($index) {
                    return (string) $index;
                },
                $index->fields()
            )
        );

        $this->assertEquals(true, $index->unique());

        $this->assertEquals(
            [
                'fields' => ['testField1', 'testField2'],
                'unique' => true,
            ],
            $index->toArray()
        );
    }
}
