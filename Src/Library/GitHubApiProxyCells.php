<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\ShieldsIo;

/**
 * HTML cells of the github-api-proxy tables: caller labels, the client
 * (user agent) cell and shields.io badges. Every value is HTML-escaped.
 * Used by GitHubApiProxyPresenter.
 */
class GitHubApiProxyCells
{

    /**
     * Longest user agent shown in the Client column.
     */
    private const USER_AGENT_LENGTH = 48;

    /**
     * Badge URL generator.
     *
     * @var ShieldsIo
     */
    private $shields;


    /**
     * @param ShieldsIo|null $shields Badge URL generator; defaults to a new one.
     */
    public function __construct(?ShieldsIo $shields=null)
    {
        $this->shields = ($shields ?? new ShieldsIo());

    }//end __construct()


    /**
     * Caller cell. The proxy labels callers as a GitHub login,
     * installation:<account>, app:<id>, token:<fingerprint> (still being
     * resolved), invalid:<fingerprint> or anonymous.
     *
     * @param string $caller Caller label.
     * @param array  $kinds  Token kinds used by the caller.
     *
     * @return string
     */
    public function caller(string $caller, array $kinds): string
    {
        $parts  = explode(':', $caller, 2);
        $prefix = $parts[0];
        $value  = ($parts[1] ?? '');
        $muted  = static fn(string $text): string => ' <small class="text-white-50">'.$text.'</small>';

        return match ($prefix) {
            'installation' => $this->githubLink($value).$muted('app installation'),
            'app'          => 'GitHub App #'.$this->escape($value).$this->kindsNote($kinds),
            'token'        => 'token <code>'.$this->escape($value).'</code>'.$muted('resolving'),
            'invalid'      => 'invalid token <code>'.$this->escape($value).'</code>',
            'anonymous'    => 'anonymous',
            ''             => '—',
            default        => $this->githubLink($caller).$this->kindsNote($kinds),
        };

    }//end caller()


    /**
     * Client cell: the first user agent (shortened) and how many more were
     * seen, with every user agent and client IP in the tooltip.
     *
     * @param array $userAgents User agents seen.
     * @param array $clientIps  Client IPs seen.
     *
     * @return string
     */
    public function client(array $userAgents, array $clientIps): string
    {
        $userAgents = array_values(array_filter(array_map('strval', $userAgents), fn(string $agent) => $agent !== ''));
        if (empty($userAgents) === true) {
            return '—';
        }

        $first = $userAgents[0];
        if (mb_strlen($first) > self::USER_AGENT_LENGTH) {
            $first = mb_substr($first, 0, (self::USER_AGENT_LENGTH - 1)).'…';
        }

        $more = '';
        if (count($userAgents) > 1) {
            $more = ' <small class="text-white-50">+'.(count($userAgents) - 1).'</small>';
        }

        $title = implode("\n", array_merge($userAgents, array_map('strval', $clientIps)));

        return '<span title="'.$this->escape($title).'">'.$this->escape($first).'</span>'.$more;

    }//end client()


    /**
     * Errors badge: red when there were errors, green otherwise.
     *
     * @param integer $errors Requests answered 4xx/5xx.
     *
     * @return string
     */
    public function errorsBadge(int $errors): string
    {
        $color = 'green';
        if ($errors > 0) {
            $color = 'red';
        }

        return $this->badge('errors', number_format($errors), $color, $errors.' requests answered 4xx/5xx');

    }//end errorsBadge()


    /**
     * Used-quota badge, coloured at 50%, 80% and 95%.
     *
     * @param float   $percent Used percentage.
     * @param integer $limit   Requests allowed per window.
     *
     * @return string
     */
    public function usageBadge(float $percent, int $limit): string
    {
        $color = 'green';
        if ($percent >= 95) {
            $color = 'red';
        } elseif ($percent >= 80) {
            $color = 'orange';
        } elseif ($percent >= 50) {
            $color = 'yellow';
        }

        $text = number_format($percent, 1).'%';

        return $this->badge('used', $text, $color, $text.' of the '.$limit.' requests per window used');

    }//end usageBadge()


    /**
     * HTML-escapes a value.
     *
     * @param string $value Raw text.
     *
     * @return string
     */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

    }//end escape()


    /**
     * Small grey note listing the token kinds, if any.
     *
     * @param array $kinds Token kinds.
     *
     * @return string
     */
    private function kindsNote(array $kinds): string
    {
        $kinds = array_values(array_filter(array_map('strval', $kinds), fn(string $kind) => $kind !== '' && $kind !== 'none'));
        if (empty($kinds) === true) {
            return '';
        }

        return ' <small class="text-white-50">'.$this->escape(implode(', ', $kinds)).'</small>';

    }//end kindsNote()


    /**
     * Link to a GitHub account.
     *
     * @param string $login Account login.
     *
     * @return string
     */
    private function githubLink(string $login): string
    {
        $url = $this->escape('https://github.com/'.rawurlencode($login));

        return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$this->escape($login).'</a>';

    }//end githubLink()


    /**
     * Renders one shields.io badge as an <img> tag.
     *
     * @param string $label   Left-hand label.
     * @param string $content Right-hand content.
     * @param string $color   Content colour.
     * @param string $alt     Alt/title text.
     *
     * @return string
     */
    private function badge(string $label, string $content, string $color, string $alt): string
    {
        $badgeUrl = $this->shields->generateBadgeUrl($label, $content, $color, 'for-the-badge', 'black', null);
        $safeAlt  = $this->escape($alt);

        return '<img alt="'.$safeAlt.'" title="'.$safeAlt.'" src="'.$this->escape((string) $badgeUrl).'" />';

    }//end badge()


}//end class
