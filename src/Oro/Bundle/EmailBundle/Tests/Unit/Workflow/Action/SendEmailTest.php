<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Workflow\Action;

use Oro\Bundle\EmailBundle\Entity\Email as EmailEntity;
use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Oro\Bundle\EmailBundle\Form\Model\Email;
use Oro\Bundle\EmailBundle\Mailer\Processor;
use Oro\Bundle\EmailBundle\Tests\Unit\Fixtures\Entity\TestEmailOrigin;
use Oro\Bundle\EmailBundle\Tools\EmailAddressHelper;
use Oro\Bundle\EmailBundle\Tools\EmailOriginHelper;
use Oro\Bundle\EmailBundle\Workflow\Action\SendEmail;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\LocaleBundle\Model\FirstNameInterface;
use Oro\Component\Action\Exception\InvalidParameterException;
use Oro\Component\ConfigExpression\ContextAccessor;
use Oro\Component\Testing\ReflectionUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SendEmailTest extends \PHPUnit\Framework\TestCase
{
    /** @var ContextAccessor|\PHPUnit\Framework\MockObject\MockObject */
    private $contextAccessor;

    /** @var Processor|\PHPUnit\Framework\MockObject\MockObject */
    private $emailProcessor;

    /** @var EntityNameResolver|\PHPUnit\Framework\MockObject\MockObject */
    private $entityNameResolver;

    /** @var EmailOriginHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $emailOriginHelper;

    /** @var EventDispatcher|\PHPUnit\Framework\MockObject\MockObject */
    private $dispatcher;

    /** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    /** @var SendEmail */
    private $action;

    protected function setUp(): void
    {
        $this->contextAccessor = $this->createMock(ContextAccessor::class);
        $this->emailProcessor = $this->createMock(Processor::class);
        $this->entityNameResolver = $this->createMock(EntityNameResolver::class);
        $this->emailOriginHelper = $this->createMock(EmailOriginHelper::class);
        $this->dispatcher = $this->createMock(EventDispatcher::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = new SendEmail(
            $this->contextAccessor,
            $this->emailProcessor,
            new EmailAddressHelper(),
            $this->entityNameResolver,
            $this->emailOriginHelper
        );
        $this->action->setDispatcher($this->dispatcher);
        $this->action->setLogger($this->logger);
    }

    /**
     * @dataProvider initializeExceptionDataProvider
     */
    public function testInitializeException(array $options, string $exceptionName, string $exceptionMessage)
    {
        $this->expectException($exceptionName);
        $this->expectExceptionMessage($exceptionMessage);
        $this->action->initialize($options);
    }

    public function initializeExceptionDataProvider(): array
    {
        return [
            'no from' => [
                'options' => ['to' => 'test@test.com', 'subject' => 'test', 'body' => 'test'],
                'exceptionName' => InvalidParameterException::class,
                'exceptionMessage' => 'From parameter is required'
            ],
            'no from email' => [
                'options' => [
                    'to' => 'test@test.com', 'subject' => 'test', 'body' => 'test',
                    'from' => ['name' => 'Test']
                ],
                'exceptionName' => InvalidParameterException::class,
                'exceptionMessage' => 'Email parameter is required'
            ],
            'no to' => [
                'options' => ['from' => 'test@test.com', 'subject' => 'test', 'body' => 'test'],
                'exceptionName' => InvalidParameterException::class,
                'exceptionMessage' => 'To parameter is required'
            ],
            'no to email' => [
                'options' => [
                    'from' => 'test@test.com', 'subject' => 'test', 'body' => 'test',
                    'to' => ['name' => 'Test']
                ],
                'exceptionName' => InvalidParameterException::class,
                'exceptionMessage' => 'Email parameter is required'
            ],
            'no to email in one of addresses' => [
                'options' => [
                    'from' => 'test@test.com', 'subject' => 'test', 'body' => 'test',
                    'to' => ['test@test.com', ['name' => 'Test']]
                ],
                'exceptionName' => InvalidParameterException::class,
                'exceptionMessage' => 'Email parameter is required'
            ],
            'no subject' => [
                'options' => ['from' => 'test@test.com', 'to' => 'test@test.com', 'body' => 'test'],
                'exceptionName' => InvalidParameterException::class,
                'exceptionMessage' => 'Subject parameter is required'
            ],
            'no body' => [
                'options' => ['from' => 'test@test.com', 'to' => 'test@test.com', 'subject' => 'test'],
                'exceptionName' => InvalidParameterException::class,
                'exceptionMessage' => 'Body parameter is required'
            ],
        ];
    }

    /**
     * @dataProvider optionsDataProvider
     */
    public function testInitialize(array $options, array $expected)
    {
        self::assertSame($this->action, $this->action->initialize($options));
        self::assertEquals($expected, ReflectionUtil::getPropertyValue($this->action, 'options'));
    }

    public function optionsDataProvider(): array
    {
        return [
            'simple' => [
                [
                    'from' => 'test@test.com',
                    'to' => 'test@test.com',
                    'subject' => 'test',
                    'body' => 'test'
                ],
                [
                    'from' => 'test@test.com',
                    'to' => ['test@test.com'],
                    'subject' => 'test',
                    'body' => 'test'
                ]
            ],
            'simple with name' => [
                [
                    'from' => 'Test <test@test.com>',
                    'to' => 'Test <test@test.com>',
                    'subject' => 'test',
                    'body' => 'test'
                ],
                [
                    'from' => 'Test <test@test.com>',
                    'to' => ['Test <test@test.com>'],
                    'subject' => 'test',
                    'body' => 'test'
                ]
            ],
            'extended' => [
                [
                    'from' => [
                        'name' => 'Test',
                        'email' => 'test@test.com'
                    ],
                    'to' => [
                        'name' => 'Test',
                        'email' => 'test@test.com'
                    ],
                    'subject' => 'test',
                    'body' => 'test'
                ],
                [
                    'from' => [
                        'name' => 'Test',
                        'email' => 'test@test.com'
                    ],
                    'to' => [
                        [
                            'name' => 'Test',
                            'email' => 'test@test.com'
                        ]
                    ],
                    'subject' => 'test',
                    'body' => 'test'
                ]
            ],
            'multiple to' => [
                [
                    'from' => [
                        'name' => 'Test',
                        'email' => 'test@test.com'
                    ],
                    'to' => [
                        [
                            'name' => 'Test',
                            'email' => 'test@test.com'
                        ],
                        'test@test.com',
                        'Test <test@test.com>'
                    ],
                    'subject' => 'test',
                    'body' => 'test'
                ],
                [
                    'from' => [
                        'name' => 'Test',
                        'email' => 'test@test.com'
                    ],
                    'to' => [
                        [
                            'name' => 'Test',
                            'email' => 'test@test.com'
                        ],
                        'test@test.com',
                        'Test <test@test.com>'
                    ],
                    'subject' => 'test',
                    'body' => 'test'
                ]
            ]
        ];
    }

    /**
     * @dataProvider executeOptionsDataProvider
     */
    public function testExecute(array $options, array $expected)
    {
        $context = [];
        $this->contextAccessor->expects(self::any())
            ->method('getValue')
            ->willReturnArgument(1);
        $this->entityNameResolver->expects(self::any())
            ->method('getName')
            ->willReturnCallback(function () {
                return '_Formatted';
            });

        $emailEntity = new EmailEntity();
        $emailUserEntity = $this->createMock(EmailUser::class);
        $emailUserEntity->expects(self::any())
            ->method('getEmail')
            ->willReturn($emailEntity);

        $emailOrigin = new TestEmailOrigin();
        $this->emailOriginHelper->expects(self::once())
            ->method('getEmailOrigin')
            ->with($expected['from'], null)
            ->willReturn($emailOrigin);

        $this->emailProcessor->expects(self::once())
            ->method('process')
            ->with(self::isInstanceOf(Email::class), $emailOrigin)
            ->willReturnCallback(function (Email $model) use ($emailUserEntity, $expected) {
                self::assertEquals($expected['body'], $model->getBody());
                self::assertEquals($expected['subject'], $model->getSubject());
                self::assertEquals($expected['from'], $model->getFrom());
                self::assertEquals($expected['to'], $model->getTo());

                return $emailUserEntity;
            });
        if (array_key_exists('attribute', $options)) {
            $this->contextAccessor->expects(self::once())
                ->method('setValue')
                ->with($context, $options['attribute'], $emailEntity);
        }
        $this->action->initialize($options);
        $this->action->execute($context);
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function executeOptionsDataProvider(): array
    {
        $nameMock = $this->createMock(FirstNameInterface::class);
        $nameMock->expects(self::any())
            ->method('getFirstName')
            ->willReturn('NAME');

        return [
            'simple' => [
                [
                    'from' => 'test@test.com',
                    'to' => 'test@test.com',
                    'subject' => 'test',
                    'body' => 'test'
                ],
                [
                    'from' => 'test@test.com',
                    'to' => ['test@test.com'],
                    'subject' => 'test',
                    'body' => 'test'
                ]
            ],
            'simple with name' => [
                [
                    'from' => '"Test" <test@test.com>',
                    'to' => '"Test" <test@test.com>',
                    'subject' => 'test',
                    'body' => 'test'
                ],
                [
                    'from' => '"Test" <test@test.com>',
                    'to' => ['"Test" <test@test.com>'],
                    'subject' => 'test',
                    'body' => 'test'
                ]
            ],
            'extended' => [
                [
                    'from' => [
                        'name' => 'Test',
                        'email' => 'test@test.com'
                    ],
                    'to' => [
                        'name' => 'Test',
                        'email' => 'test@test.com'
                    ],
                    'subject' => 'test',
                    'body' => 'test'
                ],
                [
                    'from' => '"Test" <test@test.com>',
                    'to' => ['"Test" <test@test.com>'],
                    'subject' => 'test',
                    'body' => 'test'
                ]
            ],
            'extended with name formatting' => [
                [
                    'from' => [
                        'name' => $nameMock,
                        'email' => 'test@test.com'
                    ],
                    'to' => [
                        'name' => $nameMock,
                        'email' => 'test@test.com'
                    ],
                    'subject' => 'test',
                    'body' => 'test'
                ],
                [
                    'from' => '"_Formatted" <test@test.com>',
                    'to' => ['"_Formatted" <test@test.com>'],
                    'subject' => 'test',
                    'body' => 'test'
                ]
            ],
            'multiple to' => [
                [
                    'from' => [
                        'name' => 'Test',
                        'email' => 'test@test.com'
                    ],
                    'to' => [
                        [
                            'name' => 'Test',
                            'email' => 'test@test.com'
                        ],
                        'test@test.com',
                        '"Test" <test@test.com>'
                    ],
                    'subject' => 'test',
                    'body' => 'test',
                    'attribute' => 'attr'
                ],
                [
                    'from' => '"Test" <test@test.com>',
                    'to' => [
                        '"Test" <test@test.com>',
                        'test@test.com',
                        '"Test" <test@test.com>'
                    ],
                    'subject' => 'test',
                    'body' => 'test'
                ]
            ]
        ];
    }

    public function testExecuteWithProcessException()
    {
        $options = [
            'from' => 'test@test.com',
            'to' => 'test@test.com',
            'template' => 'test',
            'subject' => 'subject',
            'body' => 'body',
            'entity' => new \stdClass(),
        ];

        $context = [];
        $this->contextAccessor->expects(self::any())
            ->method('getValue')
            ->willReturnArgument(1);
        $this->entityNameResolver->expects(self::any())
            ->method('getName')
            ->willReturnCallback(function () {
                return '_Formatted';
            });

        $emailUserEntity = $this->getMockBuilder(EmailUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEmail'])
            ->getMock();
        $emailEntity = $this->createMock(EmailEntity::class);
        $emailUserEntity->expects(self::any())
            ->method('getEmail')
            ->willReturn($emailEntity);

        $this->emailProcessor->expects(self::once())
            ->method('process')
            ->with(self::isInstanceOf(Email::class))
            ->willThrowException(new \Swift_SwiftException('The email was not delivered.'));

        $this->logger->expects(self::once())
            ->method('error')
            ->with('Workflow send email action.');

        $this->action->initialize($options);
        $this->action->execute($context);
    }
}
