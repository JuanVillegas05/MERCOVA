<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Roster completo de usuarios (no se filtra por el rango de fechas elegido):
 * la pestaña "Resumen" ya muestra cuántos usuarios hay en total, esta es la
 * lista detallada de referencia para el admin.
 */
class UsersSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly Collection $users) {}

    public function collection(): Collection
    {
        return $this->users;
    }

    public function headings(): array
    {
        return ['ID', 'Nombre', 'Correo', 'Rol', 'Activo', 'Registrado el'];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->role,
            $user->is_active ? 'Sí' : 'No',
            $user->created_at->format('Y-m-d'),
        ];
    }

    public function title(): string
    {
        return 'Usuarios';
    }
}
