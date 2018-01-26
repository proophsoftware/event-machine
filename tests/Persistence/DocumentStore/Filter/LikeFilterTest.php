<?php
declare(strict_types=1);

namespace Prooph\EventMachineTest\Persistence\DocumentStore\Filter;

use Prooph\EventMachine\Persistence\DocumentStore\Filter\LikeFilter;
use Prooph\EventMachineTest\BasicTestCase;

final class LikeFilterTest extends BasicTestCase
{
    /**
     * @test
     * @dataProvider provideLikeFilter
     */
    public function it_matches(LikeFilter $filter, bool $match)
    {
        $doc = [
            'comment' => 'prooph is awesome'
        ];

        $this->assertSame($match, $filter->match($doc));
    }

    public function provideLikeFilter(): array
    {
        return [
            [new LikeFilter('comment', '%is%'), true],
            [new LikeFilter('comment', 'is%'), false],
            [new LikeFilter('comment', 'is'), false],
            [new LikeFilter('comment', '%is'), false],
            [new LikeFilter('comment', '%prooph'), false],
            [new LikeFilter('comment', 'prooph%'), true],
            [new LikeFilter('comment', '%awesome'), true],
            [new LikeFilter('comment', 'awesome%'), false],
            [new LikeFilter('comment', '%IS%'), true],
        ];
    }
}
