<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Writer;

use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\BatchBundle\Entity\StepExecution;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Context\ContextRegistry;
use Oro\Bundle\ImportExportBundle\Writer\EntityDetachFixer;
use Oro\Bundle\ImportExportBundle\Writer\EntityWriter;

class EntityWriterTest extends \PHPUnit\Framework\TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject */
    protected $entityManager;

    /** @var \PHPUnit\Framework\MockObject\MockObject|DoctrineHelper */
    protected $doctrineHelper;

    /** @var \PHPUnit\Framework\MockObject\MockObject|EntityDetachFixer */
    protected $detachFixer;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ContextRegistry */
    protected $contextRegistry;

    /** @var EntityWriter */
    protected $writer;

    protected function setUp(): void
    {
        $this->entityManager = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelper = $this->getMockBuilder(DoctrineHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelper->expects($this->any())
            ->method('getEntityManager')
            ->will($this->returnValue($this->entityManager));

        $this->detachFixer = $this->getMockBuilder(EntityDetachFixer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->contextRegistry = $this->createMock(ContextRegistry::class);

        $this->writer = new EntityWriter(
            $this->doctrineHelper,
            $this->detachFixer,
            $this->contextRegistry
        );
    }

    /**
     * @param array $configuration
     *
     * @dataProvider configurationProvider
     */
    public function testWrite($configuration)
    {
        $fooItem = $this->createMock(\stdClass::class);
        $barItem = $this->createMock(\ArrayObject::class);

        $this->detachFixer->expects($this->exactly(2))
            ->method('fixEntityAssociationFields')
            ->withConsecutive([$fooItem, 1], [$barItem, 1]);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->withConsecutive([$fooItem], [$barItem]);

        $this->entityManager->expects($this->once())
            ->method('flush');

        /** @var StepExecution $stepExecution */
        $stepExecution = $this->getMockBuilder(StepExecution::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context = $this->createMock(ContextInterface::class);
        $context->expects($this->once())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $this->contextRegistry->expects($this->once())
            ->method('getByStepExecution')
            ->with($stepExecution)
            ->will($this->returnValue($context));

        if (empty($configuration[EntityWriter::SKIP_CLEAR])) {
            $this->entityManager->expects($this->once())
                ->method('clear');
        }

        $this->writer->setStepExecution($stepExecution);
        $this->writer->write([$fooItem, $barItem]);
    }

    public function testWriteException()
    {
        $item = $this->createMock(\stdClass::class);

        $this->detachFixer->expects($this->once())
            ->method('fixEntityAssociationFields')
            ->with($item, 1);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($item);

        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new \Exception());

        /** @var StepExecution $stepExecution */
        $stepExecution = $this->getMockBuilder(StepExecution::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context = $this->createMock(ContextInterface::class);
        $context->expects($this->any())
            ->method('getConfiguration')
            ->willReturn(['entityName' => \stdClass::class]);

        $this->contextRegistry->expects($this->once())
            ->method('getByStepExecution')
            ->with($stepExecution)
            ->will($this->returnValue($context));

        $this->writer->setStepExecution($stepExecution);

        $this->expectException(\Exception::class);

        $this->writer->write([$item]);
    }

    public function testWriteDatabaseExceptionDeadlock()
    {
        $exception = $this->createMock(DeadlockException::class);
        $item = $this->createMock(\stdClass::class);

        $this->detachFixer->expects($this->once())
            ->method('fixEntityAssociationFields')
            ->with($item, 1);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($item);

        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException($exception);

        /** @var StepExecution $stepExecution */
        $stepExecution = $this->getMockBuilder(StepExecution::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context = $this->createMock(ContextInterface::class);
        $context->expects($this->any())
            ->method('getConfiguration')
            ->willReturn(['entityName' => \stdClass::class]);

        $this->contextRegistry->expects($this->any())
            ->method('getByStepExecution')
            ->with($stepExecution)
            ->will($this->returnValue($context));

        $this->writer->setStepExecution($stepExecution);

        $context->expects($this->once())
            ->method('setValue')
            ->with('deadlockDetected', true);

        $this->writer->write([$item]);
    }

    public function testMissingClassName()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('entityName not resolved');

        /** @var StepExecution $stepExecution */
        $stepExecution = $this->getMockBuilder(StepExecution::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context = $this->createMock(ContextInterface::class);
        $context->expects($this->once())
            ->method('getConfiguration')
            ->will($this->returnValue([]));

        $this->contextRegistry->expects($this->once())
            ->method('getByStepExecution')
            ->with($stepExecution)
            ->will($this->returnValue($context));

        $this->writer->setStepExecution($stepExecution);
        $this->writer->write([]);
    }

    public function testClassResolvedOnce()
    {
        /** @var StepExecution $stepExecution */
        $stepExecution = $this->getMockBuilder(StepExecution::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context = $this->createMock(ContextInterface::class);
        $context->expects($this->once())
            ->method('getConfiguration')
            ->will($this->returnValue(['entityName' => \stdClass::class]));

        $this->contextRegistry->expects($this->once())
            ->method('getByStepExecution')
            ->with($stepExecution)
            ->will($this->returnValue($context));

        $this->writer->setStepExecution($stepExecution);
        $this->writer->write([]);

        // trigger detection twice
        $this->writer->write([]);
    }

    /**
     * @return array
     */
    public function configurationProvider()
    {
        return [
            'no clear flag'    => [[]],
            'clear flag false' => [[EntityWriter::SKIP_CLEAR => false]],
            'clear flag true'  => [[EntityWriter::SKIP_CLEAR => true]],
            'className from config'  => [[EntityWriter::SKIP_CLEAR => true, 'entityName' => \stdClass::class]],
        ];
    }
}
