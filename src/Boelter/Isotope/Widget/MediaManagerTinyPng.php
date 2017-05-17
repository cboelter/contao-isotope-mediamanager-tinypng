<?php

namespace Boelter\Isotope\Widget;

use Isotope\Widget\MediaManager;

class MediaManagerTinyPng extends MediaManager
{
    /**
     * Validate input and set value
     */
    public function validate()
    {
        $this->varValue = $this->getPost($this->strName);

        if (!is_array($this->varValue)) {
            $this->varValue = array();
        }

        // Fetch fallback language record
        $arrFallback = $this->getFallbackData();

        if (is_array($arrFallback)) {
            foreach ($arrFallback as $k => $arrImage) {
                if ('all' === $arrImage['translate']) {
                    unset($arrFallback[$k]);
                }
            }
        }

        // Check that image is not assigned in fallback language
        foreach ($this->varValue as $k => $v) {
            if (is_array($arrFallback) && in_array($v, $arrFallback)) {
                $this->addError($GLOBALS['TL_LANG']['ERR']['imageInFallback']);
            } elseif ($arrFallback !== false) {
                $this->varValue[$k]['translate'] = 'all';
            }
        }

        \System::loadLanguageFile('tl_iso_product');

        // Move all temporary files
        foreach ($this->varValue as $k => $v) {
            if (stripos($v['src'], $this->strTempFolder) !== false) {
                $strFile = $this->getFilePath(basename($v['src']));

                if (is_file(TL_ROOT . '/' . $strFile)
                    && md5_file(TL_ROOT . '/' . $v['src']) != md5_file(TL_ROOT . '/' . $strFile)
                ) {
                    $pathinfo = pathinfo($v['src']);
                    $strFile  = $this->getFilePath(
                        standardize($pathinfo['filename']) . '-' . substr(md5_file(TL_ROOT . '/' . $strFile), 0, 8)
                        . '.' . $pathinfo['extension']
                    );
                }

                // Make sure the parent folder exists
                new \Folder(dirname($strFile));

                try {
                    \Tinify\setKey(\Config::get('tinypng_api_key'));
                    \Tinify\validate();
                } catch (\Tinify\AccountException $e) {
                    $this->addError($e->getMessage());

                    return;
                }

                $sourceFileName = TL_ROOT . '/' . $v['src'];
                $targetFileName = TL_ROOT . '/' . $strFile;

                if (file_exists($sourceFileName)) {
                    try {
                        $source = \Tinify\fromFile($sourceFileName);

                        if ($source->toFile($targetFileName)) {
                            $this->varValue[$k]['src'] = basename($targetFileName);

                        } else {
                            $this->addError(
                                $GLOBALS['TL_LANG']['tl_iso_product']['mediaManagerTinyPng']['compressionError']
                            );
                            unset($this->varValue[$k]);
                        }
                    } catch (\Tinify\Exception $e) {
                        $this->addError($e->getMessage());

                        return;
                    }

                } else {
                    $this->addError($GLOBALS['TL_LANG']['tl_iso_product']['mediaManagerTinyPng']['fileMissing']);
                    unset($this->varValue[$k]);
                }
            }
        }

        // Check if there are values
        if ($this->mandatory) {
            foreach ($this->varValue as $file) {
                if (is_file(TL_ROOT . '/' . $this->getFilePath($file['src']))) {
                    return;
                }
            }

            if (!is_array($arrFallback) || empty($arrFallback)) {
                $this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'], $this->strLabel));
            }
        }

        if (empty($this->varValue)) {
            $this->varValue = null;
        }
    }
}