<?php
// No direct access
defined('_JEXEC') or die;

/**
 *
 * @package     Joomla.Plugin
 * @subpackage  System.KvokkaImage
 * @since       2.5+
 * @author      dsv
 */
class PlgSystemKvokka_Image extends JPlugin
{
    /**
     * @param object $subject
     * @param array $config
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
    }

    public function onAfterInitialise()
    {
        JLoader::register('KvokkaImageCore', JPATH_PLUGINS . '/system/kvokka_image/lib/KvokkaImageCore.php');
        JLoader::register('KvokkaImageGD', JPATH_PLUGINS . '/system/kvokka_image/lib/KvokkaImageGD.php');
        JLoader::register('KvokkaImageImagick', JPATH_PLUGINS . '/system/kvokka_image/lib/KvokkaImageImagick.php');
        JLoader::register('KvokkaImage', JPATH_PLUGINS . '/system/kvokka_image/lib/KvokkaImage.php');
    }
}
