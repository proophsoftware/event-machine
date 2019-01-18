<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachineTest\JsonSchema\Type;

use Prooph\EventMachine\JsonSchema\Type\FloatType;
use Prooph\EventMachineTest\BasicTestCase;

final class FloatTypeTest extends BasicTestCase
{
    /**
     * @test
     */
    public function it_creates_float_type_with_minimum()
    {
        $floatType = (new FloatType())->withMinimum(0.2);

        $this->assertEquals(
            [
                'type' => 'number',
                'minimum' => 0.2,
            ],
            $floatType->toArray()
        );
    }

    /**
     * @test
     */
    public function it_creates_float_type_with_maximum()
    {
        $floatType = (new FloatType())->withMaximum(0.7);

        $this->assertEquals(
            [
                'type' => 'number',
                'maximum' => 0.7,
            ],
            $floatType->toArray()
        );
    }

    /**
     * @test
     */
    public function it_creates_float_type_with_range()
    {
        $floatType = (new FloatType())->withRange(0.2, 0.7);

        $this->assertEquals(
            [
                'type' => 'number',
                'minimum' => 0.2,
                'maximum' => 0.7,
            ],
            $floatType->toArray()
        );
    }

    /**
     * @test
     */
    public function it_creates_float_with_custom_validation_through_constructor()
    {
        $floatType = new FloatType(['multipleOf' => 2.5]);

        $this->assertEquals(
            [
                'type' => 'number',
                'multipleOf' => 2.5,
            ],
            $floatType->toArray()
        );
    }
}
