<?php

declare(strict_types=1);

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;

// 1. Define the project root for cleaner paths
$projectDir = dirname(__DIR__);

require_once $projectDir . '/vendor/autoload.php';

// 2. Initialize Container
$container = new ContainerBuilder();

// 3. Inject useful default parameters
// This allows you to use %kernel.project_dir% in your YAML files
$container->setParameter('kernel.project_dir', $projectDir);
$container->setParameter('kernel.logs_dir', $projectDir . '/var/log');

// 4. Register Monolog Extension
$container->registerExtension(new MonologExtension());

// 5. Load Configuration
$loader = new YamlFileLoader($container, new FileLocator($projectDir . '/config'));
$loader->load('config.yaml');

// 6. Compile and Return
$container->setAlias('Psr\Log\LoggerInterface', 'monolog.logger');
$container->compile();

return $container;