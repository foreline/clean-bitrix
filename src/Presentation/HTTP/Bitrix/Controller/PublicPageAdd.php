<?php
declare(strict_types=1);

namespace Presentation\HTTP\Bitrix\Controller;

use Bitrix\Main\LoaderException;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\UI\Extension;
use Domain\Event\Publisher;
use Domain\Events\ExceptionOccurredEvent;

/**
 *
 */
class PublicPageAdd extends PublicPageController
{
    /**
     * @param string $moduleName
     * @param string $entityName
     * @param string $submodule
     * @return void
     */
    public function getAction(string $moduleName, string $entityName, string $submodule = ''): void
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
        
        try {
            global $APPLICATION;
            
            Extension::load('ui');
            
            echo '<script src="' . self::$urlPrefix . '/js/script.js" type="module"></script>';
            Asset::getInstance()->addCss(self::$urlPrefix . '/css/style.css');
    
            foreach ( self::$filesToInclude as $file  ) {
                if ( file_exists($file) ) {
                    require_once $file;
                }
            }
    
            if ( 'bootstrap' !== $_ENV['MENU_TEMPLATE'] ) {
                $APPLICATION->IncludeComponent(self::$appName . ':menu', '', []);
            }
        
            $APPLICATION->SetTitle('Добавление');
            
            if ( !empty($submodule) ) {
                $componentName = self::$appName . ':' . $moduleName . '.' . $submodule . '.' . $entityName . '.add';
            } else {
                $componentName = self::$appName . ':' . $moduleName . '.' . $entityName . '.add';
            }
        
            $APPLICATION->IncludeComponent(
                'bitrix:ui.sidepanel.wrapper',
                '',
                [
                    'POPUP_COMPONENT_NAME' => $componentName,
                    'POPUP_COMPONENT_TEMPLATE_NAME' => '',
                    'POPUP_COMPONENT_PARAMS' => [],
                    'USE_PADDING' => false,
                    'USE_LINK_TARGETS_REPLACING' => false,
                    'USE_UI_TOOLBAR' => 'Y',
                ]
            );
            
        } catch (\Throwable $e) {
            Publisher::getInstance()->publish(new ExceptionOccurredEvent($e));
            echo '<div class="ui-alert ui-alert-icon-danger ui-alert-danger"><span class="ui-alert-message">';
            echo 'Error: ' . $e->getMessage();
            echo '</span><span class="ui-alert-close-btn" onclick="this.parentNode.style.display=\'none\';"></span></div>';
        }
        
        require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
    }
}