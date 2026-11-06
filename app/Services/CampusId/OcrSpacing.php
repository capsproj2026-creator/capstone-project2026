<?php

namespace App\Services\CampusId;

/**
 * Fix missing spaces in OCR text from campus IDs and driver's licenses.
 */
final class OcrSpacing
{
    /**
     * Common Filipino / English given names and particles used to split glued OCR
     * like "ADRIANADAMS" → "Adrian Adams".
     *
     * @var list<string>
     */
    private const NAME_TOKENS = [
        'CHRISTOPHER', 'ALEXANDER', 'MARGARETA', 'MARGARET', 'ELIZABETH', 'FRANCISCO',
        'ANTONIETTE', 'ANTONETTE', 'ANTONIETA', 'ANTONIA', 'ANTONIO', 'ANGELICA',
        'ANGELINA', 'ANGELO', 'ANGELA', 'BERNADETTE', 'CATHERINE', 'KATHERINE',
        'CHRISTIAN', 'CHRISTINE', 'CRISTINE', 'CRISTINA', 'CRISTIAN', 'DOMINIQUE',
        'DOMINIC', 'FERDINAND', 'FLORENTINO', 'GABRIELLE', 'GABRIELA', 'GABRIEL',
        'GEORGINA', 'GEORGIA', 'JONATHAN', 'JOSEPHINE', 'KATHERINE', 'MAGDALENA',
        'MARGARITA', 'MARICRIS', 'MARICEL', 'MARIBEL', 'MARITES', 'MARIVIC',
        'MICHAEL', 'MICHELLE', 'NATHANIEL', 'PATRICIA', 'PHILIPPE', 'PHILIP',
        'PRINCESS', 'RAYMUNDO', 'RAYMOND', 'ROBERTO', 'ROBERT', 'RODRIGO',
        'ROSARIO', 'SALVADOR', 'STEPHANIE', 'STEPHEN', 'THERESA', 'TERESA',
        'VALENTINE', 'VALENTIN', 'VICTORIA', 'VINCENT', 'WILLIAM',
        'ADRIAN', 'ADAMS', 'ALBERT', 'ALFREDO', 'ANDREA', 'ANDREW', 'ANDRES',
        'ANGEL', 'ANITA', 'ANNE', 'ANNA', 'ANN', 'BENJAMIN', 'BERNARD', 'BRIAN',
        'CARLOS', 'CARMEN', 'CECILE', 'CECILIA', 'CHARLES', 'CLARA', 'CLAIRE',
        'DANIEL', 'DANILO', 'DAVID', 'DENNIS', 'DIANA', 'DIEGO', 'EDGAR',
        'EDUARDO', 'EDWIN', 'ELAINE', 'ELENA', 'EMILY', 'EMMANUEL', 'ERIC',
        'ERIKA', 'ERNEST', 'ESTHER', 'EUGENE', 'FELIX', 'FERNANDO', 'FRANCES',
        'FRANCIS', 'GEORGE', 'GERARD', 'GLORIA', 'GRACE', 'GREGORY', 'HAROLD',
        'HELEN', 'HENRY', 'IRENE', 'ISABEL', 'IVAN', 'JACOB', 'JAMES', 'JANE',
        'JANET', 'JASON', 'JASMINE', 'JEFFREY', 'JENNIFER', 'JEREMY', 'JESSICA',
        'JESUS', 'JOAN', 'JOEL', 'JOHN', 'JONAS', 'JORDAN', 'JORGE', 'JOSE',
        'JOSEPH', 'JOSHUA', 'JOYCE', 'JUAN', 'JULIE', 'JULIUS', 'JUSTIN',
        'KAREN', 'KEVIN', 'LAURA', 'LAWRENCE', 'LEONARD', 'LILLIAN', 'LINDA',
        'LIZA', 'LOUIS', 'LOUISE', 'LUCAS', 'LUIS', 'MANUEL', 'MARCO', 'MARCUS',
        'MARGIE', 'MARIA', 'MARIE', 'MARIO', 'MARK', 'MARTIN', 'MARY', 'MATTHEW',
        'MAURICE', 'MELANIE', 'MELISSA', 'MIGUEL', 'MONICA', 'NANCY', 'NATHAN',
        'NELSON', 'NICOLE', 'NOEL', 'NORMAN', 'OLIVER', 'OSCAR', 'PABLO',
        'PAMELA', 'PAOLO', 'PATRICK', 'PAUL', 'PAULA', 'PEDRO', 'PETER',
        'RACHEL', 'RAFAEL', 'RALPH', 'RAMON', 'RANDY', 'RAUL', 'REBECCA',
        'RENATO', 'REYNALDO', 'RICHARD', 'RICKY', 'ROGER', 'ROMAN', 'ROMEO',
        'RONALD', 'ROSE', 'ROSEMARIE', 'RUBY', 'RUTH', 'RYAN', 'SAMUEL',
        'SANDRA', 'SANTIAGO', 'SARAH', 'SERGIO', 'SIMON', 'SOFIA', 'SOPHIA',
        'STEVEN', 'SUSAN', 'TERENCE', 'THOMAS', 'TIMOTHY', 'TONY', 'VICTOR',
        'VINCE', 'VIRGINIA', 'WALTER', 'WILSON',
        'CRUZ', 'FUERTES', 'SANTOS', 'REYES', 'GARCIA', 'LOPEZ', 'TORRES',
        'RAMOS', 'MENDOZA', 'GONZALES', 'GONZALEZ', 'FLORES', 'RIVERA',
        'GOMEZ', 'DIAZ', 'MORALES', 'CASTRO', 'ORTIZ', 'RUIZ', 'JIMENEZ',
        'ALVAREZ', 'ROMERO', 'VARGAS', 'CASTILLO', 'FERNANDEZ', 'AQUINO',
        'DELA', 'DELEON', 'DEL', 'DE', 'LA', 'LAS', 'LOS', 'SAN', 'SANTA',
        'SANTO', 'VAN', 'VON', 'Y',
    ];

    /**
     * Address words peeled from glued OCR (longest first).
     *
     * @var list<string>
     */
    private const ADDRESS_TOKENS = [
        'SUBDIVISION', 'BARANGAY', 'EXTENSION', 'BOULEVARD', 'VILLAGE', 'STREET',
        'AVENUE', 'TEACHERS', 'QUEZON', 'CAMARINES', 'LOURDES', 'PURISIMA',
        'CRISTO', 'NORTE', 'NORTH', 'SOUTH', 'EAST', 'WEST', 'SUR', 'CITY',
        'ZONE', 'PUROK', 'SITIO', 'BLOCK', 'YOUNG', 'NAGA', 'IRIGA', 'NABUA',
        'PILI', 'BATO', 'BUHI', 'BAAO', 'BULA', 'REY', 'BRGY', 'BGY', 'SUBD',
        'BLVD', 'AVE', 'EXT', 'ROAD', 'BLK', 'LOT', 'ST', 'RD',
    ];

    public static function humanizeName(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '') {
            return '';
        }

        // Mixed-case OCR sometimes glues words as AdrianAdams.
        $value = preg_replace('/(?<=[a-z])(?=[A-Z])/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        $parts = preg_split('/\s+/u', $value) ?: [];
        $out = [];
        foreach ($parts as $part) {
            foreach (self::splitGluedNameToken($part) as $piece) {
                $out[] = $piece;
            }
        }

        $joined = trim(implode(' ', $out));
        if ($joined === '') {
            return '';
        }

        $joined = mb_strtolower($joined, 'UTF-8');

        return mb_convert_case($joined, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private static function splitGluedNameToken(string $part): array
    {
        $letters = (string) preg_replace('/[^A-Za-z]/u', '', $part);
        if ($letters === '' || strlen($letters) < 6) {
            return [$part];
        }

        // Keep punctuation wrappers by splitting only the letter run.
        if ($letters !== $part) {
            $split = self::splitGluedNameToken($letters);

            return $split === [$letters] ? [$part] : $split;
        }

        $upper = strtoupper($letters);
        $tokens = self::NAME_TOKENS;
        usort($tokens, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($tokens as $first) {
            if (! str_starts_with($upper, $first) || strlen($upper) < strlen($first) + 3) {
                continue;
            }

            $rest = substr($upper, strlen($first));
            foreach ($tokens as $second) {
                if ($rest === $second || (str_starts_with($rest, $second) && strlen($rest) >= strlen($second))) {
                    $tail = self::splitGluedNameToken($rest);

                    return array_merge([$first], $tail);
                }
            }
        }

        return [$part];
    }

    public static function humanizeAddress(string $line): string
    {
        $text = preg_replace('/(?<=[A-Za-z0-9])\.(?=[A-Za-z0-9])/u', ' ', $line) ?? $line;
        // OCR reads the letter O as zero inside words ("Z0NE", "CRIST0"); house numbers stay digits.
        $text = preg_replace('/(?<=[A-Za-z])0(?=[A-Za-z])|(?<=[A-Za-z]{2})0\b/u', 'O', $text) ?? $text;
        $text = str_ireplace(
            ['LOURDESYOUNG', 'CAMARINESSUR', 'CAMARINESNORTE', 'NAGACITY', 'QUEZONCITY', 'TEACHERSVILLAGEWEST', 'TEACHERSVILLAGE'],
            ['LOURDES YOUNG', 'CAMARINES SUR', 'CAMARINES NORTE', 'NAGA CITY', 'QUEZON CITY', 'TEACHERS VILLAGE WEST', 'TEACHERS VILLAGE'],
            $text
        );

        // House numbers glued to the street: 45MATIPIDST → 45 MATIPIDST
        $text = preg_replace('/(?<=\d)(?=[A-Za-z])/u', ' ', $text) ?? $text;
        $text = preg_replace('/(?<=[A-Za-z])(?=\d)/u', ' ', $text) ?? $text;

        $chunks = preg_split('/(\s*,\s*|\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $built = '';
        foreach ($chunks as $chunk) {
            if ($chunk === '' || preg_match('/^\s*,\s*$/u', $chunk) || preg_match('/^\s+$/u', $chunk)) {
                $built .= $chunk;

                continue;
            }
            $built .= self::peelAddressToken($chunk);
        }

        $text = preg_replace('/\s+/u', ' ', $built) ?? $built;
        $text = preg_replace('/\s*,\s*/u', ', ', $text) ?? $text;

        return trim($text, " \t,");
    }

    private static function peelAddressToken(string $token): string
    {
        if ($token === '' || str_contains($token, ' ') || ! preg_match('/^[A-Za-z0-9]+$/u', $token)) {
            return $token;
        }

        if (strlen($token) < 5 || preg_match('/^\d+$/u', $token)) {
            return $token;
        }

        $upper = strtoupper($token);
        $tokens = self::ADDRESS_TOKENS;
        usort($tokens, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $pieces = [];
        $guard = 0;

        while ($upper !== '' && $guard++ < 12) {
            $matched = false;
            foreach ($tokens as $known) {
                if (strlen($upper) <= strlen($known)) {
                    continue;
                }
                // Keep compass words like WEST intact (they also end in ST).
                if (in_array($upper, $tokens, true)) {
                    break;
                }
                if (str_ends_with($upper, $known) && strlen($upper) - strlen($known) >= 3) {
                    array_unshift($pieces, $known);
                    $upper = substr($upper, 0, -strlen($known));
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                break;
            }
        }

        $guard = 0;
        $prefixPieces = [];
        while ($upper !== '' && $guard++ < 8) {
            $matched = false;
            foreach ($tokens as $known) {
                if (strlen($upper) <= strlen($known)) {
                    continue;
                }
                if (in_array($upper, $tokens, true)) {
                    break;
                }
                if (str_starts_with($upper, $known) && strlen($upper) - strlen($known) >= 3) {
                    $prefixPieces[] = $known;
                    $upper = substr($upper, strlen($known));
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                break;
            }
        }

        if ($upper !== '') {
            $prefixPieces[] = $upper;
        }

        $pieces = array_merge($prefixPieces, $pieces);
        if ($pieces === []) {
            return $token;
        }

        return implode(' ', $pieces);
    }
}
