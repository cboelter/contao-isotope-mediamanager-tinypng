<?php

namespace Boelter\Isotope;

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

            $migration = 0;
            $messages  = array();

            foreach ($finder as $file) {
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
            }

            $objTemplate->message  = sprintf(
                $GLOBALS['TL_LANG']['tl_maintenance']['iso_tinypng_migrate']['message'],
                $migration,
                $finder->count()
            );
            $objTemplate->messages = $messages;
        }

        return $objTemplate->parse();
    }
}
