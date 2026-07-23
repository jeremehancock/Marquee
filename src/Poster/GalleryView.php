<?php

declare(strict_types=1);

namespace App\Poster;

/**
 * The current gallery view: either one real category or the aggregate "All"
 * view over every category. "All" is not a stored category and has no directory
 * of its own — it is addressed by the reserved slug `all`. Keeping it out of the
 * PosterCategory enum confines the pseudo-category to this one place.
 */
final class GalleryView
{
    public const ALL_SLUG = 'all';

    private function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly ?PosterCategory $category,
    ) {
    }

    public static function all(): self
    {
        return new self(self::ALL_SLUG, 'All', null);
    }

    public static function forCategory(PosterCategory $category): self
    {
        return new self($category->value, $category->label(), $category);
    }

    /**
     * Resolve a URL slug to a view, or null when it is neither the reserved
     * `all` slug nor one of the four categories.
     */
    public static function fromSlug(string $slug): ?self
    {
        if ($slug === self::ALL_SLUG) {
            return self::all();
        }

        $category = PosterCategory::fromSlug($slug);

        return $category !== null ? self::forCategory($category) : null;
    }

    public function isAll(): bool
    {
        return $this->category === null;
    }

    /**
     * The categories this view lists: every category for All, otherwise the one.
     *
     * @return list<PosterCategory>
     */
    public function categories(): array
    {
        return $this->category === null ? PosterCategory::all() : [$this->category];
    }
}
