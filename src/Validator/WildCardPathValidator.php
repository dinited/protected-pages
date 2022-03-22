<?php

namespace Drupal\protected_pages\Validator;

use Drupal\Core\Path\PathValidator;

final class WildCardPathValidator extends PathValidator
{
    const WILDCARD_SYMBOL = '*';
    const EMPTY_STRING = '';

    /**
     * {@inheritdoc}
     */
    public function isValid($path) {
        $path = $this->normalizePath($path);
        return (bool) $this->getUrlIfValid($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getUrlIfValid($path) {
        $path = $this->normalizePath($path);
        return $this->getUrl($path, TRUE);
    }

    /**
     * {@inheritdoc}
     */
    public function getUrlIfValidWithoutAccessCheck($path) {
        $path = $this->normalizePath($path);
        return $this->getUrl($path, FALSE);
    }

    /**
     * @param string $path
     * @return string
     */
    private function normalizePath($path) {
        if(false !== strpos($path, self::WILDCARD_SYMBOL)) {
            $path = str_replace(self::WILDCARD_SYMBOL, self::EMPTY_STRING, $path);
        }
        return $path;
    }
}