<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Cli;

use PhpSoftBox\CliApp\Command\ArgumentDefinition;
use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\CliApp\Command\CommandRegistryInterface;
use PhpSoftBox\CliApp\Command\OptionDefinition;
use PhpSoftBox\CliApp\Loader\CommandProviderInterface;

final class NotificationCommandProvider implements CommandProviderInterface
{
    public function register(CommandRegistryInterface $registry): void
    {
        $registry->register(Command::define(
            name: 'make:notification',
            description: 'Создать класс уведомления',
            signature: [
                new ArgumentDefinition(
                    name: 'name',
                    description: 'Namespace или путь (например, App\\Notifications\\WelcomeNotification)',
                    required: true,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'path',
                    short: 'p',
                    description: 'Базовая директория для namespace (по умолчанию src)',
                    required: false,
                    default: 'src',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'namespace',
                    short: 'n',
                    description: 'Базовый namespace (по умолчанию App)',
                    required: false,
                    default: 'App',
                    type: 'string',
                ),
            ],
            handler: MakeNotificationHandler::class,
        ));

        $registry->register(Command::define(
            name: 'notifications:prune',
            description: 'Очистить уведомления из базы данных',
            signature: [
                new OptionDefinition(
                    name: 'days',
                    short: 'd',
                    description: 'Удалить записи старше N дней',
                    required: false,
                    default: 30,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'read',
                    short: 'r',
                    description: 'Удалять только прочитанные уведомления',
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: NotificationPruneHandler::class,
        ));
    }
}
