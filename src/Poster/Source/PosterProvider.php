<?php

declare(strict_types=1);

namespace App\Poster\Source;

/**
 * A service the poster source draws candidates from, and the section its
 * candidates are shown in.
 *
 * Three facts about each provider have to agree: what the poster source calls it
 * on the wire, what the user is shown, and where its section sits. Keeping all
 * three here is what stops them drifting apart.
 *
 * The case *values* are the exact slugs the source emits, so {@see tryFrom()} is
 * the whole parse. A slug this enum does not carry is not an error — see
 * {@see PosterSearchResult::sections()}, which keeps those candidates rather than
 * discarding them.
 */
enum PosterProvider: string
{
    case Tmdb = 'tmdb';
    case TheTvdb = 'thetvdb';
    case Fanart = 'fanart.tv';

    /**
     * What the user is shown, which is each service's own spelling of its name —
     * not a title-cased slug.
     */
    public function label(): string
    {
        return match ($this) {
            self::Tmdb => 'TMDB',
            self::TheTvdb => 'TheTVDB',
            self::Fanart => 'fanart.tv',
        };
    }

    /**
     * The order sections appear in, which is the same for every item.
     *
     * **This is the order the provider attribution credits the same three
     * services in** (`templates/partials/_attribution.html.twig`, required by the
     * `application-shell` spec). The match is deliberate: two orderings of one set
     * of names in one product must not disagree. Reorder the footer and this
     * follows it.
     *
     * TMDB leads for a second reason. It is the only provider that supplies a
     * score, so the source's own ranking puts a TMDB poster first more often than
     * not — leading with it keeps "best first" true most of the time even though
     * a fixed section order gives up that guarantee.
     *
     * Derived from declaration order rather than listed again, so a provider
     * added to this enum cannot be forgotten here and silently lose its section.
     *
     * @return list<self>
     */
    public static function inSectionOrder(): array
    {
        return self::cases();
    }
}
