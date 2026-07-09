<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

if (class_exists(ComponentRegistrar::class)) {
    ComponentRegistrar::register(
        ComponentRegistrar::MODULE,
        'Rgd_Inventory',
        __DIR__
    );
}
