<?php

namespace byteShard\Internal\Database;

use byteShard\Config\CustomDataModelInterface;
use byteShard\Config\OverrideDataModelInterface;
use byteShard\Database\Model;
use byteShard\DataModelInterface;
use byteShard\Environment;
use byteShard\LayoutCell;
use byteShard\Session;

class ModelCache
{
    private static ?array $sizeData = null;

    public static function getSizeData(string $name): array
    {
        if (self::$sizeData === null) {
            self::loadSizeData();
        }
        return self::$sizeData[$name] ?? [];
    }

    private static function loadSizeData(): void
    {
        $userId = Session::getUserId();
        if ($userId !== null) {
            $dataModel = self::getDataModel();
            $layouts = $dataModel->getCellSize(Session::getUserId());
            foreach ($layouts as $layout) {
                $layout = array_change_key_case((array)$layout);
                switch ($layout['type']) {
                    case LayoutCell::HEIGHT:
                    case LayoutCell::WIDTH:
                        self::$sizeData[$layout['tab'].'\\'.$layout['cell']][$layout['type']] = (int)$layout['value'];
                        break;
                    case LayoutCell::COLLAPSED:
                        self::$sizeData[$layout['tab'].'\\'.$layout['cell']][LayoutCell::COLLAPSED] = true;
                        break;
                }
            }
        }
    }

    private static function getDataModel(): DataModelInterface
    {
        global $env;
        if ($env instanceof CustomDataModelInterface) {
            return $env->getByteShardDataModel();
        }
        $model = match ($env->getDbDriver()) {
            Environment::DRIVER_MYSQL_PDO => new Model\MySQL\PDO(),
            Environment::DRIVER_PGSQL_PDO => new Model\PostgreSQL\PDO(),
            default                       => new Model\DeprecatedModel(),
        };

        if ($env instanceof OverrideDataModelInterface) {
            $model->setUserTableSchema($env->getOverrideDefinitions());
        }
        return $model;
    }

}