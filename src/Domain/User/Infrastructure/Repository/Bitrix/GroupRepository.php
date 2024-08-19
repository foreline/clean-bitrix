<?php
declare(strict_types=1);

namespace Domain\User\Infrastructure\Repository\Bitrix;

use CGroup;
use Domain\User\Aggregate\Group;
use Domain\User\Aggregate\GroupCollection;
use Domain\User\Entity\GroupEntity;
use Domain\User\Infrastructure\Repository\GroupRepositoryInterface;
use ReflectionClass;

/**
 *
 */
class GroupRepository extends GroupProxy implements GroupRepositoryInterface
{
    protected $res = null;

    /**
     * Returns class constants
     * @return array<string, string>
     */
    public static function getFields(): array
    {
        $reflection = new ReflectionClass(__CLASS__);
        return $reflection->getConstants();
    }

    /**
     * @param GroupEntity $group
     * @return int
     */
    public function create(GroupEntity $group): int
    {
        $cGroup = new CGroup();
        $groupData = $this->entityToArray($group);
        $groupData = array_change_key_case($groupData, CASE_UPPER);
        $id = $cGroup->Add($groupData);
        return (int)$id;
    }

    /**
     * @param GroupEntity $group
     * @return bool
     */
    public function update(GroupEntity $group): bool
    {
        $cGroup = new CGroup();
        $groupData = $this->entityToArray($group);
        $groupData = array_change_key_case($groupData, CASE_UPPER);
        $result = $cGroup->Update($group->getId(), $groupData);
        return $result;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return (bool) CGroup::Delete($id);
    }

    /**
     * @param array $filter
     * @param array $sort
     * @param array $limit
     * @param array $fields
     * @return GroupRepository|null
     * @noinspection PhpTooManyParametersInspection
     */
    private function query(array $filter = [], array $sort = [], array $limit = [], array $fields = []): ?self
    {
        $arFilter = [];
        
        $filter = array_change_key_case($filter, CASE_UPPER);

        if ( isset($filter['ID']) && is_array($filter['ID']) ) {
            $arFilter['ID'] = implode(' | ', $filter['ID']);
        } elseif ( !empty($filter['ID']) ) {
            $arFilter['ID'] = (int)$filter['ID'];
        }
        
        if ( !empty($filter['STRING_ID']) ) {
            $arFilter['STRING_ID'] = (string)$filter['STRING_ID'];
        }
        
        $this->res = CGroup::GetList(
            ($by = 'ID'),
            ($order = 'DESC'),
            $arFilter
        );

        if ( !$this->res ) {
            return null;
        }

        return $this;
    }

    /**
     * @param array $filter
     * @param array $sort
     * @param array $limit
     * @param array $fields
     * @return ?GroupCollection
     * @noinspection PhpTooManyParametersInspection
     */
    public function find(array $filter = [], array $sort = [], array $limit = [], array $fields = []): ?GroupCollection
    {
        if ( !$this->query($filter, $sort, $limit, $fields) ) {
            return null;
        }

        $groups = new GroupCollection();

        while ( $group = $this->fetch() ) {
            $groups->addItem($group);
        }

        return $groups;
    }

    /**
     * @param int $id
     * @return ?Group
     */
    public function findById(int $id): ?Group
    {
        if ( 0 >= $id ) {
            return null;
        }

        return $this->find(['id' => $id])?->current();
    }

    /**
     * @return Group|null
     */
    private function fetch(): ?Group
    {
        if ( null === $this->res ) {
            return null;
        }

        if ( !$arGroup = $this->res->fetch() ) {
            return null;
        }

        $group = new Group();

        $group->setId((int)$arGroup['ID']);
        $group->setName((string)$arGroup['NAME']);
        $group->setDescription((string)$arGroup['DESCRIPTION']);
        $group->setCode((string)$arGroup['STRING_ID']);
        $group->setSort((int)$arGroup['C_SORT']);
        $group->setActive('Y' === $arGroup['ACTIVE']);
        $group->setAnonymous('Y' === $arGroup['ANONYMOUS']);

        return $group;
    }
}