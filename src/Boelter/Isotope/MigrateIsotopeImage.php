<?php

namespace Boelter\Isotope;

use ContaoCommunityAlliance\UrlBuilder\UrlBuilder;
use Symfony\Component\Finder\Finder;

class MigrateIsotopeImage extends \Backend implements \executable
{
    /**
     * @return boolean True if the module is active
     */
    public function isActive()
    {
        return \Input::get('act') == 'iso_tinypng_migrate';
    }

    /**
     * Generate the module
     *
     * @return string
     */
    public function run()
    {
        $objTemplate           = new \BackendTemplate('be_iso_tinypng_migrate');
        $objTemplate->isActive = $this->isActive();
        $objTemplate->action   = ampersand(\Environment::get('request'));

        // Rebuild the index
        if (\Input::get('act') === 'iso_tinypng_migrate') {

            // Check the request token
            if (!isset($_GET['rt']) || !\RequestToken::validate(\Input::get('rt'))) {
                $this->Session->set('INVALID_TOKEN_URL', \Environment::get('request'));
                $this->redirect('contao/confirm.php');
            }

            $finder = new Finder();
            $finder->files()->in(TL_ROOT . '/isotope')->name('*.jpg')->name('*.png');

            try {
                \Tinify\setKey(\Config::get('tinypng_api_key'));
                \Tinify\validate();
            } catch (\Tinify\AccountException $e) {
                return;
            }

            $migration    = 0;
            $messages     = array();
            $stepSize     = 10;
            $currentStep  = (int)\Input::get('currentStep') ?: 0;
            $currentCount = 0;
            $stepStart    = $currentStep * $stepSize;
            $stepLimit    = $stepStart + $stepSize;
            $maxStep      = (ceil($finder->count() / $stepSize) - 1);

            foreach ($finder as $file) {
                if ($currentCount < $stepStart) {
                    $currentCount++;
                    continue;
                }

                $source = \Tinify\fromFile($file->getRealPath());
                if ($source->toFile($file->getRealPath())) {
                    $migration++;
                    $messages[] =
                        array(
                            'type'    => 'confirm',
                            'message' => sprintf(
                                $GLOBALS['TL_LANG']['tl_maintenance']['iso_tinypng_migrate']['confirm'],
                                $file->getRealPath()
                            ),
                        );
                } else {
                    $messages[] =
                        array(
                            'type'    => 'error',
                            'message' => sprintf(
                                $GLOBALS['TL_LANG']['tl_maintenance']['iso_tinypng_migrate']['error'],
                                $file->getRealPath()
                            ),
                        );
                }

                $currentCount++;

                if ($stepLimit == $currentCount && $currentStep < $maxStep) {
                    $urlBuilder = new UrlBuilder(\Environment::get('request'));
                    $urlBuilder->unsetQueryParameter('currentStep');
                    $urlBuilder->insertQueryParameter('currentStep', $currentStep + 1, 0);
                    $objTemplate->redirectUrl = $urlBuilder->getUrl();
                    break;
                } elseif ($stepLimit == $currentCount && $currentStep == $maxStep) {
                    break;
                }
            }

            $objTemplate->message = sprintf(
                $GLOBALS['TL_LANG']['tl_maintenance']['iso_tinypng_migrate']['message'],
                $stepSize * ($currentStep + 1),
                $finder->count()
            );

            $objTemplate->messages = $messages;
        }

        return $objTemplate->parse();
    }
}
