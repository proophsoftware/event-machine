<?php
declare(strict_types = 1);

namespace Prooph\EventMachineTest\Data;

use PHPUnit\Framework\TestCase;
use Prooph\EventMachineTest\Data\Stubs\TestProduct;
use Prooph\EventMachineTest\Data\Stubs\TestProductVO;

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

    /**
     * @test
     */
    public function it_can_handle_value_objects()
    {
        $expectedData = [
            'id' => 123,
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
        ];

        $product = TestProductVO::fromArray($expectedData);

        self::assertSame(123, $product->id()->toInt());
        self::assertSame('Test product', $product->name()->toString());
        self::assertSame('An immutable product record', $product->description());
        self::assertSame(12.50, $product->price()->amount()->toFloat());
        self::assertSame('EUR', $product->price()->currency()->toString());
        self::assertTrue($product->active()->toBool());
        self::assertSame([
            'Tag1',
            'Tag2',
        ], $product->tags());

        $productData = $product->toArray();

        self::assertSame($expectedData, $productData);
    }
}
