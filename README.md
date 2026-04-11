# Granja Backend

API backend del micro-ERP avicola. Este proyecto centraliza la logica de negocio de la granja: autenticacion, lotes, produccion diaria, inventario, ventas, caja, usuarios, recordatorios y pedidos.

Su responsabilidad principal es exponer una API protegida con Laravel Sanctum para que la app movil pueda operar con datos consistentes y reglas del negocio en un solo lugar.

## Que resuelve este proyecto

Este backend modela el flujo operativo de una granja de huevos:

- Control de lotes de aves y su cantidad viva actual.
- Registro de recoleccion diaria por lote.
- Clasificacion de huevos por tamano y control de mermas.
- Inventario disponible para venta.
- Registro de clientes, ventas y cuentas por cobrar.
- Apertura y cierre de caja, ingresos, egresos y anulaciones.
- Recordatorios compartidos para tareas operativas.
- Pedidos programados de clientes y su conversion a venta.

## Stack tecnico

- PHP 8.2
- Laravel 12
- Laravel Sanctum para autenticacion por token
- Eloquent ORM
- Vite para assets del proyecto Laravel

## Modulos principales

### 1. Autenticacion

- Login con correo y contrasena.
- Emision y revocacion de tokens.
- Endpoint para perfil autenticado.
- Recuperacion y reseteo de contrasena.

### 2. Gestion de lotes

- CRUD de lotes.
- Registro de mortalidad.
- Ajustes manuales de cantidad viva.

### 3. Productos

- Catalogo de tamanos de huevo.
- Configuracion de precios por tamano.

### 4. Produccion e inventario

- Recolecciones por lote.
- Clasificacion diaria de produccion.
- Huevos remanentes en mesa.
- Balance pendiente entre recoleccion y clasificacion.
- Inventario actual por tamano.

Regla importante:

- 1 carton = 30 huevos.

### 5. Clientes y ventas

- CRUD de clientes.
- Registro de ventas con items.
- Rebaja automatica de inventario.
- Manejo de ventas pendientes, parciales o pagadas.

### 6. Caja

- Apertura de caja.
- Registro de ingresos y egresos.
- Cierre de caja.
- Historial de sesiones.
- Anulacion de transacciones por administrador.
- Rubros de egresos.

### 7. Usuarios

- CRUD de usuarios.
- Cambio de contrasena.
- Roles de acceso.

### 8. Recordatorios

- Creacion de tareas programadas.
- Soporte para repeticion unica, mensual o por intervalo.
- Historial de tareas completadas.

### 9. Pedidos

- Registro de pedidos pendientes o pospuestos.
- Control de fecha de entrega.
- Abonos iniciales.
- Conversion opcional a venta al momento de entregar.

## Estructura importante

```text
granja-backend/
├── app/
│   ├── Http/Controllers/
│   │   ├── Auth/
│   │   ├── Api/
│   │   └── ...
│   └── Models/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── public/
├── resources/
├── routes/
│   ├── api.php
│   └── web.php
└── tests/
```

### Donde esta la logica

- `routes/api.php`: mapa principal de endpoints.
- `app/Http/Controllers/Api`: controladores de modulos funcionales.
- `app/Http/Controllers/Auth`: autenticacion y recuperacion de contrasena.
- `app/Models`: entidades del dominio.
- `database/migrations`: estructura de base de datos.
- `database/seeders/DatabaseSeeder.php`: semilla inicial de usuario admin.

## Flujo general con la app movil

1. La app Flutter inicia sesion usando `/auth/login`.
2. El backend responde con un token Sanctum.
3. La app usa ese token para consumir el resto de endpoints.
4. Cada modulo de la app consulta o registra datos contra esta API.

## Endpoints funcionales por dominio

Los endpoints mas importantes estan agrupados de esta forma:

- `auth/*`: login, logout, perfil, recuperacion de contrasena.
- `batches/*`: lotes, mortalidad y ajustes.
- `product-sizes/*`: tamanos y precios.
- `daily-collections/*`: recolecta por lote.
- `productions/*`: clasificacion, resumen y balance pendiente.
- `table-eggs/*`: remanentes en mesa.
- `inventory/*`: stock disponible y ajustes.
- `customers/*`: clientes.
- `sales/*`: ventas.
- `cash/*`: caja activa, historial y transacciones.
- `expense-categories/*`: rubros de egreso.
- `users/*`: usuarios.
- `reminders/*`: recordatorios.
- `orders/*`: pedidos e historial.

## Requisitos para levantarlo

- PHP 8.2+
- Composer
- Node.js y npm
- Base de datos compatible con Laravel

## Instalacion local

```bash
composer install
npm install
```

Este proyecto espera un archivo `.env` con configuracion Laravel estandar y credenciales de base de datos. Si otra persona va a trabajar en el sistema, necesita una copia valida del `.env` del equipo o crear uno equivalente segun el entorno.

## Comandos utiles

```bash
php artisan serve
php artisan migrate
php artisan db:seed
php artisan test
npm run dev
```

Tambien existe un comando de arranque combinado definido en Composer:

```bash
composer run dev
```

Y un setup rapido:

```bash
composer run setup
```

## Datos semilla

El seeder actual crea un usuario administrador inicial para pruebas locales desde `database/seeders/DatabaseSeeder.php`.

## Estado actual del proyecto

El backend ya no es un scaffold vacio de Laravel. Tiene desarrollo funcional real y la mayor parte del conocimiento del sistema vive en el codigo, especialmente en:

- rutas de API
- controladores
- modelos
- migraciones

Por eso, si alguien nuevo entra al proyecto, la forma mas rapida de entenderlo es revisar primero:

1. `routes/api.php`
2. `app/Http/Controllers/Api`
3. `app/Models`
4. `database/migrations`

## Notas para nuevos colaboradores

- La API esta pensada principalmente para la app Flutter del repositorio `granja-movil`.
- Varias reglas del negocio afectan inventario y caja; conviene tocar esos modulos con cuidado porque estan interconectados.
- Hay logica financiera y de inventario que cruza ventas, pedidos, produccion y caja.
- Antes de cambiar comportamiento, conviene revisar bien controladores como `ProductionController`, `SaleController`, `CashBoxController` y `OrderController`.
