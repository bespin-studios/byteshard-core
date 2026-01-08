<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Permission;

use byteShard\Enum\Access;
use byteShard\Enum\AccessType;
use byteShard\Exception;
use byteShard\Internal\Debug;
use byteShard\Session;
use UnitEnum;

trait PermissionImplementation
{
    private int   $accessType         = AccessType::RW;
    private int   $parentAccessType   = AccessType::RW;
    private array $permissions        = [];
    private bool  $unrestrictedAccess = false;
    private int   $permissionAccessType;

    /**
     * permissions are additive, the highest permission will be in place
     * @session write (Cell, LayoutContainer, Toolbar)
     * @session none (Node, CellContent, SharedParent, Column, ToolbarObject)
     */
    public function setPermission(string|UnitEnum ...$permissions): self
    {
        foreach ($permissions as $permission) {
            if ($permission instanceof UnitEnum) {
                $this->permissions[] = $permission->name;
            } else {
                $this->permissions[] = $permission;
            }
        }
        $this->calculatePermissionAccessType();
        return $this;
    }

    private function calculatePermissionAccessType(): void
    {
        $permissionAccessType[] = 0;
        foreach ($this->permissions as $permission) {
            $permissionAccessType[] = Session::getPermissionAccessType($permission);
        }
        $this->permissionAccessType = max($permissionAccessType);
    }

    /**
     * @session write (Cell, LayoutContainer, Toolbar)
     * @session none (Node, CellContent, SharedParent, Column, ToolbarObject)
     */
    public function setUnrestrictedAccess(bool $bool = true): self
    {
        $this->unrestrictedAccess = $bool;
        return $this;
    }

    public function getUnrestrictedAccess(): bool
    {
        return $this->unrestrictedAccess;
    }

    /**
     * @session read
     */
    public function getAccessType(): int
    {
        if ($this->unrestrictedAccess === true) {
            return AccessType::RW;
        }
        if (!empty($this->permissions)) {
            if (!isset($this->permissionAccessType)) {
                // shouldn't happen since permission_access_type is evaluated in method setPermission
                // if it happens, it might be written to the session, if not, it's not so important since in that case it will be evaluated in the next call again
                // therefore we declare this method as @session read
                $this->calculatePermissionAccessType();
            }
            $accessType = min($this->permissionAccessType, $this->accessType, $this->parentAccessType);
            if (AccessType::is_enum($accessType)) {
                return $accessType;
            }
            return AccessType::NONE;
        }
        $accessType = min($this->accessType, $this->parentAccessType);
        if (AccessType::is_enum($accessType)) {
            return $accessType;
        }
        return AccessType::NONE;
    }

    /**
     * @session write (Cell, LayoutContainer, Toolbar)
     * @session none (Node, CellContent, SharedParent, Column, ToolbarObject)
     */
    public function setAccessType(int|Access $accessType): static
    {
        if ($accessType instanceof Access) {
            $this->accessType = $accessType->value;
        } elseif (AccessType::is_enum($accessType)) {
            $this->accessType = $accessType;
        } else {
            Debug::info(__METHOD__.": Method only accepts enums of type Enum\\AccessType. Input was '".gettype($accessType)."'");
            $this->accessType = AccessType::none;
        }
        return $this;
    }

    /**
     * @session write (Cell, LayoutContainer, Toolbar)
     * @session none (Node, CellContent, SharedParent, Column, ToolbarObject)
     * @throws Exception
     * @internal
     */
    public function setParentAccessType(int|Access $accessType): static
    {
        if ($accessType instanceof Access) {
            $this->accessType = $accessType->value;
        } elseif (AccessType::is_enum($accessType)) {
            $this->parentAccessType = $accessType;
        } else {
            $e = new Exception(__METHOD__.": Method only accepts enums of type Enum\\AccessType. Input was '".gettype($accessType)."'");
            $e->setLocaleToken('byteShard.permission.setAccessType.invalidArgument.access_type');
            throw $e;
        }
        return $this;
    }
}
