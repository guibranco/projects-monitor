<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Turns failures into text and JSON friendly data for the error reports:
 * renders message templates, summarises HTML error pages and Throwables.
 */
class ErrorText
{
    /**
     * Deepest array level kept by normalize().
     */
    private const MAX_DEPTH = 5;

    /**
     * Longest rendered placeholder value.
     */
    private const MAX_VALUE_LENGTH = 300;


    /**
     * Turns an error text into one readable line: an HTML error page
     * (e.g. a proxy's 503 page) becomes its text, whitespace is collapsed.
     *
     * @param string $message The message.
     *
     * @return string The summary.
     */
    public function summarize(string $message): string
    {
        $text = $message;
        if (preg_match('/<(html|body|!doctype)\b/i', $text) === 1) {
            $text = $this->replace('/<(style|script|head)\b[^>]*>.*?<\/\1>/is', " ", $text);
            $text = $this->replace('/<[^>]*>/', " ", $text);
            $text = html_entity_decode($text, (ENT_QUOTES | ENT_HTML5), "UTF-8");
        }

        return trim($this->replace('/\s+/u', " ", $text));

    }//end summarize()


    /**
     * Renders the `{name}` placeholders of a message template (dotted names reach nested arrays).
     *
     * @param string $message The message template.
     * @param array  $context The values.
     *
     * @return string The rendered message; unknown placeholders are kept as they are.
     */
    public function render(string $message, array $context): string
    {
        $callback = function (array $matches) use ($context): string {
            $value = $context;
            foreach (explode(".", $matches[1]) as $key) {
                if (is_array($value) === false || array_key_exists($key, $value) === false) {
                    return $matches[0];
                }

                $value = $value[$key];
            }

            return $this->truncate($this->stringify($value), self::MAX_VALUE_LENGTH);
        };

        $rendered = preg_replace_callback('/\{([A-Za-z0-9_.]+)\}/', $callback, $message);
        if ($rendered === null) {
            return $message;
        }

        return $rendered;

    }//end render()


    /**
     * Makes a value JSON friendly: Throwables become arrays, other objects their class.
     *
     * @param mixed   $value The value.
     * @param integer $depth The current depth, 0 at the top.
     *
     * @return mixed The normalised value.
     */
    public function normalize(mixed $value, int $depth): mixed
    {
        $result = $value;
        if ($value instanceof \Throwable) {
            $result = $this->describeThrowable($value);
        }

        if (is_array($value) === true) {
            $result = $this->normalizeArray($value, $depth);
        }

        if (is_object($value) === true && ($value instanceof \Throwable) === false && ($value instanceof \JsonSerializable) === false) {
            $result = sprintf("[object %s]", get_class($value));
        }

        if (is_resource($value) === true) {
            $result = "[resource]";
        }

        return $result;

    }//end normalize()


    /**
     * Summarises a Throwable and its previous ones.
     *
     * @param \Throwable $exception The Throwable.
     *
     * @return array The class, message, code, file, line, trace and previous.
     */
    public function describeThrowable(\Throwable $exception): array
    {
        $description            = [];
        $description["class"]   = get_class($exception);
        $description["message"] = $exception->getMessage();
        $description["code"]    = $exception->getCode();
        $description["file"]    = $exception->getFile();
        $description["line"]    = $exception->getLine();
        $description["trace"]   = $exception->getTraceAsString();

        $previous = $exception->getPrevious();
        if ($previous !== null) {
            $description["previous"] = $this->describeThrowable($previous);
        }

        return $description;

    }//end describeThrowable()


    /**
     * Cuts a text to a length.
     *
     * @param string  $value  The text.
     * @param integer $length The length.
     *
     * @return string The text, with an ellipsis when cut.
     */
    public function truncate(string $value, int $length): string
    {
        return (new LogContext())->truncate($value, $length);

    }//end truncate()


    /**
     * Normalises every item of an array, down to MAX_DEPTH levels.
     *
     * @param array   $values The array.
     * @param integer $depth  The current depth.
     *
     * @return array|string The normalised array, or "[array]" past MAX_DEPTH.
     */
    private function normalizeArray(array $values, int $depth): array|string
    {
        if ($depth >= self::MAX_DEPTH) {
            return "[array]";
        }

        $normalized = [];
        foreach ($values as $key => $item) {
            $normalized[$key] = $this->normalize($item, ($depth + 1));
        }

        return $normalized;

    }//end normalizeArray()


    /**
     * A placeholder value as text.
     *
     * @param mixed $value The value.
     *
     * @return string The text.
     */
    private function stringify(mixed $value): string
    {
        $text = null;
        if ($value instanceof \Throwable) {
            $text = sprintf("%s: %s", get_class($value), $value->getMessage());
        }

        if (is_bool($value) === true || $value === null) {
            $text = strtolower(var_export($value, true));
        }

        if ($text === null && (is_scalar($value) === true || $value instanceof \Stringable)) {
            $text = (string) $value;
        }

        if ($text !== null) {
            return $text;
        }

        $encoded = json_encode($this->normalize($value, 0), (JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES));
        if ($encoded === false) {
            return "[unencodable]";
        }

        return $encoded;

    }//end stringify()


    /**
     * Runs preg_replace, keeping the subject when the pattern fails.
     *
     * @param string $pattern     The pattern.
     * @param string $replacement The replacement.
     * @param string $subject     The subject.
     *
     * @return string The result.
     */
    private function replace(string $pattern, string $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);
        if ($result === null) {
            return $subject;
        }

        return $result;

    }//end replace()


}//end class
