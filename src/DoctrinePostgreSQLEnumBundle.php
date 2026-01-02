<?php

declare(strict_types=1);

namespace Weglot\DoctrinePostgresEnum;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Weglot\DoctrinePostgresEnum\DependencyInjection\DoctrinePostgreSQLEnumExtension;

final class DoctrinePostgreSQLEnumBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new DoctrinePostgreSQLEnumExtension();
    }
}
