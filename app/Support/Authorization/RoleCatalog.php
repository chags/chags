<?php

namespace App\Support\Authorization;

final class RoleCatalog
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'candidato' => 'Candidato',
            'colaborador' => 'Colaborador',
            'gestor' => 'Gestor',
            'rh-analista' => 'Analista de RH',
            'rh-gestor' => 'Gestor de RH',
            'dp-analista' => 'Analista de DP',
            'dp-gestor' => 'Gestor de DP',
            'administrador' => 'Administrador',
            'super-admin' => 'Super Admin',
        ];
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::labels());
    }
}
