<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore\Filter;

final class OrFilter implements Filter
{
    private $aFilter;

    private $bFilter;

    public function __construct(Filter $aFilter, Filter $bFilter)
    {
        $this->aFilter = $aFilter;
        $this->bFilter = $bFilter;
    }

    /**
     * @return Filter
     */
    public function aFilter(): Filter
    {
        return $this->aFilter;
    }

    /**
     * @return Filter
     */
    public function bFilter(): Filter
    {
        return $this->bFilter;
    }

    public function match(array $doc): bool
    {
        return $this->aFilter->match($doc) || $this->bFilter->match($doc);
    }
}
