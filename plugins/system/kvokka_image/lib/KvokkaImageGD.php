<?php

jimport('joomla.image.image');

class KvokkaImageGD extends KvokkaImageCore
{
    public function resizeImage()
    {
        // Проверка существования фаила
        if (!JFile::exists($this->originalPathToFIle)) {
            return false;
        }

        // Проверка типа
        $type = exif_imagetype($this->originalPathToFIle);
        if (!in_array($type, array(IMAGETYPE_GIF, IMAGETYPE_PNG, IMAGETYPE_JPEG))) {
            return false;
        }

        if ($this->width == 0 && $this->height == 0) {
            // Задает размеры оригенального изображения
            $imgInfo = getimagesize($this->originalPathToFIle);
            if ($imgInfo) {
                $this->width = $imgInfo[0];
                $this->height = $imgInfo[1];
            }
        }

        if ($this->width == 0 && $this->height == 0) {
            return false;
        }

        // Загрузка исходного изображения
        $image = new JImage($this->originalPathToFIle);

        if (!$this->crop) {

            if ($this->scale == 'h') {
                $this->width = 10000;
            } elseif ($this->scale == 'w') {
                $this->height = 1;
            }

            $image->resize($this->width, $this->height, false, $this->getScale($this->scale));

        } else if ($this->crop) {

            $image->cropResize($this->width, $this->height, false);

        }

        if ($this->wt && $this->srcWatermark) {

            $wtPath = str_replace('//', '/', JPATH_ROOT . '/' . $this->srcWatermark);

            if (!JFile::exists($wtPath)) {
                return false;
            }

            $wt = new JImage($wtPath);

            $this->wtHeight = !empty($this->wtHeight) ? $this->wtHeight : $this->width / 2;
            $this->wtWidth = !empty($this->wtWidth) ? $this->wtWidth : $this->width / 2;

            $wt->resize($this->wtWidth, $this->wtHeight, false, $this->getScale($this->wtScale));

            if ($this->wtCenter) {
                $this->wtBottom = $image->getHeight() / 2 - $wt->getHeight() / 2;
                $this->wtRigth = $image->getWidth() / 2 - $wt->getWidth() / 2;
            }

            $image->watermark($wt, $this->wtOpacity, $this->wtBottom, $this->wtRigth);
        }

        $image->toFile($this->cachePathToFile, $type, $this->getOptions($type));

        return $this->cacheUrl;
    }

    /**
     * Возрощает параметры для изображения
     *
     * @param int $type
     * @return array
     */
    protected function getOptions($type)
    {
        $options = array();
        // Стапень сжатия изображения
        switch ($type) {

            case IMAGETYPE_JPEG:
                $options['quality'] = $this->quality;
                break;

            case IMAGETYPE_PNG:
                $options['quality'] = 9 - round(($this->quality - 10) / 10);
                break;
        }

        return $options;
    }

    /**
     * Метод маштобирования
     *
     * @param string $name
     * @return int
     */
    protected function getScale($name)
    {
        switch ($name) {
            case 'h':
                $scale = JImage::SCALE_OUTSIDE;
                break;
            case 'w':
                $scale = JImage::SCALE_INSIDE;
                break;
            case 'full':
            default:
                $scale = JImage::SCALE_FILL;
                break;
        }

        return $scale;
    }
}
