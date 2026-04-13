<?php

namespace App\Services;

class ClientNormalizer
{
    /**
     * Genera una clave normalizada para agrupar aliases del mismo cliente.
     */
    public static function normalizeKey(string $rawName): string
    {
        $name = $rawName;

        // 1. Quitar números al inicio (códigos tipo "526 ", "30349610066081 ")
        $name = preg_replace('/^\d+\s+/', '', $name);

        // 2. Uppercase para comparar
        $name = mb_strtoupper(trim($name));

        // 3. Quitar punto final
        $name = rtrim($name, '.');

        // 4. Normalizar formas jurídicas
        $name = preg_replace('/,?\s*S\.?A\.?U?\.?\s*$/i', '', $name);
        $name = preg_replace('/,?\s*S\.?L\.?U?\.?\s*$/i', '', $name);
        $name = preg_replace('/,?\s*S\.?A\.?\s*$/i', '', $name);
        $name = preg_replace('/,?\s*S\.?L\.?\s*$/i', '', $name);
        $name = preg_replace('/,?\s*S\.?\s*COOP\.?\s*$/i', '', $name);

        // 5. Quitar coma final que quede
        $name = rtrim(trim($name), ',');

        // 6. Normalizar sub-unidades: "AENA. DIRECCIÓN DEL AEROPUERTO DE X" → "AENA"
        // Patron: "NOMBRE. DIRECCIÓN/DEPARTAMENTO/PRESIDENCIA/CONSEJO..."
        if (preg_match('/^(.+?)\.\s*(DIRECCIÓN|DEPARTAMENTO|PRESIDENCIA|CONSEJO|SUBDIRECCIÓN|GERENCIA|SECRETARÍA|JEFATURA|SERVICIO DE|OFICINA|ÁREA|UNIDAD)/u', $name, $m)) {
            $name = trim($m[1]);
        }

        // 7. Normalizar separadores: "ADIF - ALTA VELOCIDAD. PRESIDENCIA" → "ADIF - ALTA VELOCIDAD"
        if (preg_match('/^(.+?)\.\s*(PRESIDENCIA|CONSEJO|DIRECCIÓN|SECRETARÍA)/u', $name, $m)) {
            $name = trim($m[1]);
        }

        // 8. Normalizar "ADIF - PRESIDENCIA" y "ADIF - ALTA VELOCIDAD" → ambos bajo "ADIF"
        // Solo si el separador " - " esta seguido de una palabra genérica
        if (preg_match('/^(.+?)\s+-\s+(PRESIDENCIA|CONSEJO|DIRECCIÓN|SECRETARÍA|ALTA VELOCIDAD)/u', $name, $m)) {
            $name = trim($m[1]);
        }

        // 9. Quitar dobles espacios
        $name = preg_replace('/\s+/', ' ', $name);

        // 10. Trim final
        $name = trim($name);

        return $name;
    }

    /**
     * Genera un nombre limpio para el cliente a partir de la clave normalizada.
     */
    public static function cleanName(string $normalizedKey): string
    {
        // Title case inteligente para español
        $name = mb_strtolower($normalizedKey);

        // Capitalizar primera letra de cada palabra
        $name = mb_convert_case($name, MB_CASE_TITLE);

        // Corregir artículos/preposiciones que no deberían ir en mayúscula
        $lowercaseWords = ['De', 'Del', 'La', 'Las', 'Los', 'El', 'En', 'Y', 'E', 'A', 'Al', 'Para', 'Por', 'Con'];
        foreach ($lowercaseWords as $word) {
            $name = preg_replace('/\b' . $word . '\b/u', mb_strtolower($word), $name);
        }

        // Primera letra siempre mayúscula
        $name = mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);

        return $name;
    }
}
