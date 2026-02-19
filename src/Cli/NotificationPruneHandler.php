<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Cli;

use DateInterval;
use DateTimeImmutable;
use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Notifications\Database\DatabaseNotificationRepository;

use function is_int;
use function max;

final readonly class NotificationPruneHandler implements HandlerInterface
{
    public function __construct(
        private DatabaseNotificationRepository
    $repository,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $days = $runner->request()->option('days', 30);
        $days = is_int($days) ? $days : (int) $days;
        $days = max(0, $days);

        $onlyRead = (bool) $runner->request()->option('read', false);

        $before = null;
        if ($days > 0) {
            $before = new DateTimeImmutable()->sub(new DateInterval('P' . $days . 'D'));
        }

        $deleted = $this->repository->prune($before, $onlyRead);

        $runner->io()->writeln('Удалено уведомлений: ' . $deleted, 'success');

        return Response::SUCCESS;
    }
}
