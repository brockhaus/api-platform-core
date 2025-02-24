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

namespace ApiPlatform\Hydra\Serializer;

use ApiPlatform\Api\Entrypoint;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes the API entrypoint.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class EntrypointNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public const FORMAT = 'jsonld';

    private $resourceMetadataFactory;
    private $iriConverter;
    private $urlGenerator;

    public function __construct(ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory, IriConverterInterface $iriConverter, UrlGeneratorInterface $urlGenerator)
    {
        $this->iriConverter = $iriConverter;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        $entrypoint = [
            '@context' => $this->urlGenerator->generate('api_jsonld_context', ['shortName' => 'Entrypoint']),
            '@id' => $this->urlGenerator->generate('api_entrypoint'),
            '@type' => 'Entrypoint',
        ];

        foreach ($object->getResourceNameCollection() as $resourceClass) {
            $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);

            foreach ($resourceMetadata as $resource) {
                if ($resource->getExtraProperties()['is_alternate_resource_metadata'] ?? false) {
                    continue;
                }

                foreach ($resource->getOperations() as $operation) {
                    $key = lcfirst($resource->getShortName());
                    if (!$operation instanceof CollectionOperationInterface || isset($entrypoint[$key])) {
                        continue;
                    }

                    try {
                        $entrypoint[$key] = $this->iriConverter->getIriFromResource($resourceClass, UrlGeneratorInterface::ABS_PATH, $operation); // @phpstan-ignore-line phpstan issue as type is CollectionOperationInterface & Operation
                    } catch (InvalidArgumentException|OperationNotFoundException) {
                        // Ignore resources without GET operations
                    }
                }
            }
        }

        ksort($entrypoint);

        return $entrypoint;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return self::FORMAT === $format && $data instanceof Entrypoint;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
