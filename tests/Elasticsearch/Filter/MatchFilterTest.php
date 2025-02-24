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

namespace ApiPlatform\Tests\Elasticsearch\Filter;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Elasticsearch\Filter\ConstantScoreFilterInterface;
use ApiPlatform\Elasticsearch\Filter\MatchFilter;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Property\PropertyNameCollection;
use ApiPlatform\Tests\Fixtures\TestBundle\Entity\Foo;
use ApiPlatform\Tests\ProphecyTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class MatchFilterTest extends TestCase
{
    use ProphecyTrait;

    public function testConstruct(): void
    {
        self::assertInstanceOf(
            ConstantScoreFilterInterface::class,
            new MatchFilter(
                $this->prophesize(PropertyNameCollectionFactoryInterface::class)->reveal(),
                $this->prophesize(PropertyMetadataFactoryInterface::class)->reveal(),
                $this->prophesize(ResourceClassResolverInterface::class)->reveal(),
                $this->prophesize(IriConverterInterface::class)->reveal(),
                $this->prophesize(PropertyAccessorInterface::class)->reveal(),
                $this->prophesize(NameConverterInterface::class)->reveal()
            )
        );
    }

    public function testApply(): void
    {
        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactoryProphecy->create(Foo::class)->willReturn(new PropertyNameCollection(['id', 'name', 'bar']))->shouldBeCalled();

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactoryProphecy->create(Foo::class, 'id')->willReturn((new ApiProperty())->withBuiltinTypes([new Type(Type::BUILTIN_TYPE_INT)]))->shouldBeCalled();
        $propertyMetadataFactoryProphecy->create(Foo::class, 'name')->willReturn((new ApiProperty())->withBuiltinTypes([new Type(Type::BUILTIN_TYPE_STRING)]))->shouldBeCalled();

        $foo = new Foo();
        $foo->setName('Xavier');
        $foo->setBar('Thévenard');

        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);
        $iriConverterProphecy->getResourceFromIri('/foos/1', ['fetch_data' => false])->willReturn($foo)->shouldBeCalled();

        $propertyAccessorProphecy = $this->prophesize(PropertyAccessorInterface::class);
        $propertyAccessorProphecy->getValue($foo, 'id')->willReturn(1)->shouldBeCalled();

        $nameConverterProphecy = $this->prophesize(NameConverterInterface::class);
        $nameConverterProphecy->normalize('id', Foo::class, null, Argument::type('array'))->willReturn('id')->shouldBeCalled();
        $nameConverterProphecy->normalize('name', Foo::class, null, Argument::type('array'))->willReturn('name')->shouldBeCalled();

        $matchFilter = new MatchFilter(
            $propertyNameCollectionFactoryProphecy->reveal(),
            $propertyMetadataFactoryProphecy->reveal(),
            $this->prophesize(ResourceClassResolverInterface::class)->reveal(),
            $iriConverterProphecy->reveal(),
            $propertyAccessorProphecy->reveal(),
            $nameConverterProphecy->reveal()
        );

        self::assertSame(
            ['bool' => ['must' => [['match' => ['id' => 1]], ['bool' => ['should' => [['match' => ['name' => 'Caroline']], ['match' => ['name' => 'Xavier']]]]]]]],
            $matchFilter->apply([], Foo::class, null, ['filters' => ['id' => '/foos/1', 'name' => ['Caroline', 'Xavier']]])
        );
    }

    public function testApplyWithNestedProperty(): void
    {
        $fooType = new Type(Type::BUILTIN_TYPE_ARRAY, false, Foo::class, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, Foo::class));
        $barType = new Type(Type::BUILTIN_TYPE_STRING);

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactoryProphecy->create(Foo::class, 'foo')->willReturn((new ApiProperty())->withBuiltinTypes([$fooType]))->shouldBeCalled();
        $propertyMetadataFactoryProphecy->create(Foo::class, 'bar')->willReturn((new ApiProperty())->withBuiltinTypes([$barType]))->shouldBeCalled();

        $resourceClassResolverProphecy = $this->prophesize(ResourceClassResolverInterface::class);
        $resourceClassResolverProphecy->isResourceClass(Foo::class)->willReturn(true)->shouldBeCalled();

        $nameConverterProphecy = $this->prophesize(NameConverterInterface::class);
        $nameConverterProphecy->normalize('foo.bar', Foo::class, null, Argument::type('array'))->willReturn('foo.bar')->shouldBeCalled();
        $nameConverterProphecy->normalize('foo', Foo::class, null, Argument::type('array'))->willReturn('foo')->shouldBeCalled();

        $matchFilter = new MatchFilter(
            $this->prophesize(PropertyNameCollectionFactoryInterface::class)->reveal(),
            $propertyMetadataFactoryProphecy->reveal(),
            $resourceClassResolverProphecy->reveal(),
            $this->prophesize(IriConverterInterface::class)->reveal(),
            $this->prophesize(PropertyAccessorInterface::class)->reveal(),
            $nameConverterProphecy->reveal(),
            ['foo.bar' => null]
        );

        self::assertSame(
            ['bool' => ['must' => [['nested' => ['path' => 'foo', 'query' => ['match' => ['foo.bar' => 'Krupicka']]]]]]],
            $matchFilter->apply([], Foo::class, null, ['filters' => ['foo.bar' => 'Krupicka']])
        );
    }

    public function testApplyWithInvalidFilters(): void
    {
        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactoryProphecy->create(Foo::class)->willReturn(new PropertyNameCollection(['id', 'bar']))->shouldBeCalled();

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactoryProphecy->create(Foo::class, 'id')->willReturn((new ApiProperty())->withBuiltinTypes([new Type(Type::BUILTIN_TYPE_INT)]))->shouldBeCalled();
        $propertyMetadataFactoryProphecy->create(Foo::class, 'bar')->willReturn(new ApiProperty())->shouldBeCalled();

        $iriConverterProphecy = $this->prophesize(IriConverterInterface::class);
        $iriConverterProphecy->getResourceFromIri('/invalid_iri_foos/1', ['fetch_data' => false])->willThrow(new InvalidArgumentException())->shouldBeCalled();

        $matchFilter = new MatchFilter(
            $propertyNameCollectionFactoryProphecy->reveal(),
            $propertyMetadataFactoryProphecy->reveal(),
            $this->prophesize(ResourceClassResolverInterface::class)->reveal(),
            $iriConverterProphecy->reveal(),
            $this->prophesize(PropertyAccessorInterface::class)->reveal(),
            $this->prophesize(NameConverterInterface::class)->reveal()
        );

        self::assertSame(
            [],
            $matchFilter->apply([], Foo::class, null, ['filters' => ['id' => '/invalid_iri_foos/1', 'bar' => 'Chaverot']])
        );
    }

    public function testGetDescription(): void
    {
        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactoryProphecy->create(Foo::class)->willReturn(new PropertyNameCollection(['id', 'name', 'bar', 'date', 'weird']))->shouldBeCalled();

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactoryProphecy->create(Foo::class, 'id')->willReturn((new ApiProperty())->withBuiltinTypes([new Type(Type::BUILTIN_TYPE_INT)]))->shouldBeCalled();
        $propertyMetadataFactoryProphecy->create(Foo::class, 'name')->willReturn((new ApiProperty())->withBuiltinTypes([new Type(Type::BUILTIN_TYPE_STRING)]))->shouldBeCalled();
        $propertyMetadataFactoryProphecy->create(Foo::class, 'bar')->willReturn(new ApiProperty())->shouldBeCalled();
        $propertyMetadataFactoryProphecy->create(Foo::class, 'date')->willReturn((new ApiProperty())->withBuiltinTypes([new Type(Type::BUILTIN_TYPE_OBJECT, false, \DateTimeImmutable::class)]))->shouldBeCalled();
        $propertyMetadataFactoryProphecy->create(Foo::class, 'weird')->willReturn((new ApiProperty())->withBuiltinTypes([new Type(Type::BUILTIN_TYPE_RESOURCE)]))->shouldBeCalled();

        $resourceClassResolverProphecy = $this->prophesize(ResourceClassResolverInterface::class);
        $resourceClassResolverProphecy->isResourceClass(\DateTimeImmutable::class)->willReturn(false)->shouldBeCalled();

        $matchFilter = new MatchFilter(
            $propertyNameCollectionFactoryProphecy->reveal(),
            $propertyMetadataFactoryProphecy->reveal(),
            $resourceClassResolverProphecy->reveal(),
            $this->prophesize(IriConverterInterface::class)->reveal(),
            $this->prophesize(PropertyAccessorInterface::class)->reveal(),
            $this->prophesize(NameConverterInterface::class)->reveal()
        );

        self::assertSame(
            [
                'id' => [
                    'property' => 'id',
                    'type' => 'int',
                    'required' => false,
                ],
                'id[]' => [
                    'property' => 'id',
                    'type' => 'int',
                    'required' => false,
                ],
                'name' => [
                    'property' => 'name',
                    'type' => 'string',
                    'required' => false,
                ],
                'name[]' => [
                    'property' => 'name',
                    'type' => 'string',
                    'required' => false,
                ],
                'date' => [
                    'property' => 'date',
                    'type' => \DateTimeInterface::class,
                    'required' => false,
                ],
                'date[]' => [
                    'property' => 'date',
                    'type' => \DateTimeInterface::class,
                    'required' => false,
                ],
                'weird' => [
                    'property' => 'weird',
                    'type' => 'string',
                    'required' => false,
                ],
                'weird[]' => [
                    'property' => 'weird',
                    'type' => 'string',
                    'required' => false,
                ],
            ],
            $matchFilter->getDescription(Foo::class)
        );
    }
}
