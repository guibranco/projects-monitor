<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Builds the credentials of an application: the key, which follows the
 * pattern pmk-{acronym}-key (the acronym of the application's name), and
 * the token, 32 random hexadecimal characters.
 */
class ApplicationKey
{
    public const PREFIX = "pmk-";

    public const SUFFIX = "-key";

    /**
     * The acronym used when a name has no letters or digits.
     */
    public const FALLBACK_ACRONYM = "app";

    /**
     * The length of a token (the applications.token column is CHAR(32)).
     */
    public const TOKEN_LENGTH = 32;

    /**
     * The acronym of an application's name: the first character of each word,
     * lowercase. Words are split on spaces, punctuation and camelCase
     * boundaries, and accents are dropped ("Projects Monitor" → "pm",
     * "WebhooksHandler" → "wh", "API BR - Vagas" → "abv").
     *
     * @param string $name The application's name.
     *
     * @return string
     */
    public static function acronym(string $name): string
    {
        $ascii = self::toAscii($name);
        preg_match_all("/[A-Z]+(?![a-z])|[A-Z]?[a-z]+|[0-9]+/", $ascii, $matches);

        $acronym = "";
        foreach ($matches[0] as $word) {
            $acronym .= strtolower($word[0]);
        }

        return $acronym === "" ? self::FALLBACK_ACRONYM : $acronym;
    }

    /**
     * The key of an application. When another application already has the key,
     * pass a sequence greater than 1 to get the next free one
     * (pmk-pm-key, pmk-pm2-key, pmk-pm3-key, …).
     *
     * @param string $name     The application's name.
     * @param int    $sequence 1 for the plain key, 2 or more for the next ones.
     *
     * @return string
     */
    public static function build(string $name, int $sequence = 1): string
    {
        $acronym = self::acronym($name);
        if ($sequence > 1) {
            $acronym .= $sequence;
        }

        return self::PREFIX . $acronym . self::SUFFIX;
    }

    /**
     * Whether a key follows the pattern for the given name (any sequence).
     *
     * @param string $key  The key.
     * @param string $name The application's name.
     *
     * @return bool
     */
    public static function matchesName(string $key, string $name): bool
    {
        $pattern = "/^" . preg_quote(self::PREFIX . self::acronym($name), "/") . "([2-9]|[1-9][0-9]+)?" . preg_quote(self::SUFFIX, "/") . "$/";

        return preg_match($pattern, $key) === 1;
    }

    /**
     * A new random token.
     *
     * @return string
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
    }

    /**
     * A token with all but its last 4 characters hidden, for listing.
     *
     * @param string $token The token.
     *
     * @return string
     */
    public static function mask(string $token): string
    {
        $token = trim($token);
        if (strlen($token) <= 4) {
            return str_repeat("•", 4);
        }

        return str_repeat("•", 8) . substr($token, -4);
    }

    /**
     * The name with accents transliterated and other non-ASCII characters dropped.
     *
     * @param string $name The name.
     *
     * @return string
     */
    private static function toAscii(string $name): string
    {
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create("NFD; [:Nonspacing Mark:] Remove; NFC");
            if ($transliterator !== null) {
                $result = $transliterator->transliterate($name);
                if ($result !== false) {
                    $name = $result;
                }
            }
        }

        if (function_exists("iconv")) {
            $result = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $name);
            if ($result !== false) {
                $name = $result;
            }
        }

        return (string) preg_replace("/[^\x20-\x7E]/", "", $name);
    }
}
