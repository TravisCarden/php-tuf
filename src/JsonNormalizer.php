<?php

namespace Tuf;

/**
 * Provides normalization to convert an array to a canonical JSON string.
 *
 * @internal
 *   This is not a generic normalizer but intended to be used PHP-TUF metadata
 *   classes.
 */
class JsonNormalizer
{
    /**
     * Encodes an associative array into a string of canonical JSON.
     *
     * @param mixed[]|\stdClass $structure
     *     The associative array of JSON data.
     *
     * @return string
     *     An encoded string of normalized, canonical JSON data.
     *
     * @todo This is a very incomplete implementation of
     *     http://wiki.laptop.org/go/Canonical_JSON.
     *     Consider creating a separate library under php-tuf just for this?
     */
    public static function asNormalizedJson($structure) : string
    {
        self::rKeySort($structure);
        return json_encode($structure);
    }

    /**
     * Decodes a string to data that can be used with ::asNormalizedJson().
     *
     * @param string $json
     *   The JSON string.
     *
     * @return mixed
     *   The decoded data.
     */
    public static function decode(string $json)
    {
        $data = json_decode($json);
        static::replaceStdClassWithArrayObject($data);
        return $data;
    }

    /**
     * Sorts the JSON data array into a canonical order.
     *
     * This method should be used to sort data structures that were passed
     * through \Tuf\JsonNormalizer::replaceStdClassWithArrayObject().
     *
     * @see \Tuf\JsonNormalizer::replaceStdClassWithArrayObject()
     *
     * @param mixed[]|\ArrayObject $structure
     *     The array of JSON to sort, passed by reference.
     *
     * @throws \Exception
     *     Thrown if sorting the array fails.
     * @throws \RuntimeException
     *     Thrown if an object other than \ArrayObject is found.
     *
     * @return void
     */
    private static function rKeySort(&$structure) : void
    {
        if (is_array($structure)) {
            if (!ksort($structure, SORT_STRING)) {
                throw new \Exception("Failure sorting keys. Canonicalization is not possible.");
            }
        } elseif ($structure instanceof \ArrayObject) {
            $structure->ksort();
        } elseif (is_object($structure)) {
            throw new \RuntimeException('\Tuf\JsonNormalizer::rKeySort() is not intended to sort objects except \ArrayObject. Found: ' . get_class($structure));
        }

        foreach ($structure as $key => $value) {
            if (is_array($value) || $value instanceof \ArrayObject) {
                if (is_array($structure) || $structure instanceof \ArrayObject) {
                    self::rKeySort($structure[$key]);
                }
            }
        }
    }

    /**
     * Replaces all instance of \stdClass in the data structure with \ArrayObject.
     *
     * Symfony Validator library's built-in constraints cannot validate
     * \stdClass objects. This method should only be used with the return value
     * of json_decode therefore should not contain any objects except instances
     * of \stdClass.
     *
     * @param array|\stdClass $data
     *   The data to convert. The data structure should contain no objects
     *   except \stdClass instances.
     *
     * @return void
     *
     * @throws \RuntimeException
     *   Thrown if the an object other than \stdClass is found.
     */
    private static function replaceStdClassWithArrayObject(&$data):void
    {
        if ($data instanceof \stdClass) {
            $data = new \ArrayObject($data);
        } elseif (!is_array($data)) {
            throw new \RuntimeException('Cannot convert type: ' . get_class($data));
        }
        foreach ($data as $key => $datum) {
            if (is_array($datum) || is_object($datum)) {
                static::replaceStdClassWithArrayObject($datum);
            }
            $data[$key] = $datum;
        }
    }
}