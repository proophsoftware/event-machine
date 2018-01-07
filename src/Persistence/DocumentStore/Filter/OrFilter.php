<?php
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
