<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Uuid;

/**
 * Persiste UUIDs como CHAR(36), compatível com o schema MySQL das migrations.
 */
final class UuidCharType extends Type
{
    public const NAME = 'uuid';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uuid
    {
        if ($value instanceof Uuid || $value === null) {
            return $value;
        }

        if (!\is_string($value)) {
            $this->throwInvalidType($value);
        }

        try {
            return Uuid::fromString($value);
        } catch (\InvalidArgumentException $e) {
            $this->throwValueNotConvertible($value, $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof AbstractUid) {
            return $value->toRfc4122();
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (!\is_string($value)) {
            $this->throwInvalidType($value);
        }

        try {
            return Uuid::fromString($value)->toRfc4122();
        } catch (\InvalidArgumentException $e) {
            $this->throwValueNotConvertible($value, $e);
        }
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    private function throwInvalidType(mixed $value): never
    {
        if (!class_exists(InvalidType::class)) {
            throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['null', 'string', Uuid::class]);
        }

        throw InvalidType::new($value, self::NAME, ['null', 'string', Uuid::class]);
    }

    private function throwValueNotConvertible(mixed $value, \Throwable $previous): never
    {
        if (!class_exists(ValueNotConvertible::class)) {
            throw ConversionException::conversionFailed($value, self::NAME, $previous);
        }

        throw ValueNotConvertible::new($value, self::NAME, null, $previous);
    }
}
