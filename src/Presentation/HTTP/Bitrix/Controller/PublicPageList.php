<?php
declare(strict_types=1);

namespace Presentation\HTTP\Bitrix\Controller;

use Bitrix\Main\Page\Asset;
use Bitrix\Main\UI\Extension;
use CComponentEngine;
use CJSCore;
use Domain\Event\Publisher;
use Domain\Events\ExceptionOccurredEvent;
use Throwable;

/**
 *
 */
class PublicPageList extends PublicPageController
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
            Extension::load('ui.buttons');
            CJSCore::init('sidepanel');
    
            echo '<script src="' . self::$urlPrefix . '/js/script.js" type="module"></script>';
            Asset::getInstance()->addCss(self::$urlPrefix . '/css/style.css');
    
            foreach ( self::$filesToInclude as $file  ) {
                if ( file_exists($file) ) {
                    require_once $file;
                }
            }
    
            global $APPLICATION;
            $APPLICATION->SetTitle('Список');
            
            if ( 'bootstrap' !== $_ENV['MENU_TEMPLATE'] ) {
                $APPLICATION->IncludeComponent(self::$appName . ':menu', '', []);
            }
            
            if ( 'custom' === $_ENV['BITRIX_TEMPLATE'] ) {
                echo '<div class="px-3 border clearfix d-flex bg-light pagetitle-inner-container">';
                echo '<div class="pagetitle"><h1>' . $APPLICATION->GetTitle() . '</h1></div>';
                $APPLICATION->ShowViewContent('inside_pagetitle');
                echo '</div>';
            }
            
            $APPLICATION->AddChainItem($APPLICATION->GetTitle());
            
            $componentName = self::$appName . ':' .
                $moduleName . '.' .
                (
                    !empty($submodule)
                        ? $submodule . '.'
                        : ''
                ) .
                $entityName .
                '.list';
    
            if ( !$this->isComponentExists($componentName) ) {
                $componentName = self::$appName . ':' . $moduleName . '.tiles';
            }
            
            $APPLICATION->IncludeComponent(
                'bitrix:ui.sidepanel.wrapper',
                '',
                [
                    'POPUP_COMPONENT_NAME' => $componentName,
                    'POPUP_COMPONENT_TEMPLATE_NAME' => '',
                    'POPUP_COMPONENT_PARAMS' => [],
                    'USE_PADDING' => false,
                    'USE_LINK_TARGETS_REPLACING' => true,
                    'USE_UI_TOOLBAR' => 'Y',
                    'BUTTONS' => ['close'],
                ]
            );
            
        } catch ( Throwable $e ) {
            Publisher::getInstance()->publish(new ExceptionOccurredEvent($e));
            echo '<div class="ui-alert ui-alert-icon-danger ui-alert-danger"><span class="ui-alert-message">';
            echo 'Error: ' . $e->getMessage();
            echo '</span><span class="ui-alert-close-btn" onclick="this.parentNode.style.display=\'none\';"></span></div>';
        }
        
        require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
    }
    
    /**
     * @param string $componentName
     * @return bool
     */
    private function isComponentExists(string $componentName): bool
    {
        $componentPath = CComponentEngine::MakeComponentPath($componentName);
        $componentPath = getLocalPath('components' . $componentPath /*. '/class.php'*/);
        return ( $componentPath !== false && file_exists($_SERVER['DOCUMENT_ROOT'] . $componentPath) );
    }
}