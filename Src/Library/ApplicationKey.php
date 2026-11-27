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
    public function acronym(string $name): string
    {
        $ascii = $this->toAscii($name);
        preg_match_all("/[A-Z]+(?![a-z])|[A-Z]?[a-z]+|\d+/", $ascii, $matches);

        $acronym = self::FALLBACK_ACRONYM;
        if (count($matches[0]) > 0) {
            $acronym = "";
        }

        foreach ($matches[0] as $word) {
            $acronym .= strtolower($word[0]);
        }

        return $acronym;

    }//end acronym()


    /**
     * The key of an application. When another application already has the key,
     * pass a sequence greater than 1 to get the next free one
     * (pmk-pm-key, pmk-pm2-key, pmk-pm3-key, …).
     *
     * @param string  $name     The application's name.
     * @param integer $sequence 1 for the plain key, 2 or more for the next ones.
     *
     * @return string
     */
    public function build(string $name, int $sequence): string
    {
        $acronym = $this->acronym($name);
        if ($sequence > 1) {
            $acronym = sprintf("%s%d", $acronym, $sequence);
        }

        return sprintf("%s%s%s", self::PREFIX, $acronym, self::SUFFIX);

    }//end build()


    /**
     * Whether a key follows the pattern for the given name (any sequence).
     *
     * @param string $key  The key.
     * @param string $name The application's name.
     *
     * @return boolean
     */
    public function matchesName(string $key, string $name): bool
    {
        $pattern = sprintf(
            "/^%s([2-9]|[1-9]\d+)?%s$/",
            preg_quote(sprintf("%s%s", self::PREFIX, $this->acronym($name)), "/"),
            preg_quote(self::SUFFIX, "/")
        );

        return preg_match($pattern, $key) === 1;

    }//end matchesName()


    /**
     * A new random token.
     *
     * @return string
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes((self::TOKEN_LENGTH / 2)));

    }//end generateToken()


    /**
     * A token with all but its last 4 characters hidden, for listing.
     *
     * @param string $token The token.
     *
     * @return string
     */
    public function mask(string $token): string
    {
        $token  = trim($token);
        $masked = str_repeat("•", 4);
        if (strlen($token) > 4) {
            $masked = sprintf("%s%s", str_repeat("•", 8), substr($token, -4));
        }

        return $masked;

    }//end mask()


    /**
     * The name with accents dropped and other non-ASCII characters removed.
     *
     * @param string $name The name.
     *
     * @return string
     */
    private function toAscii(string $name): string
    {
        if (function_exists("transliterator_transliterate") === true) {
            $result = transliterator_transliterate("NFD; [:Nonspacing Mark:] Remove; NFC", $name);
            if (is_string($result) === true) {
                $name = $result;
            }
        }

        if (function_exists("iconv") === true) {
            $result = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $name);
            if (is_string($result) === true) {
                $name = $result;
            }
        }

        return (string) preg_replace("/[^\x20-\x7E]/", "", $name);

    }//end toAscii()


}//end class
