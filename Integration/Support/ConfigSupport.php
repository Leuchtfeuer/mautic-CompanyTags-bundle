<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Integration\LeuchtfeuerCompanyTagsIntegration;

class ConfigSupport extends LeuchtfeuerCompanyTagsIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}
