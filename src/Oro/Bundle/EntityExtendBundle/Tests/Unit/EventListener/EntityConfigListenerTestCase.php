<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\EventListener;

use Oro\Bundle\EntityConfigBundle\Audit\AuditManager;
use Oro\Bundle\EntityConfigBundle\Config\ConfigCache;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\ConfigModelManager;
use Oro\Bundle\EntityConfigBundle\Metadata\Factory\MetadataFactory;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderBag;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EntityConfigListenerTestCase extends \PHPUnit\Framework\TestCase
{
    /** @var ConfigManager */
    protected $configManager;

    /** @var ConfigProvider|\PHPUnit\Framework\MockObject\MockObject */
    protected $configProvider;

    /** @var ConfigCache|\PHPUnit\Framework\MockObject\MockObject */
    protected $configCache;

    /** @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->configCache = $this->createMock(ConfigCache::class);

        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->configProvider->expects($this->any())
            ->method('getScope')
            ->willReturn('extend');

        $configProviderBag = $this->createMock(ConfigProviderBag::class);
        $configProviderBag->expects($this->any())
            ->method('getProvider')
            ->willReturnCallback(function ($scope) {
                return 'extend' === $scope
                    ? $this->configProvider
                    : null;
            });

        $this->configManager = new ConfigManager(
            $this->eventDispatcher,
            $this->createMock(MetadataFactory::class),
            $this->createMock(ConfigModelManager::class),
            $this->createMock(AuditManager::class),
            $this->configCache
        );
        $this->configManager->setProviderBag($configProviderBag);
    }
}
