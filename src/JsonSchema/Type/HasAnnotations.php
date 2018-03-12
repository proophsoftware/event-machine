<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventMachine\JsonSchema\Type;

use Prooph\EventMachine\JsonSchema\AnnotatedType;

trait HasAnnotations
{
    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string|null
     */
    protected $description;

    public function entitled(string $title): AnnotatedType
    {
        $cp = clone $this;

        $cp->title = $title;

        return $cp;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function describedAs(string $description): AnnotatedType
    {
        $cp = clone $this;

        $cp->description = $description;

        return $cp;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function annotations(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
}
