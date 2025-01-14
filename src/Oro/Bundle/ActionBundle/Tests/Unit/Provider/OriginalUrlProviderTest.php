<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Provider;

use Oro\Bundle\ActionBundle\Button\ButtonSearchContext;
use Oro\Bundle\ActionBundle\Provider\OriginalUrlProvider;
use Oro\Bundle\DataGridBundle\Converter\UrlConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class OriginalUrlProviderTest extends \PHPUnit\Framework\TestCase
{
    private OriginalUrlProvider $urlProvider;

    private RouterInterface|\PHPUnit\Framework\MockObject\MockObject $router;

    private RequestStack|\PHPUnit\Framework\MockObject\MockObject $requestStack;

    private UrlConverter|\PHPUnit\Framework\MockObject\MockObject $datagridUrlConverter;

    /** {@inheritdoc} */
    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->datagridUrlConverter = $this->createMock(UrlConverter::class);
        $this->urlProvider = new OriginalUrlProvider(
            $this->requestStack,
            $this->router,
            $this->datagridUrlConverter
        );
    }

    public function testGetOriginalUrl(): void
    {
        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($this->getRequest('example.com'));

        self::assertEquals('example.com', $this->urlProvider->getOriginalUrl());
    }

    public function testGetOriginalUrlReturnNullIfRequestIsNotDefined(): void
    {
        self::assertNull($this->urlProvider->getOriginalUrl());
    }

    public function testGetOriginalUrlWhenDatagridIsSet(): void
    {
        $datagridName = 'quotes-grid';
        $pageParams = [
            'quotes-grid' =>
                [
                    'originalRoute' => 'oro_sale_quote_index',
                    '_pager' =>
                        [
                            '_page' => '1',
                            '_per_page' => '10',
                        ],
                    '_parameters' =>
                        [
                            'view' => '__all__',
                        ],
                    '_appearance' =>
                        [
                            '_type' => 'grid',
                        ],
                ],
            'appearanceType' => 'grid',
        ];

        $requestUri = '/admin/datagrid/quotes-grid?' . \http_build_query($pageParams);
        $responseUri = '/admin/sale/quote?' . \http_build_query([$datagridName => $pageParams[$datagridName]]);

        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($this->getRequest($requestUri));

        $this->datagridUrlConverter
            ->expects(self::once())
            ->method('convertGridUrlToPageUrl')
            ->with($datagridName, $requestUri)
            ->willReturn($responseUri);

        $buttonContext = $this->getSearchButtonContext($datagridName);

        self::assertEquals(
            $responseUri,
            $this->urlProvider->getOriginalUrl($buttonContext)
        );
    }

    /**
     * @param string $requestUri
     * @return Request
     */
    private function getRequest(string $requestUri): Request
    {
        return new Request([], [], [], [], [], ['REQUEST_URI' => $requestUri]);
    }

    /**
     * @param string|null $datagridName
     * @return ButtonSearchContext
     */
    private function getSearchButtonContext($datagridName): ButtonSearchContext
    {
        $btnContext = new ButtonSearchContext();
        $btnContext->setDatagrid($datagridName);

        return $btnContext;
    }
}
