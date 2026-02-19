<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Cli;

use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\CodeGenerator\Cli\AbstractMakeCommandHandler;
use PhpSoftBox\CodeGenerator\CodeGenerator;
use PhpSoftBox\CodeGenerator\GeneratorTarget;

final class MakeNotificationHandler extends AbstractMakeCommandHandler
{
    protected function missingNameMessage(): string
    {
        return 'Имя уведомления не задано.';
    }

    protected function successMessage(GeneratorTarget $target): string
    {
        return 'Создано уведомление: ' . $target->path;
    }

    protected function renderEvent(RunnerInterface $runner, GeneratorTarget $target): string
    {
        $generator = new CodeGenerator();

        $bodyLines = [
            'public function via(NotifiableInterface $notifiable): array',
            '{',
            '    return [',
            '        // NotificationChannelNames::EMAIL->value,',
            '        // NotificationChannelNames::TELEGRAM->value,',
            '        // NotificationChannelNames::DATABASE->value,',
            '    ];',
            '}',
        ];

        return $generator->renderClass(
            className: $target->className,
            namespace: $target->namespace,
            uses: [
                'PhpSoftBox\\Notifications\\Notification',
                'PhpSoftBox\\Notifications\\NotifiableInterface',
                'PhpSoftBox\\Notifications\\NotificationChannelNames',
            ],
            bodyLines: $generator->indent($bodyLines, 0),
            final: true,
        );
    }
}
