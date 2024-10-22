<?php

namespace Innovatif\i19n\Messages;

use Innovatif\i19n\Translator\i19nTranslator;
use SilverStripe\i18n\i18n;
use SilverStripe\i18n\Messages\Symfony\SymfonyMessageProvider;

class i19nMessageProvider extends SymfonyMessageProvider
{
    private function geti19nTranslator()
    {
        return new i19nTranslator();
    }

    public function translate($entity, $default, $injection)
    {
        // Ensure localisation is ready
        $locale = i18n::get_locale();
        $this->load($locale);

        // Prepare arguments
        $arguments = $this->templateInjection($injection);

        try {
            $result = $this->geti19nTranslator()->trans($entity, $arguments, $locale);
        } catch (\Exception) {
            $result = false;
        }

        //See if we have a translation if none is found
        if ($result === false || $entity === $result) {
            //$result = $this->geti19nValue($entity, $locale);

            // Pass to symfony translator
            $result = $this->getTranslator()->trans($entity, $arguments, 'messages', $locale);

            if ($result && $entity !== $result) {
                return $result;
            }

            // else Manually inject default if no translation found
            $result = $this->getTranslator()->trans($default, $arguments, 'messages', $locale);
        }

        return $result;
    }
}
