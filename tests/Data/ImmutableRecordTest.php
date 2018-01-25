<?php
declare(strict_types = 1);

namespace Prooph\EventMachineTest\Data;

use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Json;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachineTest\Data\Stubs\TestBuildingVO;
use Prooph\EventMachineTest\Data\Stubs\TestCommentVO;
use Prooph\EventMachineTest\Data\Stubs\TestDefaultPrice;
use Prooph\EventMachineTest\Data\Stubs\TestIdentityVO;
use Prooph\EventMachineTest\Data\Stubs\TestProduct;
use Prooph\EventMachineTest\Data\Stubs\TestProductVO;
use Prooph\EventMachineTest\Data\Stubs\TestUserVO;

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

    /**
     * @test
     */
    public function it_uses_short_class_name_as_type()
    {
        self::assertEquals('TestProduct', TestProduct::__type());
    }

    /**
     * @test
     */
    public function it_uses_prop_type_map_to_generate_type_schema()
    {
        $expectedSchema = JsonSchema::object([
            'productId' => JsonSchema::integer(),
            'name' => JsonSchema::string(),
            'description' => JsonSchema::string(),
            'price' => JsonSchema::typeRef('TestProductPrice'),
            'active' => JsonSchema::boolean(),
            'tags' => JsonSchema::array(JsonSchema::string()),
        ]);

        self::assertEquals($expectedSchema, TestProduct::__schema());
    }

    /**
     * @test
     */
    public function it_respects_classes_as_array_item_type_and_nullable_props()
    {
        $expectedSchema = JsonSchema::object([
            'id' => JsonSchema::string(),
            'name' => JsonSchema::string(),
            'age' => JsonSchema::nullOr(JsonSchema::integer()),
            'identities' => JsonSchema::array(JsonSchema::typeRef('TestIdentityVO')),
        ]);

        self::assertEquals($expectedSchema, TestUserVO::__schema());
    }

    /**
     * @test
     */
    public function it_accepts_and_converts_nullable_prop()
    {
        $data = [
            'id' => '1',
            'name' => 'Alex',
            'age' => null,
            'identities' => [
                'email' => 'contact@prooph.de',
                'password' => 'dev1234'
            ]
        ];

        $testUser = TestUserVO::fromArray($data);

        $this->assertEquals($data, $testUser->toArray());
    }

    /**
     * @test
     */
    public function it_can_handle_nullable_objects()
    {
        $data = [
            'text' => 'this is a comment',
            'user' => null
        ];

        $testComment = TestCommentVO::fromArray($data);

        $this->assertEquals($data, $testComment->toArray());
    }

    /**
     * @test
     */
    public function it_uses_default_value_if_val_is_not_passed_to_constructor()
    {
        $testBuilding = TestBuildingVO::fromArray(['name' => 'My House']);

        $this->assertEquals(['name' => 'My House', 'type' => 'house'], $testBuilding->toArray());
    }

    /**
     * @test
     */
    public function it_calls_init_to_give_immutable_record_the_chance_to_set_defaults_before_not_null_assertion()
    {
        $defaultPrice = TestDefaultPrice::fromArray([]);

        $this->assertEquals(['amount' => 9.99, 'currency' => 'EUR'], $defaultPrice->toArray());
    }
}
