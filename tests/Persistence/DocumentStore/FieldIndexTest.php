<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\Persistence\DocumentStore;

use Prooph\EventMachine\Persistence\DocumentStore\FieldIndex;
use Prooph\EventMachineTest\BasicTestCase;

final class FieldIndexTest extends BasicTestCase
{
    /**
     * @test
     */
    public function it_creates_field_index_from_array()
    {
        $index = FieldIndex::fromArray([
            'field' => 'testField',
            'sort' => FieldIndex::SORT_DESC,
            'unique' => false,
        ]);

        $this->assertEquals('testField', $index->field());
        $this->assertEquals(FieldIndex::SORT_DESC, $index->sort());
        $this->assertEquals(false, $index->unique());
    }

    /**
     * @test
     */
    public function it_creates_field_index_for_field_in_multi_field_index()
    {
        $index = FieldIndex::forFieldInMultiFieldIndex('testField');

        $this->assertEquals(
            [
                'field' => 'testField',
                'sort' => FieldIndex::SORT_ASC,
                'unique' => false,
            ],
            $index->toArray()
        );
    }

    /**
     * @test
     */
    public function it_fails_creating_index_for_invalid_field()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field must not be empty');

        $index = FieldIndex::forField('', FieldIndex::SORT_ASC, true);
    }

    /**
     * @test
     */
    public function it_fails_creating_index_with_invalid_sort_order()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sort order should be either 1 or -1');

        $index = FieldIndex::forField('testField', 0, true);
    }
}
