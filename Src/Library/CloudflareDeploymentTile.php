<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\ShieldsIo;

/**
 * The base of the Cloudflare deployment tiles (Pages and Workers): a table
 * with one badge per site linked to its URL, the state of its latest
 * deployment, the branch and the date; a note when there is nothing to list;
 * and the reason in place of the rows when the API cannot be read.
 *
 * A state is shown as is (BUILD_FAILURE, SUCCESS...). Its status, the text
 * after the last underscore, picks the badge icon and colour.
 */
abstract class CloudflareDeploymentTile extends CloudflareApiClient
{

    protected const NO_DATA = "-";

    protected const STATE_NOT_DEPLOYED = "NOT_DEPLOYED";

    private const MESSAGE_ERROR = "⚠️ Could not read %s: %s";


    /**
     * The name of the tile in messages, e.g. "Cloudflare Pages".
     *
     * @return string
     */
    abstract protected function getLabel(): string;


    /**
     * The title of the first column, e.g. "Project".
     *
     * @return string
     */
    abstract protected function getFirstColumn(): string;


    /**
     * The rows of the tile, header included.
     *
     * @return array
     *
     * @throws RequestException When the API cannot be read.
     */
    abstract protected function getRows(): array;


    /**
     * The rows of the tile, or, when the API cannot be read, the header and
     * one row with the reason. The failure is reported, as an uncaught one
     * would be, so it still reaches the messages table and LogStream.
     *
     * @param ErrorReporter $reporter The reporter of the failure.
     *
     * @return array
     */
    public function getRowsOrError(ErrorReporter $reporter): array
    {
        try {
            return $this->getRows();
        } catch (RequestException $exception) {
            $reason = (new ErrorText())->summarize($exception->getMessage());

            $context              = [];
            $context["reason"]    = $reason;
            $context["exception"] = $exception;
            $reporter->error($this->getLabel()." could not be read: {reason}", $context, "api");

            $message = sprintf(self::MESSAGE_ERROR, $this->getLabel(), htmlspecialchars($reason, ENT_QUOTES));

            $rows   = [];
            $rows[] = $this->getHeader();
            $rows[] = $this->getMessageRow($message);

            return $rows;
        }

    }//end getRowsOrError()


    /**
     * The header row of the tile.
     *
     * @return array
     */
    protected function getHeader(): array
    {
        $header   = [];
        $header[] = $this->getFirstColumn();
        $header[] = "Status";
        $header[] = "Branch";
        $header[] = "Last Deployment";

        return $header;

    }//end getHeader()


    /**
     * A row carrying a message in its first column.
     *
     * @param string $message The message.
     *
     * @return array
     */
    protected function getMessageRow(string $message): array
    {
        $row   = [];
        $row[] = $message;
        $row[] = self::NO_DATA;
        $row[] = self::NO_DATA;
        $row[] = self::NO_DATA;

        return $row;

    }//end getMessageRow()


    /**
     * The row of a site: its badge, linked to its URL, its state, the
     * branch deployed and the date of the deployment.
     *
     * @param string      $name    The site name.
     * @param string      $link    The site URL.
     * @param string      $state   The state of its latest deployment.
     * @param string|null $branch  The branch deployed, if known.
     * @param string|null $date    The date of the deployment, if known.
     * @param ShieldsIo   $shields The badge builder.
     *
     * @return array
     */
    protected function getBadgeRow(string $name, string $link, string $state, ?string $branch, ?string $date, ShieldsIo $shields): array
    {
        $alt   = htmlspecialchars($name, ENT_QUOTES);
        $href  = htmlspecialchars($link, ENT_QUOTES);
        $title = htmlspecialchars($state, ENT_QUOTES);

        $badgeUrl  = $shields->generateBadgeUrl($this->mapStatus($state), $name, $this->mapColor($state), "for-the-badge", "white", null);
        $image     = "<img src='{$badgeUrl}' alt='{$alt}' />";
        $badgeLink = "<a href='{$href}' title='{$title}' target='_blank' rel='noopener noreferrer'>{$image}</a>";

        $row   = [];
        $row[] = $badgeLink;
        $row[] = $title;
        $row[] = htmlspecialchars(($branch ?? self::NO_DATA), ENT_QUOTES);
        $row[] = $this->formatDate($date);

        return $row;

    }//end getBadgeRow()


    /**
     * The status part of a state: the text after the last underscore.
     *
     * @param string $state The state shown for a site.
     *
     * @return string
     */
    protected function getStatus(string $state): string
    {
        $position = strrpos($state, "_");
        if ($position === false || $state === self::STATE_NOT_DEPLOYED) {
            return $state;
        }

        return substr($state, ($position + 1));

    }//end getStatus()


    /**
     * The icon of a state.
     *
     * @param string $state The state shown for a site.
     *
     * @return string
     */
    private function mapStatus(string $state): string
    {
        return match ($this->getStatus($state)) {
            "SUCCESS", "DEPLOYED" => "✅",
            "FAILURE" => "❌",
            "ACTIVE", "RUNNING" => "🔨",
            "IDLE", "QUEUED", "INITIALIZING" => "⏳",
            "CANCELED", "CANCELLED", "TERMINATED" => "⏸",
            "SKIPPED" => "⏭",
            self::STATE_NOT_DEPLOYED => "💤",
            default => "❓",
        };

    }//end mapStatus()


    /**
     * The badge colour of a state.
     *
     * @param string $state The state shown for a site.
     *
     * @return string
     */
    private function mapColor(string $state): string
    {
        return match ($this->getStatus($state)) {
            "SUCCESS", "DEPLOYED" => "green",
            "FAILURE" => "red",
            "ACTIVE", "RUNNING" => "yellow",
            "IDLE", "QUEUED", "INITIALIZING" => "blue",
            "CANCELED", "CANCELLED", "TERMINATED", "SKIPPED", self::STATE_NOT_DEPLOYED => "gray",
            default => "lightgray",
        };

    }//end mapColor()


    /**
     * A date of the API as shown in the tile.
     *
     * @param string|null $date The date, ISO 8601.
     *
     * @return string
     */
    protected function formatDate(?string $date): string
    {
        if (empty($date) === true) {
            return self::NO_DATA;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return self::NO_DATA;
        }

        return date("H:i:s d/m/Y", $timestamp);

    }//end formatDate()


}//end class
