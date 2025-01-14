<?php

namespace Oro\Bundle\ImapBundle\Tests\Unit\Validator\Constraints;

use Oro\Bundle\ImapBundle\Validator\Constraints\ImapConnectionConfiguration;
use Oro\Bundle\ImapBundle\Validator\ImapConnectionConfigurationValidator;
use Symfony\Component\Validator\Constraint;

class ImapConnectionConfigurationTest extends \PHPUnit\Framework\TestCase
{
    public function testGetTargets()
    {
        self::assertSame(
            Constraint::CLASS_CONSTRAINT,
            (new ImapConnectionConfiguration())->getTargets()
        );
    }

    public function testValidatedBy()
    {
        self::assertSame(
            ImapConnectionConfigurationValidator::class,
            (new ImapConnectionConfiguration())->validatedBy()
        );
    }
}
