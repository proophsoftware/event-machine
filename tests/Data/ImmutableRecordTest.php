<?php
declare(strict_types = 1);

namespace Prooph\EventMachineTest\Data;

use PHPUnit\Framework\TestCase;
use Prooph\EventMachineTest\Data\Stubs\TestProduct;

final class ImmutableRecordTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_created_from_native_data()
    {
        $product = TestProduct::fromArray([
            'productId' => 123,
            'name' => 'Test product',
            'description' => 'An immutable product record',
            'price' => [
                'amount' => 12.50,
                'currency' => 'EUR'
            ],
            'active' => true,
            'tags' => [
                'Tag1',
                'Tag2',
            ]
        ]);

        self::assertSame(123, $product->productId());
        self::assertSame('Test product', $product->name());
        self::assertSame('An immutable product record', $product->description());
        self::assertSame([
            'amount' => 12.50,
            'currency' => 'EUR'
        ], $product->price()->toArray());
        self::assertTrue($product->active());
        self::assertSame([
            'Tag1',
            'Tag2',
        ], $product->tags());
    }
}
