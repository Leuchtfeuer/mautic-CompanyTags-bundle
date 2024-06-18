<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [
    ];

    $services->load('MauticPlugin\\LeuchtfeuerCompanyTagsBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');
    $services->load('MauticPlugin\\LeuchtfeuerCompanyTagsBundle\\Entity\\', '../Entity/*Repository.php');
    $services->alias('mautic.companytag.model.companytag', MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel::class);
};
