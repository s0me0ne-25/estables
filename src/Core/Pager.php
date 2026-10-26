<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * Page numbers of a long list: which page is shown, what range of rows it
 * holds, and which page links to draw.
 */
final readonly class Pager
{
    public int $page;
    public int $pages;

    public function __construct(int $requested, public int $total, public int $perPage)
    {
        $this->pages = max(1, (int) ceil($total / $perPage));
        $this->page = max(1, min($requested, $this->pages));
    }

    public static function fromQuery(Request $request, int $total, int $perPage): self
    {
        $page = $request->query['page'] ?? '1';

        return new self(\is_string($page) && ctype_digit($page) ? (int) $page : 1, $total, $perPage);
    }

    /**
     * First and last row on the page, 1-based; [0, 0] for an empty list.
     *
     * @return array{0: int, 1: int}
     */
    public function range(): array
    {
        if ($this->total === 0) {
            return [0, 0];
        }
        $first = ($this->page - 1) * $this->perPage + 1;

        return [$first, min($this->total, $first + $this->perPage - 1)];
    }

    /**
     * Page links: the first, the last and two around the current one;
     * null marks a gap.
     *
     * @return list<int|null>
     */
    public function links(): array
    {
        $show = array_unique([1, $this->pages, ...range(max(1, $this->page - 2), min($this->pages, $this->page + 2))]);
        sort($show);
        $result = [];
        $previous = 0;
        foreach ($show as $page) {
            if ($page > $previous + 1) {
                $result[] = null;
            }
            $result[] = $page;
            $previous = $page;
        }

        return $result;
    }
}
