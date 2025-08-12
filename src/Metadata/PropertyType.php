<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata;

use ReflectionParameter;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

use function array_map;

class PropertyType
{
    private TypeResolver $typeResolver;

    public function __construct()
    {
        $this->typeResolver = TypeResolver::create();
    }

    /**
     * @param class-string $class
     *
     * @return array<string>
     */
    public function getEventClassesByPropertyTypes(ReflectionParameter $parameter): array
    {
        $propertyType = $parameter->getType();

        if ($propertyType === null) {
            throw new ArgumentTypeIsMissing($methodName);
        }

        $type = $this->typeResolver->resolve($propertyType);

        if ($type instanceof ObjectType) {
            return [$type->getClassName()];
        }

        if ($type instanceof UnionType) {
            return array_map(
                static function (Type $type): string {
                    if ($type instanceof ObjectType) {
                        return $type->getClassName();
                    }

                    throw new ArgumentTypeIsMissing($methodName);
                },
                $type->getTypes(),
            );
        }

        throw new ArgumentTypeIsMissing($methodName);
    }
}
