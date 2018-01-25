<?php
declare(strict_types=1);

namespace Prooph\EventMachineTest\Data\Stubs;

use Prooph\EventMachine\Data\ImmutableRecord;
use Prooph\EventMachine\Data\ImmutableRecordLogic;

final class TestDefaultPrice implements ImmutableRecord
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

    private function init(): void
    {
        $this->amount = TestAmountVO::fromFloat(9.99);
        $this->currency = TestCurrencyVO::fromString('EUR');
    }

    /**
     * @return TestAmountVO
     */
    public function amount(): TestAmountVO
    {
        return $this->amount;
    }

    /**
     * @return null|TestCurrencyVO
     */
    public function currency(): TestCurrencyVO
    {
        return $this->currency;
    }


}
