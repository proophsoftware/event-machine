<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore\Filter;

use Codeliner\ArrayReader\ArrayReader;

final class EqFilter implements Filter
{
    /**
     * Nested props are accessed using dot notation
     *
     * @var string
     */
    private $prop;

    /**
     * @var mixed
     */
    private $val;

    public function __construct(string $prop, $val)
    {
        $this->prop = $prop;
        $this->val = $val;
    }

    /**
     * @return string
     */
    public function prop(): string
    {
        return $this->prop;
    }

    /**
     * @return mixed
     */
    public function val()
    {
        return $this->val;
    }

    public function match(array $doc): bool
    {
        $reader = new ArrayReader($doc);

        $prop = $reader->mixedValue($this->prop, self::NOT_SET_PROPERTY);

        if($prop === self::NOT_SET_PROPERTY) {
            return false;
        }

        return $prop === $this->val;
    }
}
