<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\ShieldsIo;

/**
 * Renders the webhooks API's GET /workflow-runs rows as the Workflow Runs
 * Management table: header row first, one row per run, with the same
 * shields.io badge vocabulary the dashboard's pre-rendered Workflow Runs
 * table uses (run number + workflow name badge coloured from the name,
 * emoji-iconed event / status / conclusion badges, GitHub-logo repository
 * and actor badges) plus the workflow file path and the Retry / Delete
 * action buttons. Pure formatting, no HTTP, so it is unit-testable.
 */
class WorkflowRunsPresenter
{

    public const TABLE_HEADER = [
        'Repository',
        'Workflow',
        'Path',
        'Event',
        'Status',
        'Conclusion',
        'Actor',
        'Updated At',
        'Actions',
    ];

    /**
     * Conclusions GitHub lets a run be re-run from (rerun / rerun-failed-jobs).
     */
    public const RETRYABLE_CONCLUSIONS = [
        'failure',
        'timed_out',
        'cancelled',
        'action_required',
    ];

    private const NAME_MAX_LENGTH = 50;

    private const DEFAULT_ICON = '⭕';

    /**
     * Badge icons by status / conclusion / event, matching the webhooks
     * dashboard's icon map so both tables read the same.
     */
    private const ICONS = [
        'requested'           => '⏳',
        'pending'             => '⏳',
        'waiting'             => '⏳',
        'queued'              => '⏰',
        'in_progress'         => '🔄',
        'completed'           => '✅',
        'success'             => '✅',
        'failure'             => '❌',
        'cancelled'           => '🚫',
        'timed_out'           => '⏱️',
        'action_required'     => '⚠️',
        'startup_failure'     => '💥',
        'neutral'             => '⚪',
        'stale'               => '🥀',
        'skipped'             => '⏭️',
        'none'                => '⭕',
        'push'                => '📤',
        'pull_request'        => '🔀',
        'pull_request_target' => '🔀',
        'merge_group'         => '🔀',
        'issues'              => '🐛',
        'issue_comment'       => '💬',
        'release'             => '🚀',
        'schedule'            => '📅',
        'workflow_dispatch'   => '🚥',
        'repository_dispatch' => '📢',
        'dynamic'             => '🔂',
        'create'              => '➕',
        'delete'              => '❌',
    ];

    private const STATUS_COLORS = [
        'queued'      => 'red',
        'requested'   => 'yellow',
        'pending'     => 'yellow',
        'waiting'     => 'yellow',
        'in_progress' => 'green',
        'completed'   => 'green',
    ];

    private const CONCLUSION_COLORS = [
        'success'         => 'green',
        'failure'         => 'red',
        'cancelled'       => 'red',
        'timed_out'       => 'red',
        'action_required' => 'red',
        'startup_failure' => 'red',
        'none'            => 'yellow',
        'skipped'         => 'gray',
        'neutral'         => 'gray',
        'stale'           => 'gray',
    ];


    /**
     * One row per run (header row first), ready for drawDataTable.
     *
     * @param array $runs Rows as returned by GET /workflow-runs.
     *
     * @return array
     */
    public function toTable(array $runs): array
    {
        $shields = new ShieldsIo();
        $rows    = [self::TABLE_HEADER];

        foreach ($runs as $run) {
            if (is_array($run) === false) {
                continue;
            }

            $rows[] = $this->buildRow($shields, $run);
        }

        return $rows;

    }//end toTable()


    /**
     * One run's table row.
     *
     * @param ShieldsIo $shields Badge URL builder.
     * @param array     $run     One run as returned by the webhooks API.
     *
     * @return array
     */
    protected function buildRow(ShieldsIo $shields, array $run): array
    {
        $runUrl     = $this->runUrl($run);
        $status     = (string) ($run['status'] ?? '');
        $event      = (string) ($run['event'] ?? '');
        $updatedAt  = (string) ($run['updated_at'] ?? '');
        $conclusion = $this->conclusion($run);

        return [
            $this->repositoryCell($shields, $run),
            $this->workflowCell($shields, $run, $runUrl),
            $this->pathCell($run),
            $this->linkBadge($shields, $runUrl, $event, $this->icon($event), $event, 'blue', 'white'),
            $this->linkBadge($shields, $runUrl, $status, $this->icon($status), $status, $this->statusColor($status), 'white'),
            $this->linkBadge($shields, $runUrl, $conclusion, $this->icon($conclusion), $conclusion, $this->conclusionColor($conclusion), 'white'),
            $this->actorCell($shields, $run),
            htmlspecialchars($updatedAt, ENT_QUOTES),
            $this->actionsCell($run),
        ];

    }//end buildRow()


    /**
     * The repository's GitHub URL.
     *
     * @param array $run One run as returned by the webhooks API.
     *
     * @return string
     */
    protected function repositoryUrl(array $run): string
    {
        $owner = rawurlencode((string) ($run['owner'] ?? ''));
        $repo  = rawurlencode((string) ($run['repo'] ?? ''));

        return 'https://github.com/'.$owner.'/'.$repo;

    }//end repositoryUrl()


    /**
     * The run's page under the repository's Actions tab.
     *
     * @param array $run One run as returned by the webhooks API.
     *
     * @return string
     */
    protected function runUrl(array $run): string
    {
        $workflowRunId = (int) ($run['workflow_run_id'] ?? 0);

        return $this->repositoryUrl($run).'/actions/runs/'.$workflowRunId;

    }//end runUrl()


    /**
     * The run's conclusion, or "none" while it has none yet.
     *
     * @param array $run One run as returned by the webhooks API.
     *
     * @return string
     */
    protected function conclusion(array $run): string
    {
        $conclusion = (string) ($run['conclusion'] ?? '');
        if ($conclusion === '') {
            return 'none';
        }

        return $conclusion;

    }//end conclusion()


    /**
     * Repository as a flat GitHub-logo badge linking to the repository, with
     * a lock marker when the repository is private.
     *
     * @param ShieldsIo $shields Badge URL builder.
     * @param array     $run     One run as returned by the webhooks API.
     *
     * @return string
     */
    protected function repositoryCell(ShieldsIo $shields, array $run): string
    {
        $repository = ($run['owner'] ?? '').'/'.($run['repo'] ?? '');
        $badge      = $this->linkBadge(
            $shields,
            $this->repositoryUrl($run),
            $repository,
            $repository,
            null,
            'black',
            null,
            'flat',
            'github'
        );

        $private = filter_var(($run['private'] ?? false), FILTER_VALIDATE_BOOLEAN);
        if ($private === false) {
            return $badge;
        }

        return $badge.' <i class="bi bi-lock-fill text-warning" title="Private repository"></i>';

    }//end repositoryCell()


    /**
     * "♯00123 | Workflow name (attempt)" badge coloured from the repository
     * and workflow name, exactly like the dashboard's Workflow Runs table,
     * linking to the run; the run's display title is the tooltip.
     *
     * @param ShieldsIo $shields Badge URL builder.
     * @param array     $run     One run as returned by the webhooks API.
     * @param string    $runUrl  The run's page on GitHub.
     *
     * @return string
     */
    protected function workflowCell(ShieldsIo $shields, array $run, string $runUrl): string
    {
        $name       = (string) ($run['name'] ?? '');
        $repository = ($run['owner'] ?? '').'/'.($run['repo'] ?? '');
        $runNumber  = str_pad((string) ((int) ($run['run_number'] ?? 0)), 5, '0', STR_PAD_LEFT);
        $attempt    = (int) ($run['run_attempt'] ?? 1);
        $title      = (string) ($run['display_title'] ?? '');
        if ($title === '') {
            $title = $name;
        }

        $shortName = $name;
        if (strlen($name) > self::NAME_MAX_LENGTH) {
            $shortName = substr($name, 0, self::NAME_MAX_LENGTH).'...';
        }

        $color   = new Color();
        $hex     = $color->generateColorFromText($repository.$name);
        $label   = '♯'.$runNumber;
        $content = $shortName.' ('.$attempt.')';

        return $this->linkBadge($shields, $runUrl, $title, $label, $content, $hex, 'black');

    }//end workflowCell()


    /**
     * The workflow file path, linking to that workflow's page under the
     * repository's Actions tab (GitHub addresses workflows by file name).
     *
     * @param array $run One run as returned by the webhooks API.
     *
     * @return string
     */
    protected function pathCell(array $run): string
    {
        $path = (string) ($run['path'] ?? '');
        if ($path === '') {
            return '—';
        }

        $url      = $this->repositoryUrl($run).'/actions/workflows/'.rawurlencode(basename($path));
        $safePath = htmlspecialchars($path, ENT_QUOTES);

        return '<a href="'.$url.'" title="'.$safePath.'" target="_blank" rel="noopener noreferrer">'.$safePath.'</a>';

    }//end pathCell()


    /**
     * Actor as a social-style GitHub badge linking to the profile.
     *
     * @param ShieldsIo $shields Badge URL builder.
     * @param array     $run     One run as returned by the webhooks API.
     *
     * @return string
     */
    protected function actorCell(ShieldsIo $shields, array $run): string
    {
        $actor = (string) ($run['actor_login'] ?? '');
        if ($actor === '') {
            return '—';
        }

        $url = 'https://github.com/'.rawurlencode($actor);

        return $this->linkBadge($shields, $url, $actor, $actor, null, 'black', null, 'social', 'github');

    }//end actorCell()


    /**
     * Retry (enabled for retryable conclusions only) and Delete buttons,
     * picked up by the dashboard's click delegation via data-action.
     *
     * @param array $run One run as returned by the webhooks API.
     *
     * @return string
     */
    protected function actionsCell(array $run): string
    {
        $workflowRunId = (int) ($run['workflow_run_id'] ?? 0);
        $conclusion    = $this->conclusion($run);
        $retryDisabled = ' disabled';
        if (in_array($conclusion, self::RETRYABLE_CONCLUSIONS, true) === true) {
            $retryDisabled = '';
        }

        $retryBtn  = '<button class="btn btn-warning btn-sm" data-action="retry-workflow-run"';
        $retryBtn .= ' data-workflow-run-id="'.$workflowRunId.'" title="Retry workflow run"';
        $retryBtn .= ' aria-label="Retry workflow run '.$workflowRunId.'"'.$retryDisabled.'>';
        $retryBtn .= '<i class="bi bi-arrow-clockwise"></i></button>';

        $deleteBtn  = '<button class="btn btn-danger btn-sm" data-action="delete-workflow-run"';
        $deleteBtn .= ' data-workflow-run-id="'.$workflowRunId.'" title="Delete workflow run row"';
        $deleteBtn .= ' aria-label="Delete workflow run '.$workflowRunId.'">';
        $deleteBtn .= '<i class="bi bi-trash2"></i></button>';

        return $retryBtn.' '.$deleteBtn;

    }//end actionsCell()


    /**
     * Badge icon for a status, conclusion or event; a generic one when unknown.
     *
     * @param string $key Status, conclusion or event name.
     *
     * @return string
     */
    protected function icon(string $key): string
    {
        return (self::ICONS[$key] ?? self::DEFAULT_ICON);

    }//end icon()


    /**
     * Badge colour for a run status (yellow when unknown).
     *
     * @param string $status Run status.
     *
     * @return string
     */
    protected function statusColor(string $status): string
    {
        return (self::STATUS_COLORS[$status] ?? 'yellow');

    }//end statusColor()


    /**
     * Badge colour for a run conclusion (red when unknown).
     *
     * @param string $conclusion Run conclusion.
     *
     * @return string
     */
    protected function conclusionColor(string $conclusion): string
    {
        return (self::CONCLUSION_COLORS[$conclusion] ?? 'red');

    }//end conclusionColor()


    /**
     * A shields.io badge wrapped in a link, the same shape the webhooks
     * dashboard emits. The badge text is encoded by ShieldsIo; the alt and
     * title attributes are escaped here. The link sits in an inline-block
     * span reserving the badge's width, because the table sizes its columns
     * before the badge images arrive and would otherwise leave the badges
     * spilling into the next column.
     *
     * @param ShieldsIo   $shields    Badge URL builder.
     * @param string      $url        Link target.
     * @param string      $title      Link tooltip.
     * @param string|null $label      Badge label (left side).
     * @param string|null $content    Badge content (right side).
     * @param string      $color      Badge colour.
     * @param string|null $labelColor Badge label colour.
     * @param string      $style      The shields.io style.
     * @param string|null $logo       The shields.io logo name.
     *
     * @return string
     */
    protected function linkBadge(
        ShieldsIo $shields,
        string $url,
        string $title,
        ?string $label,
        ?string $content,
        string $color,
        ?string $labelColor,
        string $style='for-the-badge',
        ?string $logo=null
    ): string {
        $badgeUrl  = $shields->generateBadgeUrl($label, $content, $color, $style, $labelColor, $logo);
        $badgeUrl  = htmlspecialchars($badgeUrl, ENT_QUOTES);
        $alt       = htmlspecialchars((string) ($content ?? $label ?? ''), ENT_QUOTES);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES);

        $width = $this->badgeWidth($style, $label, $content);

        $html  = '<span class="badge-cell" style="min-width: '.$width.'px">';
        $html .= '<a title="'.$safeTitle.'" href="'.$url.'" target="_blank" rel="noopener noreferrer">';
        $html .= '<img src="'.$badgeUrl.'" alt="'.$alt.'" /></a></span>';

        return $html;

    }//end linkBadge()


    /**
     * Estimated rendered width of a shields.io badge, in pixels, from its
     * text length: about 9.2px per character for the uppercase,
     * letter-spaced for-the-badge style (plus the room an emoji icon label
     * takes) and 6.4px per character for the flat and social styles plus
     * their padding and GitHub logo. Calibrated against rendered badges; a
     * few pixels over is harmless, under makes the badge spill, so the
     * estimate leans slightly wide.
     *
     * @param string      $style   The shields.io style.
     * @param string|null $label   Badge label (left side).
     * @param string|null $content Badge content (right side).
     *
     * @return integer
     */
    protected function badgeWidth(string $style, ?string $label, ?string $content): int
    {
        $labelLength = mb_strlen((string) $label);
        $characters  = ($labelLength + mb_strlen((string) $content));
        if ($style === 'for-the-badge') {
            $icon = 0;
            if ($labelLength === 1) {
                $icon = 20;
            }

            return (int) ceil(((9.2 * $characters) + 30 + $icon));
        }

        if ($style === 'social') {
            return (int) ceil(((6.4 * $characters) + 36));
        }

        return (int) ceil(((6.4 * $characters) + 32));

    }//end badgeWidth()


}//end class
