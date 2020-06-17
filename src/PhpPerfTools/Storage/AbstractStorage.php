<?php
/*
 * Original code Copyright 2013 Mark Story & Paul Reinheimer
 * Changes Copyright Grzegorz Drozd
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace PhpPerfTools\Storage;

/**
 * Abstract class for all storage drivers.
 *
 * CAUTION: please use interface as a typehint!
 */
class AbstractStorage
{
    /**
     * Try to get date from Y-m-d H:i:s or from timestamp
     *
     * @param string|int $date
     * @param string $direction
     * @return \DateTime
     */
    protected function getDateTimeFromString($date, $direction = 'start')
    {
        try {
            $datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
            if (!empty($datetime) && $datetime instanceof \DateTime) {
                return $datetime;
            }
        } catch (\Exception $e) {
        }

        // try without time
        try {
            $datetime = \DateTime::createFromFormat('Y-m-d', $date);
            if (!empty($datetime) && $datetime instanceof \DateTime) {
                if ($direction === 'start') {
                    $datetime->setTime(0, 0, 0);
                } elseif ($direction === 'end') {
                    $datetime->setTime(23, 59, 59);
                }

                return $datetime;
            }
        } catch (\Exception $e) {
        }

        // try using timestamp
        try {
            $datetime = \DateTime::createFromFormat('U', $date);
            if (!empty($datetime) && $datetime instanceof \DateTime) {
                return $datetime;
            }
        } catch (\Exception $e) {
        }

        throw new \InvalidArgumentException('Unable to parse date');
    }
}
