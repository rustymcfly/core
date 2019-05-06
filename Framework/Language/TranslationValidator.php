<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Language;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteCommandValidatorInterface;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

class TranslationValidator implements WriteCommandValidatorInterface
{
    public const VIOLATION_DELETE_SYSTEM_TRANSLATION = 'delete-system-translation-violation';

    public function preValidate(array $writeCommands, WriteContext $context): void
    {
        if ($context->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $violations = new ConstraintViolationList();
        $violations->addAll($this->getDeletedSystemTranslationViolations($writeCommands));

        $this->tryToThrow($violations);
    }

    /**
     * @param WriteCommandInterface[] $writeCommands *
     */
    public function postValidate(array $writeCommands, WriteContext $context): void
    {
    }

    private function getDeletedSystemTranslationViolations(array $writeCommands): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();

        foreach ($writeCommands as $writeCommand) {
            if (!$writeCommand instanceof DeleteCommand) {
                continue;
            }
            $pk = $writeCommand->getPrimaryKey();
            if (!isset($pk['language_id'])) {
                continue;
            }

            $def = $writeCommand->getDefinition();
            if (!$def instanceof EntityTranslationDefinition) {
                continue;
            }

            if (Uuid::fromBytesToHex($pk['language_id']) !== Defaults::LANGUAGE_SYSTEM) {
                continue;
            }

            $fks = $this->getFkFields($def);
            $id = Uuid::fromBytesToHex($pk[$fks['id']->getStorageName()]);
            $violations->add(
                $this->buildViolation(
                    'Cannot delete system translation',
                    ['{{ id }}' => $id],
                    null,
                    '/' . $id . '/translations/' . Defaults::LANGUAGE_SYSTEM,
                    [$id, Defaults::LANGUAGE_SYSTEM],
                    self::VIOLATION_DELETE_SYSTEM_TRANSLATION
                )
            );
        }

        return $violations;
    }

    /**
     * @return FkField[]
     */
    private function getFkFields(EntityTranslationDefinition $definition): array
    {
        $rootEntity = $definition->getParentDefinition();
        $idStorageName = $rootEntity->getEntityName() . '_id';
        $versionIdStorageName = $rootEntity->getEntityName() . '_version_id';

        $pks = $definition->getPrimaryKeys();
        $idField = $pks->getByStorageName($idStorageName);
        if (!$idField || !$idField instanceof FkField) {
            throw new \RuntimeException(sprintf('`%s` primary key should have column `%s`', $definition->getClass(), $idStorageName));
        }
        $fields = [
            'id' => $idField,
        ];

        $versionIdField = $pks->getByStorageName($versionIdStorageName);
        if ($versionIdField && $versionIdField instanceof FkField) {
            $fields['version'] = $versionIdField;
        }

        return $fields;
    }

    /**
     * @throws WriteConstraintViolationException
     */
    private function tryToThrow(ConstraintViolationList $violations): void
    {
        if ($violations->count() > 0) {
            throw new WriteConstraintViolationException($violations);
        }
    }

    private function buildViolation(string $messageTemplate, array $parameters, $root = null, ?string $propertyPath = null, $invalidValue = null, $code = null): ConstraintViolationInterface
    {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            $root,
            $propertyPath,
            $invalidValue,
            $plural = null,
            $code,
            $constraint = null,
            $cause = null
        );
    }
}
