<?php 

namespace Innovatif\i19n\TextCollection;

use LogicException;
use SilverStripe\Core\Manifest\Module;
use SilverStripe\i18n\i18n;
use SilverStripe\i18n\Messages\YamlReader;
use SilverStripe\i18n\TextCollection\i18nTextCollector;

class i19nTextCollection extends i18nTextCollector
{

    /**
     * Extracts translatables from .php files.
     * Note: Translations without default values are omitted.
     *
     * @param string $content The text content of a parsed template-file
     * @param string $fileName Filename Optional filename
     * @param Module $module Module being collected
     * @return array Map of localised keys to default values provided for this code
     */
    public function collectFromCode($content, $fileName, Module $module)
    {
        // Get "namespace" either from $fileName or $module fallback
        $namespace = $fileName ? basename($fileName) : $module->getName();

        $usedFQCNs = [];
        $entities = [];

        $tokens = token_get_all("<?php\n" . $content);
        $inTransFn = false;
        $inConcat = false;
        $inNamespace = false;
        $inClass = false; // after `class` but before `{`
        $inUse = false; // pulling in classes from other namespaces
        $inArrayClosedBy = false; // Set to the expected closing token, or false if not in array
        $inSelf = false; // Tracks progress of collecting self::class
        $currentEntity = [];
        $currentNameSpace = []; // The actual namespace for the current class
        $currentClass = []; // Class components
        $previousToken = null;
        $thisToken = null; // used to populate $previousToken on next iteration
        $potentialClassName = null;
        $currentUse = null;
        $currentUseAlias = null;
        foreach ($tokens as $token) {
            // Shuffle last token to $lastToken
            $previousToken = $thisToken;
            $thisToken = $token;
            if (is_array($token)) {
                list($id, $text) = $token;

                // Collect use statements so we can get fully qualified class names
                if ($id === T_USE) {
                    $inUse = true;
                    $currentUse = [];
                    continue;
                }

                if ($inUse) {
                    // PHP 8.0+
                    if (defined('T_NAME_QUALIFIED') && $id === T_NAME_QUALIFIED) {
                        $currentUse[] = $text;
                        $text = explode('\\', $text);
                        $currentUseAlias = end($text);
                        continue;
                    }
                    // PHP 7.4 or an alias declaration
                    if ($id === T_STRING) {
                        // Only add to the FQCN if it's the first string or comes after a namespace separator
                        if (empty($currentUse) || (is_array($previousToken) && $previousToken[0] === T_NS_SEPARATOR)) {
                            $currentUse[] = $text;
                        }
                        // The last part of the use statement is always the alias or the actual class name
                        $currentUseAlias = $text;
                        continue;
                    }
                }

                // Check class
                if ($id === T_NAMESPACE) {
                    $inNamespace = true;
                    $currentClass = [];
                    $currentNameSpace = [];
                    continue;
                }
                if ($inNamespace && ($id === T_STRING || (defined('T_NAME_QUALIFIED') && $id === T_NAME_QUALIFIED))) {
                    $currentClass[] = $text;
                    $currentNameSpace[] = $text;
                    continue;
                }

                // This could be a ClassName::class declaration
                if ($id === T_DOUBLE_COLON && is_array($previousToken) && $previousToken[0] === T_STRING) {
                    $prevString = $previousToken[1];
                    if (!in_array($prevString, ['self', 'static', 'parent'])) {
                        $potentialClassName = $prevString;
                    }
                }

                // Check class and trait
                if ($id === T_CLASS || $id === T_TRAIT) {
                    // Skip if previous token was '::'. E.g. 'Object::class'
                    if (is_array($previousToken) && $previousToken[0] === T_DOUBLE_COLON) {
                        if ($inSelf) {
                            // Handle self::class by allowing logic further down
                            // for __CLASS__/__TRAIT__ to handle an array of class parts
                            $id = $id === T_TRAIT ? T_TRAIT_C : T_CLASS_C;
                            $inSelf = false;
                        } elseif ($potentialClassName) {
                            $id = T_CONSTANT_ENCAPSED_STRING;
                            if (array_key_exists($potentialClassName, $usedFQCNs)) {
                                // Handle classes that we explicitly know about from use statements
                                $text = "'" . $usedFQCNs[$potentialClassName] . "'";
                            } else {
                                // Assume the class is in the current namespace
                                $potentialFQCN = [...$currentNameSpace, $potentialClassName];
                                $text = "'" . implode('\\', $potentialFQCN) . "'";
                            }
                        } else {
                            // Don't handle other ::class definitions. We can't determine which
                            // class was invoked, so parent::class is not possible at this point.
                            continue;
                        }
                    } else {
                        $inClass = true;
                        continue;
                    }
                } elseif (is_array($previousToken) && $previousToken[0] === T_DOUBLE_COLON) {
                    // We had a potential class but it turns out it was probably a method call.
                    $potentialClassName = null;
                }

                if ($inClass && $id === T_STRING) {
                    $currentClass[] = $text;
                    $inClass = false;
                    continue;
                }

                // Suppress tokenisation within array
                if ($inTransFn && !$inArrayClosedBy && $id == T_ARRAY) {
                    $inArrayClosedBy = ')'; // Array will close with this element
                    continue;
                }

                // Start definition
                if ($id == T_STRING && $text == '_t') {
                    $inTransFn = true;
                    continue;
                }

                // Skip rest of processing unless we are in a translation, and not inside a nested array
                if (!$inTransFn || $inArrayClosedBy) {
                    continue;
                }

                // If inside this translation, some elements might be unreachable
                if (in_array($id, [T_VARIABLE, T_STATIC]) ||
                    ($id === T_STRING && in_array($text, ['static', 'parent']))
                ) {
                    // Un-collectable strings such as _t(static::class.'.KEY').
                    // Should be provided by i18nEntityProvider instead
                    $inTransFn = false;
                    $inArrayClosedBy = false;
                    $inConcat = false;
                    $currentEntity = [];
                    continue;
                }

                // Start collecting self::class declarations
                if ($id === T_STRING && $text === 'self') {
                    $inSelf = true;
                    continue;
                }

                // Check text
                if ($id == T_CONSTANT_ENCAPSED_STRING) {
                    // Fixed quoting escapes, and remove leading/trailing quotes
                    if (preg_match('/^\'(?<text>.*)\'$/s', $text ?? '', $matches)) {
                        $text = preg_replace_callback(
                            '/\\\\([\\\\\'])/s', // only \ and '
                            function ($input) {
                                return stripcslashes($input[0] ?? '');
                            },
                            $matches['text'] ?? ''
                        );
                    } elseif (preg_match('/^\"(?<text>.*)\"$/s', $text ?? '', $matches)) {
                        $text = preg_replace_callback(
                            '/\\\\([nrtvf\\\\$"]|[0-7]{1,3}|\x[0-9A-Fa-f]{1,2})/s', // rich replacement
                            function ($input) {
                                return stripcslashes($input[0] ?? '');
                            },
                            $matches['text'] ?? ''
                        );
                    } else {
                        throw new LogicException("Invalid string escape: " . $text);
                    }
                } elseif ($id === T_CLASS_C || $id === T_TRAIT_C) {
                    // Evaluate __CLASS__ . '.KEY' and self::class concatenation
                    $text = implode('\\', $currentClass);
                } else {
                    continue;
                }

                if ($inConcat) {
                    // Parser error
                    if (empty($currentEntity)) {
                        user_error('Error concatenating localisation key', E_USER_WARNING);
                    } else {
                        $currentEntity[count($currentEntity) - 1] .= $text;
                    }
                } else {
                    $currentEntity[] = $text;
                }
                continue; // is_array
            }

            // Test we can close this array
            if ($inTransFn && $inArrayClosedBy && ($token === $inArrayClosedBy)) {
                $inArrayClosedBy = false;
                continue;
            }

            // Check if we can close the namespace or use statement
            if ($token === ';') {
                if ($inNamespace) {
                    $inNamespace = false;
                    continue;
                }
                if ($inUse) {
                    $inUse = false;
                    $usedFQCNs[$currentUseAlias] = implode('\\', $currentUse);
                    $currentUse = null;
                    $currentUseAlias = null;
                    continue;
                }
            }

            // Continue only if in translation and not in array
            if (!$inTransFn || $inArrayClosedBy) {
                continue;
            }

            switch ($token) {
                case '.':
                    $inConcat = true;
                    break;
                case ',':
                    $inConcat = false;
                    break;
                case '[':
                    // Enter array
                    $inArrayClosedBy = ']';
                    break;
                case ')':
                    // finalize definition
                    $inTransFn = false;
                    $inConcat = false;
                    // Ensure key is valid before saving
                    if (!empty($currentEntity[0])) {
                        $key = $currentEntity[0];
                        $default = '';
                        $comment = '';
                        if (!empty($currentEntity[1])) {
                            $default = $currentEntity[1];
                            if (!empty($currentEntity[2])) {
                                $comment = $currentEntity[2];
                            }
                        }
                        // Save in appropriate format
                        if ($default) {
                            $plurals = i18n::parse_plurals($default);
                            // Use array form if either plural or metadata is provided
                            if ($plurals) {
                                $entity = $plurals;
                            } elseif ($comment) {
                                $entity = ['default' => $default];
                            } else {
                                $entity = $default;
                            }
                            if ($comment) {
                                $entity['comment'] = $comment;
                            }
                            $entities[$key] = $entity;
                        } elseif ($this->getWarnOnEmptyDefault()) {
                            trigger_error("Missing localisation default for key " . $currentEntity[0], E_USER_NOTICE);
                        }
                    }
                    $currentEntity = [];
                    $inArrayClosedBy = false;
                    break;
            }
        }

        // Normalise all keys
        foreach ($entities as $key => $entity) {
            unset($entities[$key]);
            $entities[$this->normalizeEntity($key, $namespace)] = $entity;
        }
        ksort($entities);

        return $entities;
    }

    public function getReader()
    {
        if( !$this->reader )
        {
            return new YamlReader();
        }
        parent::getReader();
    }
}