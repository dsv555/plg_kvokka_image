<?php

class KvokkaImage
{
    /**
     * @param string $src - url изображения
     * @param array $options - параметры масштабирования
     * @return string
     */
    public static function resize($src, $options = '')
    {
        $params = self::params();

        // Выбор способа обработки изображений
        switch ($params['core']) {
            case 'IMAGICK':
                $image = new KvokkaImageImagick($src, $params, $options);
                break;

            default:
                $image = new KvokkaImageGD($src, $params, $options);
                break;
        }

        return $image->resize();
    }

    /**
     * Параметры плагина
     *
     * @return void
     */
    public static function params()
    {
        $paramsArr = array();
        $plugin = JPluginHelper::getPlugin('system', 'kvokka_image');

        if (isset($plugin->params) && !empty($plugin->params)) {
            $params = json_decode($plugin->params);

            $paramsArr['default'] = !empty($params->default_img) ? $params->default_img : '/plugins/system/kvokka_image/media/noimage.png';
            $paramsArr['watermark'] = !empty($params->watermark_img) ? $params->watermark_img : null;
            $paramsArr['quality'] = !empty($params->quality) ? $params->quality : 75;
            $paramsArr['core'] = !empty($params->core) ? $params->core : 'GD';
        }

        return $paramsArr;
    }
}
