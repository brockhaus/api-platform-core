<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Tests\Symfony\EventListener;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use PHPUnit\Framework\TestCase;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class EventPrioritiesTest extends TestCase
{
    public function testConstants()
    {
        $this->assertEquals(5, EventPriorities::PRE_READ);
        $this->assertEquals(3, EventPriorities::POST_READ);
        $this->assertEquals(3, EventPriorities::PRE_DESERIALIZE);
        $this->assertEquals(1, EventPriorities::POST_DESERIALIZE);
        $this->assertEquals(65, EventPriorities::PRE_VALIDATE);
        $this->assertEquals(63, EventPriorities::POST_VALIDATE);
        $this->assertEquals(33, EventPriorities::PRE_WRITE);
        $this->assertEquals(31, EventPriorities::POST_WRITE);
        $this->assertEquals(17, EventPriorities::PRE_SERIALIZE);
        $this->assertEquals(15, EventPriorities::POST_SERIALIZE);
        $this->assertEquals(9, EventPriorities::PRE_RESPOND);
        $this->assertEquals(0, EventPriorities::POST_RESPOND);
    }
}
