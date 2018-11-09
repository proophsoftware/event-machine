<?php
declare(strict_types=1);

namespace ProophExample\CustomMessages\Util;

trait ApplyPayload
{
    public function __construct(array $payload)
    {
        foreach ($payload as $key => $val) {
            $this->{$key} = $val;
        }
    }
}
