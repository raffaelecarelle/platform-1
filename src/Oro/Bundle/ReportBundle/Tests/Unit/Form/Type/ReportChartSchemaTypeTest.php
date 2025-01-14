<?php

namespace Oro\Bundle\ReportBundle\Tests\Unit\Form\Type;

use Oro\Bundle\QueryDesignerBundle\Form\Type\FieldChoiceType;
use Oro\Bundle\QueryDesignerBundle\QueryDesigner\Manager;
use Oro\Bundle\ReportBundle\Form\Type\ReportChartSchemaType;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportChartSchemaTypeTest extends FormIntegrationTestCase
{
    /** @var ReportChartSchemaType */
    private $type;

    protected function setUp(): void
    {
        $manager = $this->createMock(Manager::class);

        $this->type = new ReportChartSchemaType($manager);

        parent::setUp();
    }

    /**
     * @dataProvider dataSchemaProvider
     */
    public function testBuildForm(array $dataSchema)
    {
        $this->factory->create(ReportChartSchemaType::class, null, ['data_schema' => $dataSchema]);
    }

    /**
     * @return array
     */
    public function dataSchemaProvider()
    {
        return [
            'full' => [
                'dataSchema' => [
                    'fieldName' => [
                        'label'    => 'label',
                        'name'     => 'name',
                        'required' => true,
                        'default_type' => 'string',
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    protected function getExtensions()
    {
        $translator = $this->createMock(TranslatorInterface::class);

        $fieldChoiceType = new FieldChoiceType($translator);

        return [
            new PreloadedExtension(
                [
                    ReportChartSchemaType::class => $this->type,
                    $fieldChoiceType->getName() => $fieldChoiceType
                ],
                []
            )
        ];
    }
}
