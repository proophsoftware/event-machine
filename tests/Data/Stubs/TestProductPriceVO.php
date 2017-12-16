<?php

declare(strict_types=1);

namespace Prooph\EventMachineTest\Data\Stubs;

use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\Data\ImmutableRecordLogic;

final class TestProductPriceVO implements ImmutableRecord
{
    use ImmutableRecordLogic;

    /**
     * @var TestAmountVO
     */
    private $amount;

    /**
     * @var TestCurrencyVO
     */
    private $currency;

    /**
     * @return TestAmountVO
     */
    public function amount(): TestAmountVO
    {
        return $this->amount;
    }

    /**
     * @return TestCurrencyVO
     */
    public function currency(): TestCurrencyVO
    {
        return $this->currency;
    }
}
