<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore\Filter;

use Codeliner\ArrayReader\ArrayReader;

final class ExistsFilter implements Filter
{
    /**
     * Nested props are accessed using dot notation
     *
     * @var string
     */
    private $prop;

    public function __construct(string $prop)
    {
        $this->prop = $prop;
    }

    /**
     * @return string
     */
    public function prop(): string
    {
        return $this->prop;
    }

    public function match(array $doc): bool
    {
        $reader = new ArrayReader($doc);

        $prop = $reader->mixedValue($this->prop, self::NOT_SET_PROPERTY);

        return $prop !== self::NOT_SET_PROPERTY;
    }
}
