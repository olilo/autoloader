<?php

namespace HDNET\Autoloader\Listener;

use HDNET\Autoloader\Utility\ExtendedUtility;
use HDNET\Autoloader\Utility\ModelUtility;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ElementBackendPreviewListener
{
    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        $row = $event->getRecord();

        if (!$this->isAutoloaderContentobject($row)) {
            return;
        }

        if (!$this->hasBackendPreview($row)) {
            return;
        }

        $event->setPreviewContent($this->getBackendPreview($row));
    }

    /**
     * Render the Backend Preview Template and return the HTML.
     *
     * @param mixed[] $row
     *
     * @return string
     */
    public function getBackendPreview(array $row): string
    {
        if (!$this->hasBackendPreview($row)) {
            return '';
        }

        $cacheIdentifier = 'tt-content-preview-' . $row['uid'] . '-' . $row['tstamp'];

        /** @var FrontendInterface $cache */
        $cache = GeneralUtility::makeInstance(CacheManager::class)
            ->getCache('pagesection')
        ;
        if ($cache->has($cacheIdentifier)) {
            return $cache->get($cacheIdentifier);
        }

        $ctype = $row['CType'];
        /** @var array $config */
        $config = $GLOBALS['TYPO3_CONF_VARS']['AUTOLOADER']['ContentObject'][$ctype];

        $model = ModelUtility::getModel($config['modelClass'], $row);

        $view = ExtendedUtility::createExtensionStandaloneView($config['extensionKey'], $config['backendTemplatePath']);
        $view->assignMultiple([
            'data' => $row,
            'object' => $model,
        ]);
        $output = $view->render();
        $cache->set($cacheIdentifier, $output);

        return $output;
    }

    /**
     * Check if the ContentObject has a Backend Preview Template.
     *
     * @param mixed[] $row
     */
    public function hasBackendPreview(array $row): bool
    {
        if (!$this->isAutoloaderContentobject($row)) {
            return false;
        }
        $ctype = $row['CType'];
        /** @var array $config */
        $config = $GLOBALS['TYPO3_CONF_VARS']['AUTOLOADER']['ContentObject'][$ctype];

        $beTemplatePath = GeneralUtility::getFileAbsFileName($config['backendTemplatePath']);

        return is_file($beTemplatePath);
    }

    /**
     * Check if the the Element is registered by the ContenObject-Autoloader.
     */
    public function isAutoloaderContentobject(array $row): bool
    {
        $cType = $row['CType'];

        return isset($GLOBALS['TYPO3_CONF_VARS']['AUTOLOADER']['ContentObject'][$cType]);
    }
}
