<?php
declare(strict_types=1);

namespace {
    require __DIR__ . '/../vendor/autoload.php';
}

/**
 * Minimal Bitrix stubs required to load Infrastructure\Bitrix\Repository\ORM\OrmDataManager
 * in a test environment where Bitrix runtime is not available.
 */
namespace Bitrix\Main\ORM\Data {
    class DataManager
    {
    }
}

namespace Bitrix\Main\ORM\Fields\Relations {
    class Reference
    {
    }
}

namespace Bitrix\Main\Entity {
    class Base
    {
    }
}

namespace Bitrix\Main {
    class Application
    {
    }
    
    class ArgumentException extends \Exception
    {
    }
    
    class SystemException extends \Exception
    {
    }
}

namespace Bitrix\Main\DB {
    class Connection
    {
    }
    
    class Result
    {
    }
    
    class SqlQueryException extends \Exception
    {
    }
}
