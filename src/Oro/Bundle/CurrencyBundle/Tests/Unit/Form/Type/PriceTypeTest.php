<?php

namespace Oro\Bundle\CurrencyBundle\Tests\Unit\Form\Type;

use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\CurrencyBundle\Form\Type\CurrencySelectionType;
use Oro\Bundle\CurrencyBundle\Form\Type\PriceType;
use Oro\Bundle\CurrencyBundle\Provider\CurrencyProviderInterface;
use Oro\Bundle\CurrencyBundle\Utils\CurrencyNameHelper;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Component\Testing\Unit\FormIntegrationTestCase;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PriceTypeTest extends FormIntegrationTestCase
{
    /**
     * {@inheritDoc}
     */
    protected function getExtensions()
    {
        $currencyProvider = $this->createMock(CurrencyProviderInterface::class);
        $currencyProvider->expects($this->any())
            ->method('getCurrencyList')
            ->willReturn(['USD', 'EUR']);

        $localeSettings = $this->createMock(LocaleSettings::class);
        $currencyNameHelper = $this->createMock(CurrencyNameHelper::class);

        $priceType = new PriceType();
        $priceType->setDataClass(Price::class);

        return [
            new PreloadedExtension(
                [
                    PriceType::class => $priceType,
                    CurrencySelectionType::class => new CurrencySelectionType(
                        $currencyProvider,
                        $localeSettings,
                        $currencyNameHelper
                    )
                ],
                []
            ),
            $this->getValidatorExtension(true),
        ];
    }

    public function testValueWhenDefaultEnglishLocale()
    {
        $form = $this->factory->create(PriceType::class, (new Price())->setValue(1234567.89));
        $view = $form->createView();

        self::assertEquals('1,234,567.8900', $view->children['value']->vars['value']);

        $form->submit(['value' => '2,432,765.9800', 'currency' => 'USD']);

        self::assertEquals(2432765.98, $form->getData()->getValue());
    }

    public function testValueWhenGermanLocale()
    {
        $previousLocale = \Locale::getDefault();
        try {
            \Locale::setDefault('de_DE');
            $form = $this->factory->create(PriceType::class, (new Price())->setValue(1234567.89));
            $view = $form->createView();

            self::assertEquals('1.234.567,8900', $view->children['value']->vars['value']);

            $form->submit(['value' => '2.432.765,9800', 'currency' => 'USD']);

            self::assertEquals(2432765.98, $form->getData()->getValue());
        } finally {
            \Locale::setDefault($previousLocale);
        }
    }

    /**
     * @dataProvider submitProvider
     */
    public function testSubmit(
        bool $isValid,
        mixed $defaultData,
        array $submittedData,
        mixed $expectedData,
        array $options = []
    ) {
        $form = $this->factory->create(PriceType::class, $defaultData, $options);

        $this->assertEquals($defaultData, $form->getData());
        $form->submit($submittedData);
        $this->assertEquals($isValid, $form->isValid());
        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expectedData, $form->getData());
    }

    public function submitProvider(): array
    {
        return [
            'price without value' => [
                'isValid'       => true,
                'defaultData'   => new Price(),
                'submittedData' => [],
                'expectedData'  => null
            ],
            'not numeric value' => [
                'isValid'       => false,
                'defaultData'   => new Price(),
                'submittedData' => [
                    'value' => 'test-value',
                    'currency' => 'USD'
                ],
                'expectedData'  => null,
            ],
            'value < 0' => [
                'isValid'       => false,
                'defaultData'   => new Price(),
                'submittedData' => [
                    'value' => -1,
                    'currency' => 'USD'
                ],
                'expectedData'  => (new Price())->setValue(-1)->setCurrency('USD')
            ],
            'price without currency' => [
                'isValid'       => false,
                'defaultData'   => new Price(),
                'submittedData' => [
                    'value' => 100
                ],
                'expectedData'  => (new Price())->setValue(100)
            ],
            'invalid currency' => [
                'isValid'       => false,
                'defaultData'   => new Price(),
                'submittedData' => [
                    'value' => 100,
                    'currency' => 'UAH'
                ],
                'expectedData'  => (new Price())->setValue(100)
            ],
            'price with value' => [
                'isValid'       => true,
                'defaultData'   => new Price(),
                'submittedData' => [
                    'value' => 100,
                    'currency' => 'USD'
                ],
                'expectedData'  => (new Price())->setValue(100)->setCurrency('USD')
            ],
            'price with precision' => [
                'isValid'       => true,
                'defaultData'   => new Price(),
                'submittedData' => [
                    'value' => 100.1234,
                    'currency' => 'USD'
                ],
                'expectedData'  => (new Price())->setValue(100.1234)->setCurrency('USD')
            ],
            'hidden price' => [
                'isValid'       => true,
                'defaultData'   => new Price(),
                'submittedData' => [
                    'value' => 100,
                    'currency' => 'EUR'
                ],
                'expectedData'  => (new Price())->setValue(100)->setCurrency('EUR'),
                [
                    'hide_currency' => true,
                    'default_currency' => 'USD'
                ]
            ]
        ];
    }

    public function testGetName()
    {
        $formType = $this->factory->create(PriceType::class);
        $this->assertEquals(PriceType::NAME, $formType->getName());
    }

    public function testConfigureOptions()
    {
        $optionsResolverMock = $this->createMock(OptionsResolver::class);

        $form = new PriceType();
        $form->setDataClass(\stdClass::class);

        $optionsResolverMock->expects($this->once())
            ->method('setDefaults')
            ->with(
                [
                'data_class' => \stdClass::class,
                'hide_currency' => false,
                'additional_currencies' => null,
                'currencies_list' => null,
                'default_currency' => null,
                'full_currency_list' => false,
                'currency_empty_value' => 'oro.currency.currency.form.choose',
                'compact' => false,
                'validation_groups'=> ['Default'],
                'match_price_on_null' => true
                ]
            );

        $form->configureOptions($optionsResolverMock);
    }
}
