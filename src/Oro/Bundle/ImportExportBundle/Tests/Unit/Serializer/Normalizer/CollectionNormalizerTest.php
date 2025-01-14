<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Serializer\Normalizer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Oro\Bundle\ImportExportBundle\Serializer\Normalizer\CollectionNormalizer;
use Oro\Bundle\ImportExportBundle\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class CollectionNormalizerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $serializer;

    /**
     * @var CollectionNormalizer
     */
    protected $normalizer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(Serializer::class);
        $this->normalizer = new CollectionNormalizer();
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testSetInvalidSerializer()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Serializer must implement');

        $this->normalizer->setSerializer($this->createMock(SerializerInterface::class));
    }

    public function testSupportsNormalization()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));

        $collection = $this->createMock(Collection::class);
        $this->assertTrue($this->normalizer->supportsNormalization($collection));
    }

    /**
     * @dataProvider supportsDenormalizationDataProvider
     */
    public function testSupportsDenormalization($type, $expectedResult)
    {
        $this->assertEquals($expectedResult, $this->normalizer->supportsDenormalization(array(), $type));
    }

    public function supportsDenormalizationDataProvider()
    {
        return array(
            array('stdClass', false),
            array('ArrayCollection', true),
            array(ArrayCollection::class, true),
            array('Doctrine\Common\Collections\ArrayCollection<Foo>', true),
            array('Doctrine\Common\Collections\ArrayCollection<Foo\Bar\Baz>', true),
            array('ArrayCollection<ArrayCollection<Foo\Bar\Baz>>', true),
        );
    }

    public function testNormalize()
    {
        $format = null;
        $context = array('context');

        $firstElement = $this->createMock(\stdClass::class);
        $secondElement = $this->createMock(\ArrayObject::class);
        $data = new ArrayCollection(array($firstElement, $secondElement));

        $this->serializer->expects($this->exactly(2))
            ->method('normalize')
            ->will(
                $this->returnValueMap(
                    array(
                        array($firstElement, $format, $context, 'first'),
                        array($secondElement, $format, $context, 'second'),
                    )
                )
            );

        $this->assertEquals(
            array('first', 'second'),
            $this->normalizer->normalize($data, $format, $context)
        );
    }

    public function testDenormalizeNotArray()
    {
        $this->serializer->expects($this->never())->method($this->anything());
        $this->assertEquals(
            new ArrayCollection(),
            $this->normalizer->denormalize('string', '')
        );
    }

    public function testDenormalizeSimple()
    {
        $this->serializer->expects($this->never())->method($this->anything());
        $data = array('foo', 'bar');
        $this->assertEquals(
            new ArrayCollection($data),
            $this->normalizer->denormalize($data, 'ArrayCollection', null)
        );
    }

    public function testDenormalizeWithItemType()
    {
        $format = null;
        $context = array();

        $fooEntity = new \stdClass();
        $barEntity = new \stdClass();

        $this->serializer->expects($this->exactly(2))
            ->method('denormalize')
            ->will(
                $this->returnValueMap(
                    array(
                        array('foo', 'ItemType', $format, $context, $fooEntity),
                        array('bar', 'ItemType', $format, $context, $barEntity),
                    )
                )
            );

        $this->assertEquals(
            new ArrayCollection(array($fooEntity, $barEntity)),
            $this->normalizer->denormalize(
                array('foo', 'bar'),
                'ArrayCollection<ItemType>',
                $format,
                $context
            )
        );
    }
}
