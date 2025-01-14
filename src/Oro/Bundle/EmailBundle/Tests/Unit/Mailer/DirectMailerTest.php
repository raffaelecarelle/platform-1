<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Mailer;

use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Event\SendEmailTransport;
use Oro\Bundle\EmailBundle\Exception\NotSupportedException;
use Oro\Bundle\EmailBundle\Form\Model\SmtpSettings;
use Oro\Bundle\EmailBundle\Mailer\DirectMailer;
use Oro\Bundle\EmailBundle\Provider\SmtpSettingsAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DirectMailerTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Swift_Mailer|\PHPUnit\Framework\MockObject\MockObject */
    private $baseMailer;

    /** @var ContainerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $container;

    /** @var EmailOrigin|\PHPUnit\Framework\MockObject\MockObject */
    private $emailOrigin;

    /** @var SmtpSettingsAwareInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $smtpSettingsProvider;

    /** @var DirectMailer */
    private $mailer;

    protected function setUp(): void
    {
        $this->baseMailer = $this->createMock(\Swift_Mailer::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->emailOrigin = $this->createMock(EmailOrigin::class);
        $this->smtpSettingsProvider = $this->createMock(SmtpSettingsAwareInterface::class);

        $this->mailer = new DirectMailer($this->baseMailer, $this->container);
    }

    public function testSendNonSpooled()
    {
        $message = new \Swift_Message();
        $failedRecipients = [];
        $transport = $this->createMock(\Swift_Transport::class);

        $this->baseMailer->expects($this->once())
            ->method('getTransport')
            ->willReturn($transport);
        $this->container->expects($this->never())
            ->method('getParameter');

        $this->smtpSettingsProvider->expects($this->any())
            ->method('getSmtpSettings')
            ->willReturn(new SmtpSettings());

        $this->container->expects($this->any())
            ->method('get')
            ->willReturn($this->smtpSettingsProvider);

        $transport->expects($this->exactly(2))
            ->method('isStarted')
            ->willReturnOnConsecutiveCalls(false, true);
        $transport->expects($this->once())
            ->method('start');
        $transport->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($message), $this->identicalTo($failedRecipients))
            ->willReturn(1);
        $transport->expects($this->once())
            ->method('stop');

        $this->assertEquals(1, $this->mailer->send($message, $failedRecipients));
    }

    public function testSendSpooled()
    {
        $message = new \Swift_Message();
        $failedRecipients = [];
        $transport = $this->createMock(\Swift_Transport_SpoolTransport::class);
        $realTransport = $this->createMock(\Swift_Transport::class);

        $this->baseMailer->expects($this->once())
            ->method('getTransport')
            ->willReturn($transport);

        $this->container->expects($this->once())
            ->method('getParameter')
            ->with('swiftmailer.mailers')
            ->willReturn(['test1' => null, 'test2' => null]);

        $this->smtpSettingsProvider->expects($this->any())
            ->method('getSmtpSettings')
            ->willReturn(new SmtpSettings());

        $this->container->expects($this->any())
            ->method('get')
            ->willReturnMap([
                [
                    'swiftmailer.mailer.test1',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    $this->createMock(\Swift_Mailer::class)
                ],
                [
                    'swiftmailer.mailer.test2',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    $this->baseMailer
                ],
                [
                    'swiftmailer.mailer.test2.transport.real',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    $realTransport
                ],
                [
                    'oro_email.provider.smtp_settings',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    $this->smtpSettingsProvider
                ]
            ]);

        $realTransport->expects($this->exactly(2))
            ->method('isStarted')
            ->willReturnOnConsecutiveCalls(false, true);
        $realTransport->expects($this->once())
            ->method('start');
        $realTransport->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($message), $this->identicalTo($failedRecipients))
            ->willReturn(1);
        $realTransport->expects($this->once())
            ->method('stop');

        $this->container->expects($this->any())
            ->method('initialized')
            ->willReturnMap([
                ['swiftmailer.mailer.test1', true],
                ['swiftmailer.mailer.test2', true]
            ]);

        $this->assertEquals(1, $this->mailer->send($message, $failedRecipients));
    }

    public function testSendSpooledWithSmtpSettings()
    {
        $message = new \Swift_Message();
        $failedRecipients = [];

        $this->baseMailer->expects($this->once())
            ->method('getTransport')
            ->willReturn($this->createMock(\Swift_Transport_SpoolTransport::class));

        $this->container->expects($this->once())
            ->method('getParameter')
            ->with('swiftmailer.mailers')
            ->willReturn(['test1' => null, 'test2' => null]);

        $this->smtpSettingsProvider->expects($this->any())
            ->method('getSmtpSettings')
            ->willReturn(new SmtpSettings('127.0.0.1', 1025, ''));

        $realTransport = $this->createMock(\Swift_Transport_EsmtpTransport::class);
        $realTransport->expects($this->once())
            ->method('isStarted')
            ->willReturn(false);

        $realTransport->expects($this->once())
            ->method('setHost')
            ->with('127.0.0.1')
            ->willReturnSelf();
        $realTransport->expects($this->once())
            ->method('setPort')
            ->with(1025)
            ->willReturnSelf();
        $realTransport->expects($this->once())
            ->method('setEncryption')
            ->with('')
            ->willReturnSelf();
        // to process auth settings Swift will use AuthHandler which will called by __call() method
        $realTransport->expects($this->exactly(2))
            ->method('__call')
            ->withConsecutive(
                ['setUsername', ['']],
                ['setPassword', ['']]
            )
            ->willReturnSelf();

        $realTransport->expects($this->once())
            ->method('start');
        $realTransport->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($message), $this->identicalTo($failedRecipients))
            ->willReturn(1);
        $realTransport->expects($this->once())
            ->method('stop');

        $this->container->expects($this->any())
            ->method('get')
            ->willReturnMap([
                [
                    'swiftmailer.mailer.test1',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    $this->createMock(\Swift_Mailer::class)
                ],
                [
                    'swiftmailer.mailer.test2',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    $this->baseMailer
                ],
                [
                    'swiftmailer.mailer.test2.transport.real',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    $realTransport
                ],
                [
                    'oro_email.provider.smtp_settings',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    $this->smtpSettingsProvider
                ]
            ]);

        $this->container->expects($this->any())
            ->method('initialized')
            ->willReturnMap([
                ['swiftmailer.mailer.test1', true],
                ['swiftmailer.mailer.test2', true]
            ]);

        $this->assertEquals(1, $this->mailer->send($message, $failedRecipients));
    }

    public function testSendWithException()
    {
        $this->expectException(\Exception::class);
        $message = new \Swift_Message();
        $failedRecipients = [];
        $transport = $this->createMock(\Swift_Transport::class);

        $this->baseMailer->expects($this->once())
            ->method('getTransport')
            ->willReturn($transport);
        $this->container->expects($this->never())
            ->method('getParameter');

        $this->smtpSettingsProvider->expects($this->any())
            ->method('getSmtpSettings')
            ->willReturn(new SmtpSettings());

        $this->container->expects($this->any())
            ->method('get')
            ->willReturn($this->smtpSettingsProvider);

        $transport->expects($this->exactly(2))
            ->method('isStarted')
            ->willReturnOnConsecutiveCalls(false, true);
        $transport->expects($this->once())
            ->method('start');
        $transport->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($message), $this->identicalTo($failedRecipients))
            ->willThrowException(new \Exception('test failure'));
        $transport->expects($this->once())
            ->method('stop');

        $this->assertEquals(1, $this->mailer->send($message, $failedRecipients));
    }

    public function testCreateSmtpTransport()
    {
        $transport = $this->createMock(\Swift_Transport::class);
        $smtpTransport = $this->createMock(\Swift_Transport_EsmtpTransport::class);

        $event = $this->createMock(SendEmailTransport::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturn($event);
        $event->expects($this->once())
            ->method('getTransport')
            ->willReturn($smtpTransport);

        $this->baseMailer->expects($this->once())
            ->method('getTransport')
            ->willReturn($transport);

        $this->smtpSettingsProvider->expects($this->any())
            ->method('getSmtpSettings')
            ->willReturn(new SmtpSettings());

        $this->container->expects($this->any())
            ->method('get')
            ->willReturnMap([
                ['event_dispatcher', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $dispatcher],
                [
                    'oro_email.provider.smtp_settings',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    $this->smtpSettingsProvider
                ]
            ]);

        $this->mailer->prepareEmailOriginSmtpTransport($this->emailOrigin);
        $smtpTransport = $this->mailer->getTransport();

        $this->assertInstanceOf(\Swift_Transport_EsmtpTransport::class, $smtpTransport);
    }

    public function testRegisterPlugin()
    {
        $this->expectException(NotSupportedException::class);
        $plugin = $this->createMock(\Swift_Events_EventListener::class);
        $this->mailer->registerPlugin($plugin);
    }

    /**
     * Check that SMTP transport initialized correctly and not overridden
     */
    public function testAfterPrepareSmtpTransportForEsmtpTransport()
    {
        $smtpTransport = $this->getMockBuilder(\Swift_Transport_EsmtpTransport::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setHost', 'setPort', 'setEncryption'])
            ->addMethods(['setUsername', 'setPassword'])
            ->getMock();
        $streamOptions = ['ssl' => ['verify_peer' => false]];
        $smtpTransport->setStreamOptions($streamOptions);

        $this->baseMailer->expects($this->once())
            ->method('getTransport')
            ->willReturn($smtpTransport);

        $this->smtpSettingsProvider->expects($this->any())
            ->method('getSmtpSettings')
            ->willReturn(new SmtpSettings());

        $this->container->expects($this->any())
            ->method('get')
            ->with('oro_email.provider.smtp_settings', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
            ->willReturn($this->smtpSettingsProvider);

        $settings = new SmtpSettings();
        $settings->setHost('test.host');
        $settings->setPort(442);
        $settings->setUsername('username');
        $settings->setPassword('pass');
        $settings->setEncryption('tls');

        $smtpTransport->expects($this->once())
            ->method('setHost')
            ->with($settings->getHost())
            ->willReturnSelf();
        $smtpTransport->expects($this->once())
            ->method('setPort')
            ->with($settings->getPort())
            ->willReturnSelf();
        $smtpTransport->expects($this->once())
            ->method('setEncryption')
            ->with($settings->getEncryption())
            ->willReturnSelf();
        $smtpTransport->expects($this->once())
            ->method('setUsername')
            ->with($settings->getUsername())
            ->willReturnSelf();
        $smtpTransport->expects($this->once())
            ->method('setPassword')
            ->with($settings->getPassword())
            ->willReturnSelf();

        $this->mailer->afterPrepareSmtpTransport($settings);

        $this->assertSame($streamOptions, $smtpTransport->getStreamOptions());
    }

    /**
     * Check that SMTP transport initialized correctly new instance created
     */
    public function testAfterPrepareSmtpTransportForNonEsmtpTransport()
    {
        $smtpTransport = $this->createMock(\Swift_Transport_AbstractSmtpTransport::class);

        $this->baseMailer->expects(self::once())
            ->method('getTransport')
            ->willReturn($smtpTransport);

        $this->smtpSettingsProvider->expects(self::any())
            ->method('getSmtpSettings')
            ->willReturn(new SmtpSettings());

        $this->container->expects(self::any())
            ->method('get')
            ->with('oro_email.provider.smtp_settings', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
            ->willReturn($this->smtpSettingsProvider);

        $settings = new SmtpSettings();
        $settings->setHost('test.host');
        $settings->setPort(442);
        $settings->setUsername('username');
        $settings->setPassword('pass');
        $settings->setEncryption('tls');

        $smtpTransport->expects(self::never())
            ->method(self::anything());

        $this->mailer->afterPrepareSmtpTransport($settings);
        $transport = $this->mailer->getTransport();
        self::assertInstanceOf(\Swift_SmtpTransport::class, $this->mailer->getTransport());

        self::assertEquals($settings->getHost(), $transport->getHost());
        self::assertEquals($settings->getPort(), $transport->getPort());
        self::assertEquals($settings->getEncryption(), $transport->getEncryption());
        self::assertEquals($settings->getUsername(), $transport->getUsername());
        self::assertEquals($settings->getPassword(), $transport->getPassword());
    }
}
