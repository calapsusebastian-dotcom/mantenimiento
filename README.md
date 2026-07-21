# Mantenimiento — Parque Industrial

Sistema de gestión de mantenimiento (preventivo y correctivo) para equipos y maquinaria de un parque industrial. Construido con Laravel 13, Livewire/Volt y Tailwind CSS.

## Funcionalidades

- **Equipos**: alta, edición e inactivación de maquinaria (código, área, marca, modelo, estado).
- **Planes de mantenimiento preventivo**: frecuencia, checklist y generación automática de órdenes de trabajo cuando vencen (comando programado diario).
- **Órdenes de trabajo**: reporte de fallas, asignación a técnicos, flujo tomar → iniciar → completar, con bitácora de actividad.
- **Calendario**: vista mensual de todo el mantenimiento (órdenes programadas, vencimientos proyectados de planes preventivos según su frecuencia, y pendientes sin programar).
- **Usuarios y roles**: administrador, técnico y operador/inquilino, cada uno con su propio alcance de visibilidad.
- **Dashboard** con indicadores por rol.

## Requisitos

- PHP 8.3+
- Composer
- Node.js + npm
- MySQL

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configura DB_* en .env con tus credenciales de MySQL
php artisan migrate
npm install
npm run build
```

## Desarrollo

```bash
composer run dev
```

Levanta el servidor, la cola, los logs (`pail`) y Vite en modo watch simultáneamente.

## Pruebas

```bash
php artisan test
```

## Roles de prueba

Si usas el seeder (`php artisan db:seed`), se crean cuentas de ejemplo para cada rol con contraseña `password`. En producción, crea tu propio usuario administrador y gestiona el resto desde el módulo de Usuarios.
