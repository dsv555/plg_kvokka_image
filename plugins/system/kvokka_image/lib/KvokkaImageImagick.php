<?php

class KvokkaImageImagick extends KvokkaImageCore
{
    public function resizeImage()
    {
        // Проверка существования фаила
        if (!JFile::exists($this->originalPathToFIle)) {
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
        $image = new Imagick($this->originalPathToFIle);

        if (!$this->crop) {

            if ($this->scale == 'w') {
                $image->thumbnailImage($this->width, 0, false, true);
            } else if ($this->scale == 'h') {
                $width = $this->height * ($image->getImageWidth() / $image->getImageHeight());
                $image->thumbnailImage($width, $this->height, true, true);
            }

        } else if ($this->crop) {

            $image->cropThumbnailImage($this->width, $this->height, true);

        }

        if ($this->wt && $this->srcWatermark) {

            $wtPath = str_replace('//', '/', JPATH_ROOT . '/' . $this->srcWatermark);

            if (!JFile::exists($wtPath)) {
                return false;
            }

            $wt = new Imagick($wtPath);
            $wt->evaluateImage(Imagick::EVALUATE_MULTIPLY, $this->wtOpacity / 100, Imagick::CHANNEL_ALPHA);

            $this->wtHeight = !empty($this->wtHeight) ? $this->wtHeight : $this->width / 2;
            $this->wtWidth = !empty($this->wtWidth) ? $this->wtWidth : $this->width / 2;

            if ($this->wtScale == 'w') {
                $wt->thumbnailImage($this->wtWidth, 0);
            } else if ($this->wtScale == 'h') {
                $width = $this->wtHeight * ($wt->getImageWidth() / $wt->getImageHeight());
                $wt->thumbnailImage($width, $this->wtHeight, true, true);
            }

            if ($this->wtCenter) {
                $this->wtBottom = $image->getImageHeight() / 2 - $wt->getImageHeight() / 2;
                $this->wtRigth = $image->getImageWidth() / 2 - $wt->getImageWidth() / 2;
            }

            $image->compositeImage($wt, imagick::COMPOSITE_OVER, $this->wtRigth, $this->wtBottom);
        }

        $image->setImageCompressionQuality($this->quality);
        $image->writeImages($this->cachePathToFile, true);

        return $this->cacheUrl;
    }
}
