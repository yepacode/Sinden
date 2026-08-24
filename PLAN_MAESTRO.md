# PLAN MAESTRO - PROYECTO SINDEN

## INSTRUCCIONES DE USO DE ESTE DOCUMENTO

> **IMPORTANTE - LEE ESTO PRIMERO**

Este documento es la **FUENTE DE VERDAD** del proyecto. Contiene toda la informacion sobre la base de datos, modelos, arquitectura y modulos a desarrollar.

### Como usar este documento:

1. **Antes de iniciar CUALQUIER modulo:**
   - Claude DEBE leer este documento completo
   - Revisar especialmente la seccion "DISENO DE BASE DE DATOS"
   - Entender que tablas y relaciones estan disponibles

2. **Durante el desarrollo:**
   - Seguir la arquitectura definida (patrones, principios SOLID)
   - Respetar las relaciones entre modelos
   - No crear tablas nuevas sin antes consultar

3. **Despues de completar un modulo:**
   - Si se agregaron campos nuevos a tablas -> **ACTUALIZAR** este documento
   - Si se crearon migraciones adicionales -> **DOCUMENTAR** aqui
   - Si se modifico alguna relacion -> **REFLEJAR** el cambio
   - Marcar el modulo como completado en la seccion correspondiente
   - **ACTUALIZAR NAVEGACION:** Agregar el nuevo modulo en el menu lateral con directivas `@role()` segun corresponda

4. **Reglas de oro:**
   - Este .md se mantiene actualizado en TODO momento
   - Cualquier desviacion del plan SE DOCUMENTA aqui
   - Es la unica verdad - no confiar en memoria ni conversaciones anteriores
   - Al inicio de cada sesion, leer las secciones relevantes
   - **NUNCA HACER COMMITS** - Solo el usuario hace commits en Git

---

## DECISIONES ARQUITECTONICAS

| Decision | Valor | Motivo |
|----------|-------|--------|
| Diseno UI | Inter + tema verde (#4A7C59), sin dark mode | Mantener diseño actual |
| Real-time | Polling AJAX (cada 30s) | Sin dependencias extra, funciona en cualquier hosting |
| Timestamps | `created_at`/`updated_at` de Laravel | Evitar configuracion custom en cada modelo |
| Nombres tablas/campos | Espanol (excepto timestamps) | Convencion del proyecto |
| Base de datos | MySQL/MariaDB | XAMPP local, hosting compartido |
| Archivos | `public/uploads/` | Sin symlinks, acceso directo |
| Zona horaria | America/Bogota | Colombia |
| Moneda | COP (Pesos colombianos) | Formato: $1.500.000 |

---

## REGLAS CRITICAS DEL PROYECTO

### ALMACENAMIENTO DE ARCHIVOS
```
NUNCA usar `storage/` ni `php artisan storage:link`
TODOS los archivos van en `public/`
```

**Estructura de carpetas:**
```
public/
└── uploads/
    ├── bosquejos-matriz/{id}/          (plantillas de bosquejos)
    ├── ordenes/{id}/
    │   ├── bosquejos/                  (bosquejos de la orden)
    │   ├── fotos/                      (fotos de avance/entrega)
    │   └── firma/                      (firma del cliente)
    ├── profile-photos/                 (fotos de perfil)
    └── empresa/                        (logo empresa)
```

**Razon:** El servidor de produccion NO soporta enlaces simbolicos (symlinks)
- Usar `public_path()` en lugar de `storage_path()`
- URLs directas: `/uploads/...` (sin `/storage/`)

### ALERTAS Y NOTIFICACIONES
```
NUNCA usar `alert()`, `confirm()` o `prompt()` nativos de JavaScript
SIEMPRE usar SweetAlert2
```

**Ejemplos de uso:**
```javascript
// Confirmacion de eliminacion
Swal.fire({
    title: 'Eliminar registro?',
    text: 'Esta accion no se puede deshacer',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Si, eliminar',
    cancelButtonText: 'Cancelar'
}).then((result) => {
    if (result.isConfirmed) {
        // Ejecutar eliminacion
    }
});

// Toast de exito
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: 'Guardado correctamente',
    showConfirmButton: false,
    timer: 3000
});

// Error
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: 'No se pudo completar la operacion'
});
```

### FORMATO DE FECHAS
- **Base de datos:** `YYYY-MM-DD` o `YYYY-MM-DD HH:MM:SS`
- **Visualizacion:** `DD/MM/YYYY` (formato colombiano)
- **Visualizacion larga:** `01 DE ENERO 2026`
- Usar Carbon para manipulacion de fechas
- **Zona horaria:** America/Bogota

### FORMATO DE MONEDA
- **Base de datos:** `DECIMAL(12,2)`
- **Visualizacion:** `$1,500,000` (nomenclatura US en todo el sistema: miles con coma, decimales con punto)
- Usar `'$' . number_format($valor, 0, '.', ',')` para mostrar valores enteros
- Usar `'$' . number_format($valor, 2, '.', ',')` para mostrar con decimales

### COMPONENTES UI REUTILIZABLES
```
SIEMPRE usar componentes de `resources/views/components/sinden/`
Si un componente no existe, CREARLO antes de usarlo
NUNCA crear HTML repetitivo sin usar componentes
```

**Componentes Disponibles:**

| Componente | Uso | Ejemplo |
|------------|-----|---------|
| `<x-sinden.page-header>` | Encabezados de pagina | `<x-sinden.page-header title="Titulo" description="Desc">` |
| `<x-sinden.stat-card>` | Cards de resumen (iconos 64px) | `<x-sinden.stat-card icon="bi bi-icon" value="10" title="Label" color="primary">` |
| `<x-sinden.badge>` | Badges de estado | `<x-sinden.badge variant="success">Activo</x-sinden.badge>` |
| `<x-sinden.button>` | Botones estilizados | `<x-sinden.button variant="primary" icon="bi bi-plus">Texto</x-sinden.button>` |
| `<x-sinden.modal>` | Modales Bootstrap | `<x-sinden.modal id="miModal" title="Titulo">Contenido</x-sinden.modal>` |
| `<x-sinden.progress-bar>` | Barras de progreso | `<x-sinden.progress-bar :percentage="75" color="success">` |
| `<x-sinden.alert>` | Alertas | `<x-sinden.alert type="success" message="Mensaje">` |
| `<x-sinden.form-group>` | Campos de formulario | `<x-sinden.form-group label="Nombre" name="nombre" type="text">` |
| `<x-sinden.data-table>` | Tabla de datos | Ver componente para props |
| `<x-sinden.table-row>` | Fila de tabla | Ver componente para props |
| `<x-sinden.table-cell>` | Celda de tabla | Ver componente para props |

**Colores disponibles:** `primary` (verde), `success`, `warning`, `danger`, `info`, `secondary`

**Estructura de Vista Estandar:**
```blade
@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    {{-- 1. Page Header --}}
    <x-sinden.page-header title="Titulo" description="Descripcion">
        <x-slot name="actions">
            <x-sinden.button variant="primary">Accion</x-sinden.button>
        </x-slot>
    </x-sinden.page-header>

    {{-- 2. Summary Cards --}}
    <div class="summary-cards">
        <x-sinden.stat-card icon="bi bi-icon" :value="$count" title="Label" color="primary" />
    </div>

    {{-- 3. Filtros --}}
    <div class="filters-row">...</div>

    {{-- 4. Contenido Principal --}}
    ...
</div>
@endsection
```

### PATRON ESTANDAR DE DATATABLES

Todos los modulos que usen DataTables DEBEN seguir esta estructura exacta para mantener consistencia visual en toda la aplicacion.

**Estructura HTML de la card con DataTable:**
```blade
{{-- DataTable --}}
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-semibold text-dark">
                <i class="bi bi-list-ul me-2 text-primary"></i>Listado de [Entidad]
            </h6>
            <span class="badge bg-light text-muted border" id="totalRegistros"></span>
        </div>
    </div>
    <div class="card-body px-4 pb-4 pt-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 sinden-datatable" id="[entidad]Table" style="width:100%">
                <thead>
                    <tr>
                        <th>...</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
```

**Reglas clave:**
- Card: `border-0 shadow-sm mt-4`
- Card header: `bg-white border-0 px-4 pt-4 pb-0` con titulo h6 + badge de conteo
- Card body: `px-4 pb-4 pt-3` (NUNCA `p-0`)
- Table: clase `sinden-datatable` obligatoria (activa estilos en `gva-components.css`)
- NO usar `table-light` en thead (los estilos CSS de `.sinden-datatable thead th` ya aplican fondo gris, uppercase, letter-spacing)

**Inicializacion JavaScript estandar:**
```javascript
$(function() {
    var table = $('#[entidad]Table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("prefijo.entidad.index") }}',
        columns: [
            { data: 'id', name: 'id', width: '55px', className: 'text-center text-muted' },
            { data: 'nombre', name: 'nombre', className: 'fw-semibold' },
            // ... mas columnas ...
            { data: 'estado', name: 'activo', orderable: true, searchable: false, className: 'text-center' },
            { data: 'created_at', name: 'created_at', width: '100px', className: 'text-center' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false, className: 'text-end', width: '120px' }
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50], [10, 15, 25, 50]],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        drawCallback: function(settings) {
            var total = settings._iRecordsTotal || 0;
            $('#totalRegistros').text(total + ' registro' + (total !== 1 ? 's' : ''));
        }
    });
});
```

**Columnas de acciones en el Controller (PHP):**
```php
->addColumn('acciones', function ($entidad) {
    $viewUrl = route('prefijo.entidad.show', $entidad);
    $editUrl = route('prefijo.entidad.edit', $entidad);

    return '<div class="action-buttons justify-content-end">'
        . '<a href="' . $viewUrl . '" class="action-btn view" title="Ver" data-tooltip="Ver"><i class="bi bi-eye"></i></a>'
        . '<a href="' . $editUrl . '" class="action-btn edit" title="Editar" data-tooltip="Editar"><i class="bi bi-pencil"></i></a>'
        // ... mas botones de accion ...
        . '</div>';
})
```

**Clases de botones de accion:** Usar siempre `action-btn` (definido en `gva-components.css`):
- `action-btn view` → hover verde (ver detalle)
- `action-btn edit` → hover azul (editar)
- `action-btn delete` → hover rojo (eliminar/desactivar)
- `action-btn` sin variante → hover gris neutro (toggle, etc.)
- NUNCA usar `btn btn-sm btn-outline-*` para acciones en DataTables

**Estilos CSS ya configurados en `gva-components.css`:**
- `.sinden-datatable thead th` → fondo gris-50, uppercase, letter-spacing, font-size 0.75rem
- `.sinden-datatable tbody td` → padding 0.75rem, font-size 0.8125rem, border gris-100
- `.sinden-datatable tbody tr:hover` → fondo primary-50 (azul suave)
- `.sinden-datatable .action-btn` → 30x30px, border-radius 6px
- Pagination, search input, length select → todos estilizados con variables CSS de sinden

---

## ROLES Y PERMISOS

### Roles del Sistema

| Rol | Descripcion | Redirect post-login | Jerarquia |
|-----|-------------|---------------------|-----------|
| **Administrador** | Control total del sistema | `/admin/configuracion` | 1 (mayor) |
| **Recepcion** | Crea ordenes, gestiona clientes, gestiona entregas | `/recepcion/panel` | 2 |
| **Contabilidad** | Gestiona pagos, aprueba abonos | `/contabilidad/panel` | 3 |
| **Operario** | Ejecuta trabajo sobre piezas, reporta avance | `/operario/panel` | 4 (menor) |

**Jerarquia de forzar cierre:** Administrador > Recepcion > Contabilidad > Operario
- Un usuario de mayor jerarquia puede forzar el cierre de orden bloqueada por uno de menor jerarquia
- Si usuario tiene multiples roles, redirigir al de mayor jerarquia

### Permisos por Rol

| Permiso | Admin | Recepcion | Contabilidad | Operario |
|---------|:-----:|:---------:|:------------:|:--------:|
| ver_dashboard | X | X | X | X |
| gestionar_usuarios | X | | | |
| gestionar_configuracion | X | | | |
| gestionar_tabla_precios | X | | | |
| ver_clientes | X | X | | |
| crear_clientes | X | X | | |
| editar_clientes | X | X | | |
| ver_catalogo_items | X | X | X | |
| crear_catalogo_items | X | X | | |
| editar_catalogo_items | X | X | | |
| ver_bosquejos_matriz | X | X | | |
| gestionar_bosquejos_matriz | X | X | | |
| ver_ordenes | X | X | X | X |
| crear_ordenes | X | X | | |
| editar_ordenes | X | X | | |
| anular_ordenes | X | X | | |
| generar_ordenes | X | X | | |
| gestionar_entregas | X | X | | |
| ver_pagos | X | X | X | |
| crear_pagos | X | X | X | |
| aprobar_pagos | X | | X | |
| ver_actividades_globales | X | X | | |
| ver_actividades_propias | X | X | X | X |
| trabajar_piezas | | | | X |
| transferir_piezas | | | | X |
| complementar_ordenes | | | | X |
| consultar_precios | X | X | | |
| gestionar_garantias | X | X | | |
| ver_notificaciones | X | X | X | X |

---

## DISENO DE BASE DE DATOS

### Base Existente (ya migrada)
El proyecto ya tiene las siguientes tablas:
- `users` (con campo `profile_photo`)
- `roles`
- `permissions`
- `model_has_roles`
- `model_has_permissions`
- `role_has_permissions`
- `password_resets`
- `failed_jobs`
- `personal_access_tokens`
- `migrations`

### Nuevas Tablas a Crear

#### 1. `clientes`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `nombre` | varchar(255) | requerido | Nombre completo |
| `cedula` | varchar(20) | nullable | Cedula o NIT (agregado retroalimentacion 2026-02-21) |
| `direccion` | text | nullable | |
| `correo` | varchar(255) | nullable, email | |
| `celular_1` | varchar(20) | nullable | Principal (WhatsApp) |
| `celular_2` | varchar(20) | nullable | Secundario |
| `activo` | boolean | default:true | Borrado logico |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

#### 2. `catalogo_items`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `codigo` | varchar(50) | requerido, unico | Ej: "SER 1004" |
| `descripcion` | text | requerido | |
| `precio_unitario` | decimal(12,2) | requerido, min:0 | Sin IVA |
| `porcentaje_iva` | decimal(5,2) | requerido, default:19.00 | |
| `categoria` | varchar(50) | requerido | 'servicio', 'material', 'producto_terminado' |
| `activo` | boolean | default:true | Borrado logico |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

#### 3. `grupos_bosquejos`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `nombre` | varchar(255) | requerido | Ej: "Puertas Industriales" |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

#### 4. `plantillas_bosquejos`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `grupo_bosquejo_id` | unsignedBigInteger | nullable, FK | |
| `nombre` | varchar(255) | requerido | |
| `ruta_archivo` | varchar(500) | requerido | |
| `ruta_miniatura` | varchar(500) | nullable | |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

#### 5. `configuracion_sistema`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `clave` | varchar(100) | requerido, unico | |
| `valor` | text | nullable | |
| `tipo` | varchar(20) | default:'texto' | texto, entero, decimal, json, booleano |
| `descripcion` | varchar(255) | nullable | |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

**Claves de configuracion:**
| Clave | Tipo | Default |
|-------|------|---------|
| `nombre_empresa` | texto | "SINDEN S.A.S." |
| `logo_empresa` | texto | null |
| `direccion_empresa` | texto | "" |
| `telefono_empresa` | texto | "" |
| `nit_empresa` | texto | "" |
| `numeros_nequi` | json | ["3132292789","3177138139"] |
| `porcentaje_iva_defecto` | decimal | 19.00 |
| `timeout_inactividad_operario` | entero | 10 (minutos) |
| `timeout_autoguardado_recepcion` | entero | 5 (minutos) |
| `timeout_forzar_cierre` | entero | 60 (segundos) |
| `dias_expiracion_borradores` | entero | 30 |
| `dias_borradores_recientes` | entero | 7 |
| `usuario_notificar_baja_porcentaje` | entero | null (FK users) |
| `materiales_disponibles` | json | ["HR","CR","INOX","Galvanizado","Aluminio Liso","Alfajor","Alfajor HR","Acero 430"] |
| `calibres_disponibles` | json | (ver tabla de calibres abajo) |

**Calibres disponibles:**
| Calibre | Espesor (mm) |
|---------|-------------|
| #22 | 0.76 |
| #20 | 0.91 |
| #18 | 1.21 |
| #16 | 1.52 |
| #14 | 1.90 |
| #12 | 2.66 |
| 1/8" | 3.18 |
| 4mm | 4.00 |
| 3/16" | 4.76 |
| 1/4" | 6.35 |
| 5/16" | 7.94 |
| 3/8" | 9.53 |
| 1/2" | 12.70 |

#### 6. `ordenes`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `numero_orden` | varchar(20) | unico, nullable | Formato "#0001". Se asigna al GENERAR |
| `cliente_id` | unsignedBigInteger | FK clientes | |
| `creado_por` | unsignedBigInteger | FK users | Recepcionista |
| `estado_trabajo` | varchar(50) | requerido, default:'borrador' | Ver estados abajo |
| `estado_entrega` | varchar(50) | nullable | Ver estados abajo |
| `estado_pago` | varchar(50) | nullable | Ver estados abajo |
| `fecha_entrega` | date | nullable | |
| `hora_entrega` | time | nullable | |
| `ruta_firma_cliente` | varchar(500) | nullable | Imagen PNG |
| `notas` | text | nullable | |
| `subtotal` | decimal(12,2) | default:0 | Calculado |
| `monto_iva` | decimal(12,2) | default:0 | Calculado |
| `total` | decimal(12,2) | default:0 | subtotal + monto_iva |
| `total_pagado` | decimal(12,2) | default:0 | Suma pagos aprobados |
| `saldo` | decimal(12,2) | default:0 | total - total_pagado |
| `clonada_de_id` | unsignedBigInteger | nullable, FK ordenes | |
| `bloqueada_por` | unsignedBigInteger | nullable, FK users | |
| `bloqueada_en` | timestamp | nullable | |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

**Valores de `estado_trabajo`:**
| Valor | Label | Color | Cuando |
|-------|-------|-------|--------|
| `borrador` | BORRADOR | gris | Al guardar |
| `generada` | GENERADA | azul | Al generar (sin piezas -> ejecutada) |
| `en_ejecucion` | EN EJECUCION | amarillo | Alguna pieza > 0% y no todas al 100% |
| `ejecutada_parcialmente` | EJECUTADA PARCIALMENTE | naranja | Al menos 1 pieza al 100% pero no todas |
| `ejecutada` | EJECUTADA | verde | Todas al 100% o sin piezas |
| `anulada` | ANULADA | rojo | Cancelada manualmente |

**Valores de `estado_entrega`:**
| Valor | Label | Color | Cuando |
|-------|-------|-------|--------|
| `null` | (no mostrar) | - | Ninguna entregada |
| `entregada_parcialmente` | ENTREGADA PARCIALMENTE | cyan | Al menos 1 entregada, no todas |
| `entregada` | ENTREGADA | verde oscuro | Todas entregadas o sin piezas |

**Valores de `estado_pago`:**
| Valor | Label | Color | Cuando |
|-------|-------|-------|--------|
| `null` | (no mostrar) | - | Solo borradores |
| `saldo_pendiente` | SALDO PENDIENTE | rojo | saldo > 0 |
| `pagado` | PAGADO | verde | saldo <= 0 |

#### 7. `orden_items`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `orden_id` | unsignedBigInteger | FK ordenes | |
| `catalogo_item_id` | unsignedBigInteger | nullable, FK catalogo_items | Null si manual |
| `codigo` | varchar(50) | nullable | |
| `descripcion` | text | requerido | |
| `cantidad` | decimal(10,2) | requerido, min:0.01 | |
| `precio_unitario` | decimal(12,2) | requerido, min:0 | |
| `porcentaje_iva` | decimal(5,2) | default:19.00 | |
| `categoria` | varchar(50) | requerido | Heredada del catalogo |
| `subtotal` | decimal(12,2) | calculado | cantidad x precio_unitario |
| `monto_iva` | decimal(12,2) | calculado | subtotal x (porcentaje_iva/100) |
| `total` | decimal(12,2) | calculado | subtotal + monto_iva |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

#### 8. `orden_bosquejos`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `orden_id` | unsignedBigInteger | FK ordenes | |
| `plantilla_bosquejo_id` | unsignedBigInteger | nullable, FK plantillas_bosquejos | |
| `tipo_origen` | varchar(50) | requerido | archivo_local, plantilla, grupo_plantillas, camara, dibujo_tablet |
| `nombre` | varchar(255) | requerido | |
| `ruta_archivo` | varchar(500) | requerido | |
| `ruta_miniatura` | varchar(500) | nullable | |
| `orden_visual` | integer | default:0 | |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

#### 9. `orden_piezas`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `orden_id` | unsignedBigInteger | FK ordenes | |
| `orden_bosquejo_id` | unsignedBigInteger | nullable, FK orden_bosquejos | |
| `nombre` | varchar(255) | requerido | "Pieza A", "Pieza B" |
| `nombre_automatico` | varchar(255) | nullable | |
| `cantidad` | integer | requerido, min:1 | Ej: 100 unidades |
| `material` | varchar(100) | nullable | HR, CR, INOX, etc. |
| `calibre` | varchar(50) | nullable | #22, C3mm, etc. |
| `especificacion` | text | nullable | "100 - PIEZA A - C3mm - HR" |
| `notas` | text | nullable | Notas de la pieza (agregado retroalimentacion 2026-02-21) |
| `porcentaje_avance` | decimal(5,2) | default:0, min:0, max:100 | |
| `operario_actual_id` | unsignedBigInteger | nullable, FK users | NULL = pool general |
| `estado` | varchar(50) | default:'pendiente' | pendiente, en_proceso, completada, entregada |
| `entregada` | boolean | default:false | |
| `entregada_en` | timestamp | nullable | |
| `entregada_por` | unsignedBigInteger | nullable, FK users | |
| `orden_visual` | integer | default:0 | |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

#### 10. `asignaciones_piezas`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `orden_pieza_id` | unsignedBigInteger | FK orden_piezas | |
| `orden_id` | unsignedBigInteger | FK ordenes | Denormalizado |
| `asignado_desde_id` | unsignedBigInteger | nullable, FK users | Operario anterior |
| `asignado_a_id` | unsignedBigInteger | FK users | Operario que recibe |
| `asignado_por_id` | unsignedBigInteger | FK users | Quien asigno |
| `tipo_asignacion` | varchar(50) | requerido | inicial, transferencia, complemento, reasignacion |
| `porcentaje_al_asignar` | decimal(5,2) | requerido | % al momento de asignar |
| `notas` | text | nullable | |
| `activa` | boolean | default:true | Solo 1 activa por pieza |
| `created_at` | timestamp | auto | |

#### 11. `historial_avances`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `orden_pieza_id` | unsignedBigInteger | FK orden_piezas | |
| `operario_id` | unsignedBigInteger | FK users | |
| `porcentaje_desde` | decimal(5,2) | requerido | % al recibir |
| `porcentaje_hasta` | decimal(5,2) | requerido | % al soltar |
| `contribucion` | decimal(5,2) | calculado | hasta - desde |
| `notas` | text | nullable | |
| `asignado_en` | timestamp | requerido | Cuando recibio |
| `completado_en` | timestamp | nullable | Cuando termino |
| `created_at` | timestamp | auto | |

#### 12. `pagos`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `orden_id` | unsignedBigInteger | FK ordenes | |
| `monto` | decimal(12,2) | requerido, min:0.01 | |
| `metodo_pago` | varchar(50) | requerido | efectivo, nequi, transferencia, tarjeta, otro |
| `referencia_pago` | varchar(255) | nullable | Nro referencia |
| `registrado_por` | unsignedBigInteger | FK users | |
| `aprobado_por` | unsignedBigInteger | nullable, FK users | |
| `aprobado` | boolean | default:false | |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

**Reglas de pagos:**
- Abonos de Recepcion: `aprobado = false` (necesitan aprobacion de Contabilidad)
- Abonos de Contabilidad o Admin: `aprobado = true` (auto-aprobados)
- Al aprobar: recalcular `ordenes.total_pagado` y `ordenes.saldo`

#### 13. `orden_fotos`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `orden_id` | unsignedBigInteger | FK ordenes | |
| `orden_pieza_id` | unsignedBigInteger | nullable, FK orden_piezas | |
| `tipo_foto` | varchar(50) | requerido | avance, entrega, evidencia |
| `ruta_archivo` | varchar(500) | requerido | |
| `ruta_miniatura` | varchar(500) | nullable | |
| `subido_por` | unsignedBigInteger | FK users | |
| `aprobada` | boolean | default:false | |
| `aprobada_por` | unsignedBigInteger | nullable, FK users | |
| `created_at` | timestamp | auto | |

#### 14. `orden_comentarios`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `orden_id` | unsignedBigInteger | FK ordenes | |
| `usuario_id` | unsignedBigInteger | FK users | |
| `contenido` | text | requerido | |
| `created_at` | timestamp | auto | |

**Regla:** NO se pueden editar ni eliminar comentarios. Son log de comunicacion.

#### 15. `registro_actividades`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `usuario_id` | unsignedBigInteger | FK users | |
| `orden_id` | unsignedBigInteger | nullable, FK ordenes | |
| `accion` | varchar(100) | requerido | Clave de la accion |
| `descripcion` | text | requerido | Descripcion legible |
| `datos_extra` | json | nullable | JSON adicional |
| `created_at` | timestamp | auto | |

**PROTECCION:** Modelo SIN metodos update/delete. No definir rutas de edicion/eliminacion. No hay botones editar/eliminar en UI.

**Acciones a registrar:**
| Accion | Cuando |
|--------|--------|
| `orden.creada` | Se guarda o genera |
| `orden.actualizada` | Se edita |
| `orden.estado_cambiado` | Cambia cualquier estado |
| `orden.anulada` | Se anula |
| `orden.clonada` | Se copia |
| `pieza.avance_actualizado` | Operario actualiza % |
| `pieza.avance_disminuido` | Operario BAJA % |
| `pieza.transferida` | Transferencia entre operarios |
| `pieza.liberada_a_pool` | Dejada en cola general |
| `pieza.tomada_de_pool` | Tomada del pool |
| `pieza.reasignada` | Reasignacion manual |
| `pieza.completada` | Llega a 100% |
| `pieza.entregada` | Entregada al cliente |
| `pago.registrado` | Se registra abono |
| `pago.aprobado` | Contabilidad aprueba |
| `foto.subida` | Se sube foto |
| `cliente.creado` | Se crea cliente |
| `cliente.actualizado` | Se edita cliente |
| `usuario.inicio_sesion` | Login |
| `garantia.registrada` | Devolucion por garantia |
| `sistema.borradores_eliminados` | Limpieza automatica |

#### 16. `devoluciones_garantia`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `orden_id` | unsignedBigInteger | FK ordenes | |
| `orden_pieza_id` | unsignedBigInteger | FK orden_piezas | |
| `cantidad_devuelta` | integer | requerido, min:1 | |
| `motivo` | text | requerido | |
| `cobrable` | boolean | default:false | |
| `monto_cobro` | decimal(12,2) | nullable | |
| `estado` | varchar(50) | default:'abierta' | abierta, en_proceso, completada, reentregada |
| `operario_asignado_id` | unsignedBigInteger | nullable, FK users | |
| `registrado_por` | unsignedBigInteger | FK users | |
| `completada_en` | timestamp | nullable | |
| `reentregada_en` | timestamp | nullable | |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

#### 17. `tabla_precios_servicios`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `tipo_servicio` | varchar(100) | requerido | Clave: "corte_doblez_hr_cr_galv" |
| `etiqueta_servicio` | varchar(255) | requerido | Label: "CORTE DOBLEZ HR CR GALVANIZADO" |
| `clave_calibre` | varchar(20) | requerido | #22, #20, 1/8, etc. |
| `calibre_mm` | decimal(5,2) | requerido | Espesor en mm |
| `largo_rango_min` | integer | requerido | Inicio rango largo cm |
| `largo_rango_max` | integer | nullable | Fin rango. NULL = sin limite |
| `cantidad_rango_min` | integer | requerido | Inicio rango cantidad |
| `cantidad_rango_max` | integer | nullable | Fin rango. NULL = sin limite |
| `precio` | decimal(12,2) | requerido | Precio unitario sin IVA |
| `precio_minimo` | decimal(12,2) | nullable | |
| `created_at` | timestamp | auto | |
| `updated_at` | timestamp | auto | |

**6 tipos de servicio a precargar:**
1. CORTE DOBLEZ HR CR GALVANIZADO (minima: $6,839)
2. DOBLEZ INOX (minima: $7,816)
3. CORTE INOX (minima: $6,839)
4. CORTE DOBLEZ ALUMINIO LISO Y ALFAJOR (minima: $7,816)
5. CORTE DOBLEZ ALFAJOR HR (minima: $7,295)
6. CORTE DOBLEZ ACERO 430 (minima: $6,437)

Total: 13 calibres x 4 rangos largo x 6 rangos cantidad x 6 tablas = **1,872 registros**

#### 18. `notificaciones`
| Campo | Tipo | Validacion | Notas |
|-------|------|-----------|-------|
| `id` | bigIncrements | auto | PK |
| `usuario_id` | unsignedBigInteger | FK users | Destinatario |
| `tipo` | varchar(100) | requerido | Tipo de notificacion |
| `titulo` | varchar(255) | requerido | |
| `contenido` | text | nullable | |
| `url` | varchar(500) | nullable | Link al recurso |
| `leida` | boolean | default:false | |
| `leida_en` | timestamp | nullable | |
| `created_at` | timestamp | auto | |

### Relaciones entre Modelos

```
clientes
  └── hasMany ordenes

ordenes
  ├── belongsTo clientes
  ├── belongsTo users (creado_por)
  ├── hasMany orden_items
  ├── hasMany orden_bosquejos
  ├── hasMany orden_piezas
  ├── hasMany pagos
  ├── hasMany orden_fotos
  ├── hasMany orden_comentarios
  ├── hasMany asignaciones_piezas
  ├── hasMany registro_actividades
  └── hasMany devoluciones_garantia

orden_piezas
  ├── belongsTo ordenes
  ├── belongsTo orden_bosquejos (nullable)
  ├── belongsTo users (operario_actual, nullable)
  ├── hasMany asignaciones_piezas
  ├── hasMany historial_avances
  ├── hasMany orden_fotos
  └── hasMany devoluciones_garantia

asignaciones_piezas
  ├── belongsTo orden_piezas
  ├── belongsTo ordenes
  ├── belongsTo users (asignado_desde, nullable)
  ├── belongsTo users (asignado_a)
  └── belongsTo users (asignado_por)

historial_avances
  ├── belongsTo orden_piezas
  └── belongsTo users (operario)

pagos
  ├── belongsTo ordenes
  ├── belongsTo users (registrado_por)
  └── belongsTo users (aprobado_por, nullable)

orden_bosquejos
  ├── belongsTo ordenes
  ├── belongsTo plantillas_bosquejos (nullable)
  └── hasMany orden_piezas

plantillas_bosquejos
  └── belongsTo grupos_bosquejos (nullable)

grupos_bosquejos
  └── hasMany plantillas_bosquejos

orden_fotos
  ├── belongsTo ordenes
  ├── belongsTo orden_piezas (nullable)
  └── belongsTo users (subido_por)

orden_comentarios
  ├── belongsTo ordenes
  └── belongsTo users

registro_actividades
  ├── belongsTo users
  └── belongsTo ordenes (nullable)

devoluciones_garantia
  ├── belongsTo ordenes
  ├── belongsTo orden_piezas
  ├── belongsTo users (operario_asignado, nullable)
  └── belongsTo users (registrado_por)

notificaciones
  └── belongsTo users

configuracion_sistema (independiente)
tabla_precios_servicios (independiente)
```

---

## MODULOS Y FUNCIONALIDADES

### Estado de Desarrollo

| # | Fase | Modulo | Estado | Spec |
|---|------|--------|--------|------|
| 0 | FASE 0 | Base de Datos Completa | **Completado** | - |
| 1 | FASE 1 | Autenticacion y Roles | **Completado** | Mod 1, 16 |
| 2 | FASE 2 | Clientes | **Completado** | Mod 2 |
| 3 | FASE 3 | Catalogo de Items | **Completado** | Mod 3 |
| 4 | FASE 4 | Bosquejos Matriz | **Completado** | Mod 4 |
| 5 | FASE 5 | Ordenes - Creacion (Wizard) | **Completado** | Mod 5, 6 |
| 6 | FASE 6 | Ordenes - Busqueda y Gestion | **Completado** | Mod 7 |
| 7 | FASE 7 | Flujo del Operario | **Completado** | Mod 9 |
| 8 | FASE 8 | Entregas | **Completado** | Mod 8 |
| 9 | FASE 9 | Contabilidad | **Completado** | Mod 10 |
| 10 | FASE 10 | PDF Imprimible (3 hojas) | **Completado** | Mod 11 |
| 11 | FASE 11 | Garantias | **Completado** | Mod 5.11 |
| 12 | FASE 12 | Tabla de Precios Parametrica | **Completado** | Mod 12 |
| 13 | FASE 13 | Administracion y Configuracion | **Completado** | Mod 16 |
| 14 | FASE 14 | Dashboards por Rol | **Completado** | Mod 17 |
| 15 | FASE 15 | Notificaciones | **Completado** | Mod 13 |
| 16 | FASE 16 | Registro de Actividades (Vistas) | **Completado** | Mod 18 |
| 17 | FASE 17 | Manejo de Conexion | **Completado** | Mod 14 |
| 18 | FASE 18 | Limpieza de Borradores | **Completado** | Mod 15 |
| - | BASE | Base, Usuarios y Permisos (esqueleto) | Completado | - |

### Retroalimentacion del Cliente (2026-02-21)

Se realizo reunion con el cliente quien reviso los modulos completados (Fases 0-6). Se solicitaron 21 ajustes, todos implementados exitosamente:

| # | Requerimiento | Estado | Modulo afectado |
|---|--------------|--------|-----------------|
| 1 | Actualizar contrasena y eliminar cuenta solo admin | ✅ Completado | FASE 1 (Auth) |
| 2 | En perfil de cliente link de ordenes | ✅ Completado | FASE 2 (Clientes) |
| 3 | Cliente cedula o NIT | ✅ Completado | FASE 2 (Clientes) |
| 4 | Numero WhatsApp | ✅ Completado | FASE 2 (Clientes) |
| 5 | Items: Recepcion sin acciones ni creacion | ✅ Completado | FASE 3 (Items) |
| 6 | Recepcion no puede inactivar clientes | ✅ Completado | FASE 2 (Clientes) |
| 7 | Recepcion en bosquejos matriz con opciones | ✅ Completado | FASE 4 (Bosquejos) |
| 8 | Bosquejos sin grupo | ✅ Completado | FASE 4/5 (Bosquejos/Wizard) |
| 9 | Quitar "Complete las secciones" y steps en titulo | ✅ Completado | FASE 5 (Wizard) |
| 10 | Boton cliente predeterminado MOSTRADOR | ✅ Completado | FASE 5 (Wizard) |
| 11 | En crear orden: primero bosquejos, luego items | ✅ Completado | FASE 5 (Wizard) |
| 12 | Borrar en dibujar bosquejo con fondo blanco | ✅ Completado | FASE 5 (Wizard) |
| 13 | Validar calidad del bosquejo al guardar | ✅ Completado | FASE 5 (Wizard) |
| 14 | En el dibujo sin padding | ✅ Completado | FASE 5 (Wizard) |
| 15 | Grupo de N bosquejos = N piezas automaticas | ✅ Completado | FASE 5 (Wizard) |
| 16 | Cambiar nombre del bosquejo al subir | ✅ Completado | FASE 5 (Wizard) |
| 17 | Materiales con buscador searchable en piezas | ✅ Completado | FASE 5 (Wizard) |
| 18 | En piezas columna de notas en crear orden | ✅ Completado | FASE 5 (Wizard) |
| 19 | Configuracion de IVA con checkbox por item | ✅ Completado | FASE 5 (Wizard) |
| 20 | Notas / Observaciones generales | ✅ Completado | FASE 5 (Wizard) |
| 21 | Filtrar ordenes por cliente y numero de orden | ✅ Completado | FASE 6 (Busqueda) |

**Cambios principales aplicados:**
- Wizard reordenado: Cliente > Bosquejos/Piezas > Items (antes era Cliente > Items > Bosquejos)
- Checkbox IVA por item en seccion Items (cada item puede activar/desactivar IVA individualmente)
- Cliente MOSTRADOR predeterminado con boton rapido en wizard
- Items: Recepcion tiene acceso solo lectura (sin crear/editar/toggle)
- Bosquejos Matriz: Recepcion puede gestionar grupos y bosquejos
- Canvas de dibujo sin padding con validacion de calidad
- Material en piezas con select searchable
- Campo cedula/NIT en clientes (migracion 2026_02_20_000001)
- Campo notas en orden_piezas (migracion 2026_02_21_000001)

---

## PLAN DE TRABAJO DETALLADO

### FASE 0: Base de Datos Completa

> **Objetivo:** Crear TODAS las migraciones, modelos con relaciones, y seeders antes de construir cualquier modulo.

**Archivos a crear:**
- 18 migraciones en `database/migrations/`
- 18 modelos en `app/Models/`
- `app/Traits/RegistraActividad.php` (trait para logging)
- `database/seeders/ConfiguracionSistemaSeeder.php`
- `database/seeders/TablaPreciosSeeder.php`

**Archivos a modificar:**
- `database/seeders/RolesAndPermissionsSeeder.php` (4 roles + permisos)
- `database/seeders/DatabaseSeeder.php` (llamar nuevos seeders)
- `app/Models/User.php` (nuevas relaciones)

**Checklist:**
- [ ] Crear migracion `create_clientes_table`
- [ ] Crear migracion `create_catalogo_items_table`
- [ ] Crear migracion `create_grupos_bosquejos_table`
- [ ] Crear migracion `create_plantillas_bosquejos_table`
- [ ] Crear migracion `create_configuracion_sistema_table`
- [ ] Crear migracion `create_ordenes_table`
- [ ] Crear migracion `create_orden_items_table`
- [ ] Crear migracion `create_orden_bosquejos_table`
- [ ] Crear migracion `create_orden_piezas_table`
- [ ] Crear migracion `create_asignaciones_piezas_table`
- [ ] Crear migracion `create_historial_avances_table`
- [ ] Crear migracion `create_pagos_table`
- [ ] Crear migracion `create_orden_fotos_table`
- [ ] Crear migracion `create_orden_comentarios_table`
- [ ] Crear migracion `create_registro_actividades_table`
- [ ] Crear migracion `create_devoluciones_garantia_table`
- [ ] Crear migracion `create_tabla_precios_servicios_table`
- [ ] Crear migracion `create_notificaciones_table`
- [ ] Crear modelo Cliente con relaciones
- [ ] Crear modelo CatalogoItem con relaciones
- [ ] Crear modelo GrupoBosquejo con relaciones
- [ ] Crear modelo PlantillaBosquejo con relaciones
- [ ] Crear modelo ConfiguracionSistema
- [ ] Crear modelo Orden con relaciones y scopes
- [ ] Crear modelo OrdenItem con relaciones
- [ ] Crear modelo OrdenBosquejo con relaciones
- [ ] Crear modelo OrdenPieza con relaciones
- [ ] Crear modelo AsignacionPieza con relaciones
- [ ] Crear modelo HistorialAvance con relaciones
- [ ] Crear modelo Pago con relaciones
- [ ] Crear modelo OrdenFoto con relaciones
- [ ] Crear modelo OrdenComentario con relaciones (sin update/delete)
- [ ] Crear modelo RegistroActividad con relaciones (sin update/delete)
- [ ] Crear modelo DevolucionGarantia con relaciones
- [ ] Crear modelo TablaPrecioServicio
- [ ] Crear modelo Notificacion con relaciones
- [ ] Crear trait RegistraActividad
- [ ] Actualizar User.php con nuevas relaciones
- [ ] Actualizar RolesAndPermissionsSeeder (4 roles + permisos)
- [ ] Crear ConfiguracionSistemaSeeder
- [ ] Crear TablaPreciosSeeder (1,872 registros)
- [ ] Actualizar DatabaseSeeder
- [ ] Ejecutar `migrate:fresh --seed` exitosamente
- [ ] Verificar relaciones con tinker

**Migraciones adicionales post-Fase 0:**
- `2026_02_14_000001_add_ultimo_login_to_users_table.php` (FASE 1 - campo ultimo_login en users)
- `2026_02_20_000001_add_cedula_to_clientes_table.php` (Retroalimentacion - campo cedula varchar(20) nullable en clientes)
- `2026_02_21_000001_add_notas_to_orden_piezas_table.php` (Retroalimentacion - campo notas text nullable en orden_piezas)

**Seeders adicionales post-Fase 0:**
- `database/seeders/ClientePredeterminadoSeeder.php` (Retroalimentacion - crea cliente "MOSTRADOR")

---

### FASE 1: Autenticacion y Roles ✅ COMPLETADO (2026-02-15)

> **Objetivo:** Adaptar auth existente para 4 roles con redirects, middleware y navegacion condicional.

**Archivos modificados:**
- `app/Services/Auth/RoleService.php` - getDashboardRoute() con jerarquia de roles (Admin=4 > Recepcion=3 > Contabilidad=2 > Operario=1)
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` - Registro actividad login + ultimo_login
- `app/Http/Controllers/Auth/RoleRedirectController.php` - Usa RoleService para redirect dinamico
- `app/Models/User.php` - Agregado `ultimo_login` a fillable/casts
- `routes/web.php` - Grupos de rutas por rol con middleware Spatie
- `routes/auth.php` - Registro publico deshabilitado (usuarios se crean desde Admin)
- `resources/views/layouts/navigation-vertical.blade.php` - Navegacion completa por rol con @hasanyrole/@role
- `app/Providers/AppServiceProvider.php` - Removido AlertaComposer legacy
- `public/css/sinden-components.css` - Estilos para nav items deshabilitados

**Archivos nuevos:**
- `database/migrations/2026_02_14_000001_add_ultimo_login_to_users_table.php`
- `resources/views/recepcion/panel.blade.php` - Panel placeholder Recepcion
- `resources/views/operario/panel.blade.php` - Panel placeholder Operario
- `resources/views/contabilidad/panel.blade.php` - Panel placeholder Contabilidad
- `resources/views/admin/configuracion/index.blade.php` - Panel Admin con stats dinamicos

**Rutas creadas:**
- `/dashboard` → RoleRedirectController (redirige segun rol)
- `/recepcion/panel` → middleware `role:Administrador|Recepcion`
- `/operario/panel` → middleware `role:Administrador|Operario`
- `/contabilidad/panel` → middleware `role:Administrador|Contabilidad`
- `/admin/configuracion` → middleware `role:Administrador`

**Navegacion por rol:**
- Administrador: Ordenes, Catalogos, Finanzas, Administracion, Sistema, Cuenta
- Recepcion: Ordenes, Catalogos, Sistema, Cuenta
- Contabilidad: Finanzas, Sistema, Cuenta
- Operario: Mi Trabajo, Sistema, Cuenta
- Links no implementados usan `href="#"` con clase disabled y tooltip "Disponible en Fase X"

**Notas tecnicas:**
- RoleService tiene constante ROLE_HIERARCHY para resolver multi-rol (mayor jerarquia gana)
- Cada login registra `usuario.inicio_sesion` en registro_actividades via trait RegistraActividad
- `/register` retorna 404 (rutas comentadas)
- Acceso no autorizado retorna 403 via middleware Spatie

**Checklist:**
- [x] Configurar redirect post-login segun rol (Admin -> /admin/configuracion, Recepcion -> /recepcion/panel, etc.)
- [x] Crear grupos de rutas con middleware: `/recepcion/*`, `/operario/*`, `/contabilidad/*`, `/admin/*`
- [x] Actualizar RoleService: getDashboardRoute() para 4 roles, jerarquia
- [x] Navegacion vertical condicional por rol con `@role()` y `@hasanyrole()`
- [x] Formulario de usuarios ya soporta 4 roles (existia previamente en admin/users/index)
- [x] Verificar login con cada rol redirige correctamente
- [x] Registrar actividad `usuario.inicio_sesion` al hacer login
- [x] Deshabilitar registro publico (/register -> 404)
- [x] Agregar columna `ultimo_login` a tabla users
- [x] Seguridad verificada: 403 para acceso no autorizado entre roles
- [x] Actualizar contrasena y eliminar cuenta restringido a Administrador - Retroalimentacion 2026-02-21

---

### FASE 2: Clientes ✅ COMPLETADO (2026-02-15)

> **Objetivo:** CRUD completo de clientes con borrado logico, busqueda, exportacion.

**Archivos creados:**
- `app/Http/Controllers/ClienteController.php` - 10 metodos (index, create, store, show, edit, update, toggleActivo, autocomplete, exportExcel, exportPdf)
- `resources/views/clientes/index.blade.php` - DataTable server-side Yajra, 4 stat-cards, botones exportar
- `resources/views/clientes/create.blade.php` - Formulario con form-group components, layout 8/4
- `resources/views/clientes/edit.blade.php` - Formulario precargado con datos del cliente
- `resources/views/clientes/show.blade.php` - Detalle con info + placeholder ordenes
- `app/Exports/ClientesExport.php` - Excel con header verde SINDEN, auto-size

**Archivos modificados:**
- `routes/web.php` - 6 rutas (resource + autocomplete + export-excel + export-pdf + toggle-activo) bajo grupo recepcion
- `resources/views/layouts/navigation-vertical.blade.php` - Link Clientes activo con highlight

**Rutas creadas:**
- `GET /recepcion/clientes` → index (DataTable server-side via AJAX)
- `GET /recepcion/clientes/create` → formulario crear
- `POST /recepcion/clientes` → store
- `GET /recepcion/clientes/{cliente}` → show
- `GET /recepcion/clientes/{cliente}/edit` → formulario editar
- `PUT /recepcion/clientes/{cliente}` → update
- `PATCH /recepcion/clientes/{cliente}/toggle-activo` → toggle activo/inactivo via AJAX
- `GET /recepcion/clientes/autocomplete?q=` → JSON autocompletado (max 10, solo activos)
- `GET /recepcion/clientes/export-excel` → descarga .xlsx
- `GET /recepcion/clientes/export-pdf` → descarga .pdf (landscape, DomPDF)

**Notas tecnicas:**
- DataTables server-side con Yajra para rendimiento con muchos registros
- Toggle activo via AJAX PATCH + SweetAlert2 confirmacion, recarga tabla sin perder paginacion
- Autocomplete busca en nombre, celular_1, celular_2, correo con LIKE %q%, minimo 2 caracteres
- PDF generado con HTML inline en controller (DomPDF), sin vista Blade adicional
- Registro actividad: `cliente.creado` y `cliente.actualizado` (incluye toggle activo)

**Checklist:**
- [x] ClienteController con index, create, store, show, edit, update, toggleActivo, autocomplete, exportExcel, exportPdf
- [x] Vista index con DataTable server-side (busqueda por nombre, celular, correo)
- [x] Formulario crear/editar cliente con componentes form-group
- [x] Borrado logico (campo activo toggle con SweetAlert2 + AJAX)
- [x] Exportar PDF (listado activos, landscape)
- [x] Exportar Excel (header verde, auto-size)
- [x] Endpoint AJAX para autocompletado (buscar por nombre/celular/correo, devolver JSON)
- [x] Registrar actividad: `cliente.creado`, `cliente.actualizado`
- [x] Rutas bajo `/recepcion/clientes`
- [x] Agregar al menu lateral (Recepcion + Admin via @hasanyrole)
- [x] Toggle activo protegido por middleware (solo Administrador) - Retroalimentacion 2026-02-21
- [x] Campo cedula/NIT agregado (migracion 2026_02_20_000001) - Retroalimentacion 2026-02-21

---

### FASE 3: Catalogo de Items ✅ COMPLETADO (2026-02-15)

> **Objetivo:** CRUD de productos/servicios del catalogo con categorias.

**Archivos creados:**
- `app/Http/Controllers/CatalogoItemController.php` - 9 metodos (index, create, store, edit, update, toggleActivo, autocomplete, exportExcel, exportPdf)
- `app/Exports/CatalogoItemsExport.php` - Excel con header verde SINDEN, auto-size
- `resources/views/catalogo-items/index.blade.php` - DataTable server-side Yajra, 4 stat-cards, botones exportar
- `resources/views/catalogo-items/create.blade.php` - Formulario con form-group components, layout 8/4
- `resources/views/catalogo-items/edit.blade.php` - Formulario precargado con datos del item

**Archivos modificados:**
- `routes/web.php` - 6 rutas recepcion (resource + autocomplete + export-excel + export-pdf + toggle-activo) + 1 ruta contabilidad (solo lectura)
- `resources/views/layouts/navigation-vertical.blade.php` - Link Items activo con highlight en Catalogos (Recepcion) y Finanzas (Contabilidad)

**Rutas creadas:**
- `GET /recepcion/items` → index (DataTable server-side via AJAX)
- `GET /recepcion/items/create` → formulario crear
- `POST /recepcion/items` → store
- `GET /recepcion/items/{item}/edit` → formulario editar
- `PUT /recepcion/items/{item}` → update
- `PATCH /recepcion/items/{item}/toggle-activo` → toggle activo/inactivo via AJAX
- `GET /recepcion/items/autocomplete?q=` → JSON autocompletado (max 10, solo activos, busca en codigo+descripcion)
- `GET /recepcion/items/export-excel` → descarga .xlsx
- `GET /recepcion/items/export-pdf` → descarga .pdf (landscape, DomPDF)
- `GET /contabilidad/items` → index solo lectura (sin crear/editar/toggle)

**Notas tecnicas:**
- DataTables server-side con Yajra, mismo patron que Clientes
- Toggle activo via AJAX PATCH + SweetAlert2 confirmacion
- Autocomplete retorna: id, codigo, descripcion, precio_unitario, porcentaje_iva, categoria (para uso en ordenes)
- Contabilidad solo lectura: controlado por permiso `editar_catalogo_items` (sin botones editar/toggle en DataTable)
- Categorias: servicio (badge primary), material (badge info), producto_terminado (badge warning)
- Registro actividad: `catalogo_item.creado` y `catalogo_item.actualizado` (incluye toggle activo)
- Validacion codigo unico con exclusion en update (unique:catalogo_items,codigo,{id})

**Checklist:**
- [x] CatalogoItemController CRUD completo
- [x] Vista index con DataTable (filtro por codigo, descripcion, categoria)
- [x] Formulario crear/editar: codigo (unico), descripcion, precio_unitario, porcentaje_iva, categoria
- [x] Categorias con labels: servicio="SERVICIO", material="MATERIAL", producto_terminado="PRODUCTO TERMINADO"
- [x] Borrado logico (desactivar, pedir confirmacion)
- [x] Endpoint AJAX JSON para busqueda desde ordenes (al seleccionar auto-llena codigo, descripcion, precio, categoria)
- [x] Rutas: `/recepcion/items` y `/contabilidad/items` (mismo controller)
- [x] Agregar al menu lateral (Recepcion, Contabilidad, Admin)
- [x] Recepcion: acceso solo lectura (sin crear, editar ni toggle activo) - Retroalimentacion 2026-02-21

---

### FASE 4: Bosquejos Matriz

> **Objetivo:** Biblioteca de plantillas de bosquejos organizadas por grupos.

**Archivos a crear:**
- `app/Http/Controllers/BosquejoMatrizController.php`
- `resources/views/bosquejos-matriz/index.blade.php`

**Checklist:**
- [x] Controller CRUD de grupos y plantillas
- [x] Vista con secciones colapsables por grupo (Bootstrap accordion)
- [x] Tarjetas con miniatura por bosquejo
- [x] Subida de imagenes (jpg, png, webp) a `public/uploads/bosquejos-matriz/`
- [x] Generacion de miniaturas (resize) con intervention/image v2.7
- [x] Crear grupo (solo nombre)
- [x] Agregar bosquejo a grupo (imagen + nombre)
- [x] Ver (expandir), Editar nombre, Eliminar, Descargar bosquejo
- [x] Rutas bajo `/recepcion/bosquejos-matriz`
- [x] Agregar al menu lateral (Recepcion + Admin)
- [x] Fix conflicto Tailwind CDN `.collapse` vs Bootstrap `.collapse`
- [x] Recepcion puede gestionar bosquejos (crear grupos, subir/editar/eliminar bosquejos) - Retroalimentacion 2026-02-21

---

### FASE 5: Ordenes - Creacion (Wizard)

> **Objetivo:** Modulo principal - wizard multi-seccion para crear ordenes de trabajo.

**Archivos a crear:**
- `app/Http/Controllers/OrdenController.php`
- `app/Services/OrdenService.php`
- `app/Services/OrdenEstadoService.php`
- `resources/views/ordenes/create.blade.php`
- `resources/views/ordenes/partials/_seccion-cliente.blade.php`
- `resources/views/ordenes/partials/_seccion-fechas.blade.php`
- `resources/views/ordenes/partials/_seccion-items.blade.php`
- `resources/views/ordenes/partials/_seccion-bosquejos-piezas.blade.php`
- `resources/views/ordenes/partials/_seccion-firma.blade.php`
- `resources/views/ordenes/partials/_seccion-operario.blade.php`
- `resources/views/ordenes/partials/_seccion-pagos.blade.php`
- `public/js/orden-wizard.js`
- `public/js/firma-canvas.js`
- `public/js/dibujo-canvas.js`

**Orden de secciones del Wizard (actualizado 2026-02-21 - retroalimentacion cliente):**
1. Cliente (con boton rapido "MOSTRADOR" para cliente predeterminado)
2. Bosquejos y Piezas (bosquejos sin grupo soportados, grupo de N = N piezas auto)
3. Items / Servicios (con checkbox IVA activo/inactivo por cada item)
4. Firma del Cliente
5. Asignar Operario
6. Pagos / Abonos
7. Fechas y Notas

> Nota: Los botones "Guardar Orden" y "Generar Orden" estan al final del formulario (debajo de seccion 7).

**Checklist:**
- [x] Seccion 1 - Cliente: Autocompletado AJAX de cliente existente + boton "Crear Nuevo" (modal inline) + boton rapido "MOSTRADOR"
- [x] Seccion 2 - Bosquejos: 5 metodos de insercion (archivo local, plantilla matriz, grupo completo, foto camara, dibujo tablet). Edicion de bosquejos (dibujar encima de imagen existente) con 4 grosores (Ultra Fino 1px, Fino 2px, Medio 4px, Grueso 8px). Bosquejos sin grupo soportados. Canvas sin padding. Validacion calidad al guardar
- [x] Seccion 2b - Piezas: Tabla dinamica (nombre auto A/B/C, cantidad, material select searchable, calibre select, bosquejo asociado, notas). Especificacion auto-generada. Grupo de N bosquejos genera N piezas automaticamente
- [x] Seccion 3 - Items: Tabla editable dinamica (agregar/eliminar filas). Select codigo del catalogo auto-llena. Checkbox IVA por item. Calculos reactivos: Valor = Cantidad x P.Unitario. Totales: Subtotal, IVA, Total
- [x] Seccion 4 - Firma: Canvas firma digital, boton limpiar, guardar como PNG
- [x] Seccion 5 - Operario: Select de operarios activos (solo si hay piezas). Obligatorio para generar
- [x] Seccion 6 - Pagos: Monto + metodo pago (efectivo/nequi/transferencia/tarjeta/otro). Agregar multiples. SALDO = TOTAL - abonos
- [x] Seccion 7 - Fechas: Fecha creacion (auto, solo lectura), fecha entrega (date picker), hora entrega, notas/observaciones
- [x] Step indicators: se ponen verdes al completar cada seccion
- [x] OrdenService: logica de guardar borrador y generar orden
- [x] OrdenEstadoService: recalculo automatico de 3 estados independientes
- [x] Boton GUARDAR ORDEN: Borrador, sin numero, validacion minima
- [x] Boton GENERAR ORDEN: Validacion completa, confirmar (boton habilitado tras 1s), asigna #consecutivo, crea asignaciones
- [x] Logica sin piezas = venta directa: estado_trabajo='ejecutada', estado_entrega='entregada'
- [x] Auto-guardado por inactividad (5 min configurable)
- [x] Registrar actividades
- [x] Rutas: `/recepcion/ordenes/crear`
- [x] Boton rapido "MOSTRADOR" para seleccionar cliente predeterminado - Retroalimentacion 2026-02-21
- [x] Checkbox IVA activo/inactivo por cada item (cada item puede activar/desactivar IVA individualmente) - Retroalimentacion 2026-02-21
- [x] Wizard steps en titulo a la derecha, sin texto "Complete las secciones para crear una nueva orden" - Retroalimentacion 2026-02-21
- [x] Reordenamiento secciones: Bosquejos/Piezas (seccion 2) antes de Items (seccion 3) - Retroalimentacion 2026-02-21
- [x] Bosquejos sin grupo soportados en seleccion desde matriz - Retroalimentacion 2026-02-21
- [x] Grupo de N bosquejos genera N piezas automaticamente - Retroalimentacion 2026-02-21
- [x] Material con select searchable (buscador) en piezas - Retroalimentacion 2026-02-21
- [x] Validacion de calidad de bosquejo al guardar (detecta canvas vacio) - Retroalimentacion 2026-02-21
- [x] Canvas de dibujo sin padding - Retroalimentacion 2026-02-21
- [x] Borrar dibujo limpia con fondo blanco - Retroalimentacion 2026-02-21
- [x] Cambiar nombre del bosquejo al subir - Retroalimentacion 2026-02-21
- [x] Notas / Observaciones generales en seccion Fechas - Retroalimentacion 2026-02-21

---

### FASE 6: Ordenes - Busqueda y Gestion ✅ COMPLETADO (2026-02-15)

> **Objetivo:** Buscar, ver detalle completo, editar, copiar, anular ordenes.

**Archivos creados:**
- `resources/views/ordenes/index.blade.php` - DataTable server-side Yajra, 5 stat-cards, filtros, botones exportar
- `resources/views/ordenes/show.blade.php` - Vista detalle 2 columnas (8+4), 11 secciones via partials
- `resources/views/ordenes/show/_seccion-encabezado.blade.php` - Header + 3 badges estado + resumen financiero
- `resources/views/ordenes/show/_seccion-cliente.blade.php` - Info cliente con link a detalle
- `resources/views/ordenes/show/_seccion-fechas.blade.php` - Fechas, creador, notas
- `resources/views/ordenes/show/_seccion-items.blade.php` - Tabla items + totales + desglose categoria
- `resources/views/ordenes/show/_seccion-bosquejos.blade.php` - Grid thumbnails con lightbox
- `resources/views/ordenes/show/_seccion-piezas.blade.php` - Cards con progress-bar + historial timeline colapsable
- `resources/views/ordenes/show/_seccion-pagos.blade.php` - Lista pagos + agregar (modal) + resumen financiero
- `resources/views/ordenes/show/_seccion-firma.blade.php` - Imagen firma o placeholder
- `resources/views/ordenes/show/_seccion-fotos.blade.php` - Galeria fotos con lightbox
- `resources/views/ordenes/show/_seccion-comentarios.blade.php` - Timeline comentarios + formulario agregar AJAX
- `resources/views/ordenes/show/_seccion-garantias.blade.php` - Lista garantias con acciones por estado (FASE 11)
- `resources/views/ordenes/edit.blade.php` - Wizard precargado (reutiliza 7 partials de create)
- `app/Exports/OrdenesExport.php` - Excel header verde, 13 columnas, auto-size
- `public/js/orden-detalle.js` - JS vista show (copiar, anular, pagos AJAX, comentarios AJAX, lightbox, garantias AJAX)
- `public/js/orden-edit-init.js` - JS precargar wizard con datos existentes de ORDEN_DATA

**Archivos modificados:**
- `routes/web.php` - 10 rutas nuevas (index, export-excel, export-pdf, show, edit, update, copiar, anular, comentarios, pagos)
- `app/Http/Controllers/OrdenController.php` - 12 metodos nuevos + 3 badge helpers (index, show, edit, update, copiar, anular, agregarComentario, agregarPago, exportExcel, exportPdf, badgeEstadoTrabajo, badgeEstadoEntrega, badgeEstadoPago)
- `app/Services/OrdenService.php` - Metodo copiarArchivosBosquejo() para copia fisica de archivos
- `public/js/orden-wizard.js` - Soporte EDIT_MODE (usa PUT en lugar de POST para guardar)
- `public/css/sinden-components.css` - Estilos detalle orden (pieza-card, historial-timeline, comment-item, photo-gallery, lightbox, financial-summary)
- `resources/views/layouts/navigation-vertical.blade.php` - Link "Buscar Ordenes" activado con highlight

**Rutas creadas:**
- `GET /recepcion/ordenes` → index (DataTable server-side via AJAX)
- `GET /recepcion/ordenes/export-excel` → descarga .xlsx
- `GET /recepcion/ordenes/export-pdf` → descarga .pdf (landscape, DomPDF)
- `GET /recepcion/ordenes/{orden}` → show (detalle 11 secciones)
- `GET /recepcion/ordenes/{orden}/editar` → edit (wizard precargado)
- `PUT /recepcion/ordenes/{orden}` → update (AJAX)
- `POST /recepcion/ordenes/{orden}/copiar` → copiar (nuevo borrador)
- `POST /recepcion/ordenes/{orden}/anular` → anular (con motivo)
- `POST /recepcion/ordenes/{orden}/comentarios` → agregarComentario (AJAX)
- `POST /recepcion/ordenes/{orden}/pagos` → agregarPago (AJAX)

**Notas tecnicas:**
- Rutas literales (/ordenes, /ordenes/export-*) van ANTES de rutas con parametro {orden}
- Copiar orden: DB transaction, copia items/bosquejos(archivos fisicos)/piezas(reset avance=0), NO copia pagos/firma/asignaciones
- Pagos por Recepcion: aprobado=false (necesitan aprobacion Contabilidad). Por Admin/Contabilidad: auto-aprobado
- OrdenComentario: inmutable, solo insert (update/delete lanzan RuntimeException)
- Edit mode: EDIT_MODE=true en JS, ROUTES.guardar apunta a PUT update, ROUTES.generar apunta a POST generar (para borradores)
- Fix aplicado (2026-02-15): botones modales Registrar Pago y Anular Orden requerian onclick explicito + campo response JSON usa nuevo_total_pagado/nuevo_saldo (pre-formateados)
- Exportar PDF individual y separados/ZIP quedan para FASE 10

**Checklist:**
- [x] Vista busqueda: filtros (rango fechas, estado_trabajo, estado_entrega, estado_pago) + 5 stat cards
- [x] DataTable: Orden#, Cliente, Fecha Creacion, Fecha Entrega, 3 badges, Total, Saldo, Acciones
- [x] Vista detalle con 11 secciones: encabezado+estados, cliente, fechas, items+totales, bosquejos expandibles, piezas con barra progreso+historial, pagos, firma, galeria fotos, comentarios, garantias
- [x] Editar orden (wizard precargado, mismas secciones, PUT method)
- [x] Copiar orden (nuevo borrador sin pagos/firma/asignaciones)
- [x] Anular orden (confirmacion con motivo, libera asignaciones)
- [x] Agregar abono desde detalle (modal AJAX)
- [x] Agregar comentario (AJAX sin recarga)
- [x] Exportar Excel (OrdenesExport con header verde)
- [x] Exportar PDF listado (DomPDF landscape)
- [x] Rutas: `/recepcion/ordenes`, `/recepcion/ordenes/{id}`, `/recepcion/ordenes/{id}/editar`
- [x] Exportar PDF individual por orden (FASE 10)
- [x] Exportar PDF separados en ZIP (FASE 10)
- [x] Exportar PDF unido con salto de pagina (FASE 10)

---

### FASE 7: Flujo del Operario ✅

> **Objetivo:** Dashboard, vista de trabajo por pieza, transferencias, complementar ordenes.

**Archivos creados/modificados:**
- `app/Http/Controllers/OperarioController.php` (17 metodos: panel, ordenes, trabajo, AJAX, bloqueo)
- `app/Services/OperarioPiezaService.php` (actualizarAvances, transferir, dejarEnCola, tomar, subirFoto, stats)
- `app/Services/BloqueoService.php` (bloquear, desbloquear, heartbeat, forzarCierre, verificar)
- `resources/views/operario/panel.blade.php` (reescrito con stats reales + quick-actions)
- `resources/views/operario/ordenes-asignadas.blade.php` (DataTable server-side)
- `resources/views/operario/trabajar.blade.php` (piezas, sliders, fotos, transferir, historial)
- `resources/views/operario/buscar.blade.php` (busqueda por numero, solo lectura)
- `resources/views/operario/complementar.blade.php` (DataTable cola general, boton tomar)
- `public/js/operario-trabajo.js` (sliders, fotos, heartbeat, inactividad, force-close)
- `public/css/sinden-components.css` (estilos pieza-trabajo, lock-banner, progress-multi, quick-actions)
- `resources/views/layouts/navigation-vertical.blade.php` (3 links activos: Ordenes Asignadas, Buscar, Complementar)
- `routes/web.php` (17 rutas operario: GET paginas + POST AJAX)
- `app/Http/Controllers/OrdenController.php` (bloqueo integrado en edit())

**Notas de implementacion:**
- Se uso un unico OperarioController en vez de dos controllers separados
- Bloqueo permite multiples operarios simultaneos en la misma orden (cada uno edita sus propias piezas)
- lockForUpdate() en tomarPieza() para prevenir race conditions
- navigator.sendBeacon para liberar bloqueo al cerrar pestana
- Heartbeat cada 30s, force-close check cada 10s
- Inactividad: warning al 80% del timeout, auto-cierre al 100%

**Checklist:**
- [x] Dashboard: 4 stat-cards (ordenes asignadas, piezas en proceso, para complementar, completadas hoy) + fecha/usuario
- [x] Menu operario (Ordenes Asignadas, Buscar Orden, Complementar Ordenes + Mis Actividades deshabilitado)
- [x] Vista ordenes asignadas (DataTable server-side, solo ordenes con piezas del operario)
- [x] Vista trabajo por pieza: bosquejo thumbnail, nombre/spec/material/calibre, barra progreso multi-color, slider+input sincronizados
- [x] Porcentaje puede SUBIR y BAJAR libremente. Si baja: notificacion + registro `pieza.avance_disminuido`
- [x] Adjuntar foto con preview: "Esta bien la foto?" -> Aceptar/Repetir (SweetAlert)
- [x] Boton ACTUALIZAR ORDEN con 3 tipos de confirmacion (sin cambios / algunas al 100% / todas al 100% = Orden Ejecutada)
- [x] Transferir pieza a otro operario (modal con dropdown + notas opcionales)
- [x] Dejar pieza en cola general (operario_actual_id = NULL, SweetAlert confirmacion)
- [x] Buscar orden por numero (vista solo lectura con piezas, progreso, historial)
- [x] Complementar: DataTable piezas sin operario, boton TOMAR PIEZA con confirmacion
- [x] Bloqueo: registrar bloqueada_por/bloqueada_en al abrir (shared entre operarios)
- [x] Cierre por inactividad (polling, configurable via timeout_inactividad_operario)
- [x] Forzar cierre por rango mayor (temporizador configurable, countdown SweetAlert, auto-save)
- [x] Rutas: `/operario/*` (17 rutas con middleware role:Administrador|Operario)
- [x] Agregar al menu lateral (3 links activos con highlight condicional)

---

### FASE 8: Entregas ✅ COMPLETADO (2026-02-24, mejorado 2026-02-25)

> **Objetivo:** Flujo de entrega de piezas completadas al cliente + historial de entregas.

**Archivos creados:**
- `app/Http/Controllers/EntregaController.php` - 6 metodos (pendientes, flujo, entregarPiezas, entregaRapida, subirFotoEntrega, historial) + 2 badge helpers
- `resources/views/entregas/pendientes.blade.php` - DataTable server-side Yajra, 4 stat-cards, boton entrega rapida
- `resources/views/entregas/flujo.blade.php` - Vista unica 2 columnas con Alpine.js (tabla piezas + foto/boton inline)
- `resources/views/entregas/historial.blade.php` - DataTable server-side historial de piezas entregadas, 3 stat-cards

**Archivos modificados:**
- `routes/web.php` - 6 rutas nuevas bajo grupo recepcion (entregas-pendientes, flujo, entregar, entrega-rapida, foto-entrega, entregas-historial)
- `resources/views/layouts/navigation-vertical.blade.php` - Links "Entregas Pendientes" y "Historial Entregas" activos

**Rutas creadas:**
- `GET /recepcion/entregas-pendientes` → pendientes (DataTable server-side via AJAX)
- `GET /recepcion/entregas-pendientes/{orden}/flujo` → flujo (vista unica 2 columnas)
- `POST /recepcion/entregas-pendientes/{orden}/entregar` → entregarPiezas (AJAX, recibe pieza_ids[])
- `POST /recepcion/entregas-pendientes/{orden}/entrega-rapida` → entregaRapida (AJAX, todas las piezas al 100%)
- `POST /recepcion/entregas-pendientes/{orden}/foto-entrega` → subirFotoEntrega (AJAX, FormData)
- `GET /recepcion/entregas-historial` → historial (DataTable server-side via AJAX)

**Notas tecnicas:**
- Stat cards pendientes: Total Pendientes (primary), Piezas Listas (success), Entregadas Hoy (success, entregada_en = hoy), Entregas Vencidas (danger, fecha_entrega < hoy)
- Stat cards historial: Total Entregadas (primary), Entregadas Hoy (success), Ultimos 7 Dias (info)
- "Entregadas Hoy" cuenta piezas realmente entregadas hoy (entregada_en = today), NO la fecha pronosticada (fecha_entrega)
- "Entregas Vencidas" cuenta ordenes cuya fecha pronosticada (fecha_entrega) ya paso y aun tienen piezas sin entregar
- DataTable query pendientes: `Orden::whereHas('piezas', fn => porcentaje>=100 AND !entregada)->whereNotIn(borrador, anulada)`
- DataTable query historial: `OrdenPieza::where('entregada', true)->with(['orden.cliente', 'entregadaPorUsuario'])`
- Flujo simplificado: vista unica con layout 2 columnas (col-lg-8 tabla piezas | col-lg-4 foto + boton entregar)
- Flujo reducido de 4-6 clicks (wizard 3 pasos) a 2-3 clicks (seleccionar + entregar + confirmar SweetAlert)
- Foto de entrega: sube a `public/uploads/ordenes/{id}/fotos/entrega_{timestamp}_{rand}.{ext}`, tipo_foto='entrega', orden_pieza_id=null
- `entregarPiezas()` usa DB::beginTransaction, valida pieza pertenece a la orden, porcentaje >= 100, no entregada
- Recalculo via `$estadoService->recalcularTodo($orden)` actualiza estado_entrega, estado_trabajo, estado_pago
- Badge helpers copiados de OrdenController (badgeEstadoTrabajo, badgeEstadoEntrega) para independencia
- Fechas vencidas se muestran en rojo (text-danger fw-semibold), entregas hoy en amarillo (text-warning fw-semibold)
- Historial: columnas Fecha Entrega, Orden# (link a detalle), Cliente, Pieza, Cantidad, Material, Calibre, Entregado Por
- Historial: filterable por numero_orden y cliente_nombre, ordenable por fecha_entrega_formatted

**Checklist:**
- [x] Lista ordenes pendientes (piezas al 100% no entregadas)
- [x] Tabla: Orden#, Cliente, Fecha Entrega, Piezas Listas (X de Y), Estados, Acciones
- [x] Flujo simplificado: vista unica 2 columnas (tabla piezas + foto/boton inline), 2-3 clicks
- [x] Boton "Entregar Todas" visible cuando hay mas de 1 pieza sin seleccionar
- [x] Entrega rapida desde listado: 1 click confirma todas las completadas
- [x] Marcar piezas como entregadas (entregada=true, entregada_en, entregada_por, estado='entregada')
- [x] Recalcular estado_entrega de la orden (via OrdenEstadoService::recalcularTodo)
- [x] Registrar actividad `pieza.entregada` (una por cada pieza entregada)
- [x] Historial de entregas: DataTable con piezas entregadas, 3 stat cards, busqueda por orden/cliente
- [x] Rutas: `/recepcion/entregas-pendientes` (5 rutas) + `/recepcion/entregas-historial` (1 ruta)
- [x] Agregar al menu lateral: "Entregas Pendientes" y "Historial Entregas" con highlights independientes

**Verificacion realizada (2026-02-25):**
- [x] Login como recepcion@sinden.com - links "Entregas Pendientes" y "Historial Entregas" activos en menu lateral
- [x] Stat cards pendientes pre-entrega: Pendientes=1, Piezas=1, Entregadas Hoy=0, Vencidas=1 (fecha_entrega=20/02 < hoy)
- [x] Stat cards pendientes post-entrega: Pendientes=0, Piezas=0, Entregadas Hoy=1, Vencidas=0
- [x] DataTable pendientes: #0004, Cliente Mostrador, 20/02/2026 (rojo por vencida), 1 de 1, EJECUTADA, botones Entregar y Entrega Rapida
- [x] Flujo simplificado: vista unica 2 columnas, tabla con Pieza A (1, INOX, #18), checkbox funcional, contador "0 de 1"
- [x] Seleccionar pieza: fila resaltada verde, contador "1 de 1", boton "Entregar 1 Pieza(s)" habilitado
- [x] Area foto inline visible sin cambiar de paso, boton "Entregar Todas" visible
- [x] Click Entregar -> SweetAlert confirmacion "Se entregaran 1 pieza(s) al cliente"
- [x] Confirmar -> SweetAlert exito "1 pieza(s) entregada(s) exitosamente" -> redirect a pendientes
- [x] Historial: DataTable con 1 registro - 24/02/2026 18:31, #0004 (link), Cliente Mostrador, Pieza A, 1, INOX, #18, Usuario Recepcion
- [x] Stat cards historial: Total Entregadas=1, Entregadas Hoy=1, Ultimos 7 Dias=1
- [x] Navegacion: highlight independiente para cada vista (Entregas Pendientes vs Historial Entregas)

---

### FASE 9: Contabilidad ✅ COMPLETADO (2026-02-25)

> **Objetivo:** Dashboard contable, aprobar pagos, gestionar saldos.

**Archivos creados:**
- `app/Http/Controllers/ContabilidadController.php` (8 metodos: panel, ordenesPendientes, pagosPendientes, aprobarPago, aprobarPagosMasivo, agregarPago, rechazarPago + badge helpers)
- `resources/views/contabilidad/panel.blade.php` (dashboard con stat-cards clickeables, ultimos pagos, recaudo por metodo, acciones rapidas)
- `resources/views/contabilidad/ordenes-pendientes.blade.php` (DataTable server-side con filtros, modal agregar pago)
- `resources/views/contabilidad/pagos-pendientes.blade.php` (DataTable con checkboxes, aprobacion individual/masiva, rechazo, barra sticky)
- `public/js/contabilidad.js` (DataTables, AJAX aprobaciones, pagos inline, seleccion masiva)

**Archivos modificados:**
- `routes/web.php` (8 rutas contabilidad: panel, ordenes-pendientes, pagos CRUD, aprobar-masivo)
- `resources/views/layouts/navigation-vertical.blade.php` (seccion Finanzas con Ordenes Pendientes + Pagos por Aprobar)

**Checklist:**
- [x] Dashboard: 4 stat-cards clickeables + recaudo por metodo + ultimos pagos aprobados + acciones rapidas
- [x] Vista ordenes pendientes con filtros (numero, cliente, fechas) + DataTable server-side
- [x] Aprobar pagos: individual (1 click + confirmacion) y masivo (checkboxes + barra sticky)
- [x] Rechazar pagos pendientes (elimina pago incorrecto de Recepcion)
- [x] Agregar abono via modal (monto + metodo + referencia, auto-aprobado por Contabilidad)
- [x] Al aprobar/agregar: recalcular total_pagado, saldo, estado_pago via OrdenEstadoService
- [x] Progress bar % pagado en DataTable ordenes
- [x] Badges metodo de pago con colores (efectivo=verde, nequi=morado, transferencia=azul, tarjeta=amarillo)
- [x] Acceso a catalogo items (`/contabilidad/items`) - solo lectura
- [x] Rutas: `/contabilidad/*` (8 rutas)
- [x] Menu lateral actualizado (Finanzas: Ordenes Pendientes, Pagos por Aprobar, Items)
- [x] Optimizado para tablet: botones min 44px, targets touch-friendly, operaciones AJAX sin recarga

---

### FASE 10: PDF Imprimible (3 hojas) ✅ COMPLETADO (2026-03-11)

> **Objetivo:** Generacion de PDF imprimible por orden usando DomPDF.

**Archivos creados:**
- `app/Http/Controllers/OrdenPdfController.php`
- `resources/views/ordenes/pdf/orden.blade.php`
- `resources/views/ordenes/pdf/orden-multiple.blade.php`
- `resources/views/ordenes/pdf/_styles.blade.php`
- `resources/views/ordenes/pdf/_page-info.blade.php`
- `resources/views/ordenes/pdf/_page-bosquejos.blade.php`
- `resources/views/ordenes/pdf/_page-piezas.blade.php`

**Archivos modificados:**
- `routes/web.php` (3 rutas: `{orden}/pdf`, `pdf-multiple`, `pdf-zip`)
- `resources/views/ordenes/show.blade.php` (boton dropdown PDF con opciones 1/2/3/4 bosquejos por fila)
- `resources/views/ordenes/index.blade.php` (checkboxes + boton PDF Masivo unido/zip)
- `app/Http/Controllers/OrdenController.php` (icono PDF en acciones DataTable)

**Checklist:**
- [x] Pagina 1: Logo, numero orden, fechas, datos cliente, tabla items con totales, tabla abonos, saldo, firma cliente, persona que genero
- [x] Pagina 2: Lista piezas con especificacion, estado, operario, avance, entregadas + historial entregas
- [x] Pagina 3: Bosquejos (imagenes cuadradas) con grid configurable (1/2/3/4 por fila, default 2)
- [x] PDF individual por orden
- [x] PDF multiples separados en ZIP
- [x] PDF unido con salto de pagina
- [x] Ruta: `/ordenes/{id}/pdf`
- [x] Margenes en paginas de info y piezas, sin margenes en bosquejos
- [x] Imagenes convertidas a base64 para compatibilidad DomPDF

---

### FASE 11: Garantias ✅

> **Objetivo:** Flujo de devoluciones por garantia sobre piezas entregadas.
> **Completado:** 2026-03-11

**Archivos creados:**
- `app/Http/Controllers/GarantiaController.php` - 7 metodos: index, store, cambiarEstado, asignarOperario, completarTrabajo, piezasEntregadas, misGarantias
- `resources/views/garantias/index.blade.php` - Lista DataTable con stats (Abiertas, En Proceso, Listas Re-entrega, Total Cobrable) + filtro estado
- `resources/views/operario/garantias.blade.php` - DataTable garantias asignadas al operario con boton Completar

**Archivos modificados:**
- `app/Providers/RouteServiceProvider.php` - Binding explicito: Route::model('garantia', DevolucionGarantia::class)
- `routes/web.php` - 7 rutas (5 Recepcion + 2 Operario) para garantias
- `resources/views/ordenes/show/_seccion-garantias.blade.php` - Reescrito: lista garantias con badges estado, cobrable, acciones por estado y rol
- `resources/views/ordenes/show.blade.php` - Modal #modalRegistrarGarantia (Alpine.js x-data cobrable), rutas JS garantias
- `public/js/orden-detalle.js` - 4 funciones: abrirModalGarantia, registrarGarantia, cambiarEstadoGarantia, asignarOperarioGarantia
- `resources/views/layouts/navigation-vertical.blade.php` - Menu: "Garantias" (Admin|Recepcion), "Garantias Asignadas" (Operario)
- `app/Http/Controllers/OrdenController.php` - Eager loading garantias.registradoPorUsuario en show()

**Checklist:**
- [x] Boton "Registrar Garantia" en detalle de orden (solo Admin/Recepcion, solo si hay piezas entregadas)
- [x] Modal formulario: seleccionar pieza (AJAX), cantidad devuelta (max dinamico), motivo, cobrable + monto (Alpine x-show), operario (opcional)
- [x] Validacion cantidad: no excede cantidad_entregada - garantias activas (no reentregadas)
- [x] Estados maquina: abierta -> en_proceso -> completada -> reentregada (transiciones estrictas)
- [x] Vista garantias en detalle de orden con badges estado, cobrable, operario, fechas, botones accion por estado
- [x] Asignar operario via SweetAlert select (pasa automaticamente a en_proceso si estaba abierta)
- [x] Operario ve "Garantias Asignadas" con DataTable y boton Completar (solo sus garantias)
- [x] Admin/Recepcion marcan reentregada (estado terminal, sin acciones)
- [x] Pagina /recepcion/garantias con DataTable server-side, 4 stat cards, filtro por estado
- [x] Registrar actividad para cada transicion de estado
- [x] Menu lateral actualizado para ambos roles
- [x] Layout responsive (flex-wrap en badges y acciones)

**Nota:** El monto cobrable es solo informativo en esta fase. No afecta el saldo financiero de la orden. Pendiente definir si en futuro se suma al total de la orden o se maneja por separado.

---

### FASE 12: Tabla de Precios Parametrica ✅ COMPLETADO (2026-03-12)

> **Objetivo:** Administracion y consulta de precios por servicio/calibre/largo/cantidad.
> **Completado:** 2026-03-12

**Archivos creados:**
- `app/Http/Controllers/Admin/TablaPreciosController.php` - 8 metodos: index (vista+AJAX grid), updatePrecios, servicios, storeServicio, updateServicio, destroyServicio, exportExcel, importExcel
- `app/Http/Controllers/ConsultaPrecioController.php` - 2 metodos: index, consultar (AJAX lookup)
- `resources/views/admin/tabla-precios/index.blade.php` - Vista spreadsheet con Alpine.js, grid 13x6 editable, modales Import/Gestionar Servicios, stat cards
- `resources/views/consulta-precios/index.blade.php` - Vista consulta rapida con Alpine.js, resultado en formato COP grande
- `app/Exports/TablaPreciosExport.php` - Export Excel con filtro opcional por tipo_servicio, header verde #4A7C59
- `app/Imports/TablaPreciosImport.php` - Import Excel con upsert por clave compuesta (tipo_servicio+calibre+largo+cantidad)

**Archivos modificados:**
- `app/Models/TablaPrecioServicio.php` - Agregados 2 scopes (forServicio, forLargoRange) + 5 static helpers (getDistinctServicios, getDistinctLargoRangos, getDistinctCantidadRangos, getDistinctCalibres, lookup)
- `routes/web.php` - 10 rutas nuevas (8 admin tabla-precios + 2 recepcion consulta-precios), 2 use statements
- `resources/views/layouts/navigation-vertical.blade.php` - Activados links "Consulta Precios" (Catalogos) y "Tabla de Precios" (Administracion)

**Checklist:**
- [x] Vista tipo spreadsheet para editar precios masivamente (grid 13 calibres x 6 rangos cantidad, inputs editables, badge cambios sin guardar)
- [x] CRUD de tipos de servicio (crear genera 312 registros automaticos, editar etiqueta/precio_minimo, eliminar con SweetAlert)
- [x] Seeder con 1,872 registros (6 tablas precargadas) - ya existia de Fase 0
- [x] Importar Excel (primer import del proyecto, app/Imports/ creado, upsert por clave compuesta)
- [x] Exportar Excel (filtrable por tipo_servicio, formato plano compatible con import)
- [x] Consulta de precios: seleccionar tipo servicio, calibre, largo, cantidad -> muestra precio en formato COP grande
- [x] Registrar cambios en registro_actividades (5 tipos: precios_actualizados, servicio_creado/actualizado/eliminado, importacion)
- [x] Rutas: `/admin/tabla-precios` (8 rutas), `/recepcion/consulta-precios` (2 rutas)
- [x] Agregar al menu lateral (ambos links activados, antes eran placeholders disabled)

**Nota:** La tabla de precios es una herramienta de referencia/cotizacion. No se conecta automaticamente con los precios de las ordenes. Recepcion consulta la tabla para saber el precio por calibre/largo/cantidad, y luego usa ese valor como referencia al crear la orden con items del catalogo.

---

### FASE 13: Administracion y Configuracion ✅ COMPLETADO (2026-03-12)

> **Objetivo:** Pantalla de configuracion del sistema (parametros clave-valor).

**Archivos creados:**
- `app/Http/Controllers/Admin/ConfiguracionController.php`

**Archivos modificados:**
- `resources/views/admin/configuracion/index.blade.php` (reemplazo de placeholder)
- `app/Models/ConfiguracionSistema.php` (cache 5min en get(), clear en set())
- `routes/web.php` (rutas controller reemplazando closure)
- `app/Services/BloqueoService.php` (desactivado haExpirado(), bloqueos no expiran)
- `public/js/gva-main.js` (alerts warning duran 60s en vez de 5s)

**Checklist:**
- [x] Formulario de configuracion con todos los parametros
- [x] 6 Secciones colapsables: Empresa (nombre, logo upload/delete, direccion, telefono, NIT), Financiero (IVA, Nequi tags), Sistema/Operario (autoguardado, forzar cierre), Borradores (dias expiracion, dias recientes), Catalogos (materiales tags, calibres tabla editable), Otros (cliente predeterminado)
- [x] Guardar/cargar desde tabla configuracion_sistema via AJAX + SweetAlert2
- [x] ConfiguracionSistema::get('clave', 'default') con auto-casting por tipo
- [x] Cache de 5 minutos en get(), invalidacion automatica en set()
- [x] Rutas: GET/POST `/admin/configuracion`, POST/DELETE `/admin/configuracion/logo`
- [x] Logo empresa: upload separado via FormData, preview, eliminar con confirmacion
- [x] Registro de actividad al guardar configuraciones

**Decisiones tomadas:**
- `timeout_inactividad_operario`: Removido de la UI y desactivado server-side (haExpirado() retorna false). Los bloqueos solo se liberan por desbloqueo manual, forzar cierre o cierre de sesion
- `usuario_notificar_baja_porcentaje`: Removido de la UI. La notificacion ya esta hardcodeada a roles Administrador+Contabilidad en OperarioPiezaService
- Alerts tipo warning (amarillos) ahora duran 60 segundos en vez de 5

---

### FASE 14: Dashboards por Rol ✅ COMPLETADO (2026-03-12)

> **Objetivo:** Dashboards especificos con widgets clickeables.

**Archivos creados:**
- `app/Services/DashboardService.php` — Servicio centralizado de metricas para los 4 roles
- `app/Http/Controllers/Recepcion/PanelController.php` — Controller invocable para recepcion
- `app/Http/Controllers/Admin/PanelController.php` — Controller invocable para admin
- `resources/views/admin/panel.blade.php` — Vista dashboard admin (7 stats + acciones + admin links)

**Archivos modificados:**
- `resources/views/recepcion/panel.blade.php` — Reconstruida: 6 widgets reales + acciones rapidas + garantias + catalogos
- `resources/views/operario/panel.blade.php` — Agregado stat-card y quick-action de garantias
- `resources/views/contabilidad/panel.blade.php` — Agregada card de garantias cobrables (condicional)
- `app/Http/Controllers/OperarioController.php` — Inyecta DashboardService para garantias operario
- `app/Http/Controllers/ContabilidadController.php` — Inyecta DashboardService para garantias cobrables
- `app/Services/Auth/RoleService.php` — Admin redirige a `admin.panel` en vez de `admin.configuracion`
- `routes/web.php` — Ruta recepcion usa controller, nueva ruta `admin/panel`, imports agregados
- `resources/views/layouts/navigation-vertical.blade.php` — Active state incluye `admin.panel`

**Checklist:**
- [x] Dashboard Recepcion (6 widgets): Entregas pendientes hoy (warning), Hoy/manana (warning), Vencidas (danger), Ordenes abiertas (primary), Saldo pendiente (danger), Para complementar (info)
- [x] Cada widget clickeable lleva a listado filtrado
- [x] Refinar Dashboard Operario: agregado stat-card garantias pendientes + quick-action "Mis Garantias" con badge
- [x] Refinar Dashboard Contabilidad: agregada card garantias cobrables con count y monto
- [x] Dashboard Admin creado: 7 stats (ordenes activas, entregas vencidas, saldo, recaudado hoy, garantias, pagos por aprobar, ordenes nuevas) + acciones rapidas + panel admin
- [x] Dashboard general `/dashboard` redirige segun rol (Admin→admin/panel, Recepcion→recepcion/panel, Contabilidad→contabilidad/panel, Operario→operario/panel)
- [x] Garantias integradas en los 4 dashboards via DashboardService

---

### FASE 15: Notificaciones ✅ COMPLETADO (2026-03-12)

> **Objetivo:** Sistema de notificaciones internas con icono campana.
> **Completado:** 2026-03-12

**Archivos creados:**
- `app/Http/Controllers/NotificacionController.php` — 3 metodos: index (JSON ultimas 50), destroy, marcarLeidas
- `app/Services/NotificacionService.php` — Servicio centralizado con 2 metodos genericos (notificar, notificarRoles) + 9 helpers por evento
- `public/js/notificaciones.js` — Polling cada 10s, toasts, panel dropdown, marcar leidas, eliminar individual
- `public/css/sinden-components.css` (seccion notificaciones) — Bell, badge, panel, toast, responsive

**Archivos modificados:**
- `resources/views/layouts/app.blade.php` — Campana con badge en header + toast container
- `app/Http/Controllers/GarantiaController.php` — 4 llamadas: garantiaRegistrada, garantiaAsignada, garantiaCompletada, garantiaReentregada
- `app/Http/Controllers/OrdenController.php` — 1 llamada: abonoPendienteAprobacion (solo si Recepcion registra pago)
- `app/Http/Controllers/ContabilidadController.php` — 3 llamadas: pagoAprobado (individual+masivo), pagoRechazado
- `app/Services/OrdenService.php` — 1 llamada: ordenGenerada (notifica operario asignado)
- `app/Services/OperarioPiezaService.php` — 1 llamada: piezaCompletada (al llegar a 100%)
- `app/Services/BloqueoService.php` — Notificacion forzar_cierre (pre-existente)
- `app/Http/Controllers/EntregaController.php` — Notificacion entrega_sin_avance (pre-existente)
- `routes/web.php` — 3 rutas: GET /notificaciones, DELETE /notificaciones/{id}, POST /notificaciones/marcar-leidas

**Checklist:**
- [x] Icono campana en header con badge de no leidas
- [x] Dropdown con listado de notificaciones recientes (ultimas 50)
- [x] Marcar como leida al click
- [x] Marcar todas como leidas
- [x] Polling AJAX cada 10 segundos con toasts para nuevas notificaciones
- [x] Eliminar notificacion individual
- [x] Agregar campana al layout `app.blade.php`
- [x] Eventos que generan notificacion (13 tipos totales):

| Tipo | Evento | Destinatarios | Origen |
|------|--------|---------------|--------|
| `forzar_cierre` | Cierre de orden requerido | Operario bloqueador | BloqueoService |
| `avance_disminuido` | Operario baja porcentaje | Admin + Contabilidad | OperarioPiezaService |
| `entrega_sin_avance` | Entrega con 0% avance | Admin + Contabilidad | EntregaController |
| `garantia_registrada` | Nueva garantia creada | Administrador | GarantiaController::store |
| `garantia_asignada` | Operario asignado a garantia | Operario asignado | GarantiaController::store/asignarOperario |
| `garantia_completada` | Trabajo garantia completado | Admin + Recepcion | GarantiaController::completarTrabajo/cambiarEstado |
| `garantia_reentregada` | Garantia cobrable reentregada | Contabilidad | GarantiaController::cambiarEstado |
| `abono_pendiente_aprobacion` | Pago registrado por Recepcion | Admin + Contabilidad | OrdenController::agregarPago |
| `pago_aprobado` | Pago aprobado | Quien registro el pago | ContabilidadController::aprobarPago/Masivo |
| `pago_rechazado` | Pago rechazado | Quien registro el pago | ContabilidadController::rechazarPago |
| `orden_generada` | Orden generada con operario | Operario asignado | OrdenService::generarOrden |
| `pieza_completada` | Pieza llega al 100% | Admin + Recepcion | OperarioPiezaService::actualizarAvances |

**Nota:** `borrador_expirando` se implementara en FASE 18 (Limpieza de Borradores) ya que requiere comando programado.

---

### FASE 16: Registro de Actividades (Vistas) ✅ COMPLETADO (2026-03-12)

> **Objetivo:** Vistas de auditoria por usuario y global + completar logging de TODAS las acciones del sistema.

**Archivos creados:**
- `app/Http/Controllers/ActividadController.php` (metodos personal() y global() con Yajra DataTables server-side)
- `resources/views/actividades/index.blade.php` (vista personal: 4 stat cards, filtros, DataTable 4 columnas)
- `resources/views/actividades/global.blade.php` (vista global: 4 stat cards, filtros + usuario, DataTable 6 columnas)
- `public/js/actividades.js` (initActividadesTable() con config personal/global)

**Archivos modificados (logging faltante agregado):**
- `app/Http/Controllers/Admin/UserController.php` - +trait RegistraActividad, +3 logs: usuario.creado/actualizado/eliminado
- `app/Http/Controllers/Admin/ConfiguracionController.php` - +2 logs: configuracion.logo_actualizado/logo_eliminado
- `app/Http/Controllers/OrdenController.php` - +1 log: orden.comentario_agregado
- `app/Http/Controllers/EntregaController.php` - +1 log: entrega.foto_subida
- `app/Http/Controllers/OperarioController.php` - +1 log: pieza.foto_subida
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` - +1 log: usuario.cierre_sesion (antes de logout)
- `app/Models/RegistroActividad.php` - +constante TIPOS_ACCION (41 tipos), +COLORES_CATEGORIA, +badgeAccion()
- `routes/web.php` - +import ActividadController, +6 rutas (4 personal + 2 global)
- `resources/views/layouts/navigation-vertical.blade.php` - placeholders reemplazados por links activos

**Checklist:**
- [x] Vista particular: filtrada por usuario actual, DataTable con Fecha/Hora, Accion (badge color), Orden (link), Detalle
- [x] Filtros por fecha (desde/hasta) y tipo de accion (dropdown con 41 tipos)
- [x] Vista global (Recepcion + Admin): columnas extra Usuario y Rol (con badges de color por rol)
- [x] Filtro adicional por usuario en vista global
- [x] Rutas: `/recepcion/actividades`, `/operario/actividades`, `/contabilidad/actividades`, `/admin/actividades`, `/recepcion/actividades-globales`, `/admin/actividades-globales`
- [x] Menu lateral actualizado con links activos y highlight de ruta actual
- [x] Completado logging de 9 acciones faltantes (usuarios CRUD, logo, comentarios, fotos, logout)
- [x] 41 tipos de accion registrados cubriendo TODAS las mutaciones de BD del sistema

**Acciones intencionalmente NO registradas:**
- Notificaciones (marcar leidas/eliminar): operaciones de UI sin impacto en datos de negocio
- Bloqueos/heartbeats: operaciones transitorias de alta frecuencia (cada 30s)

---

### FASE 17: Manejo de Conexion ✅ COMPLETADO (2026-03-12)

> **Objetivo:** Indicador online/offline, backup en localStorage, sincronizacion.

**Archivos creados:**
- `public/js/conexion-handler.js` - Modulo global SindenConexion (~350 lineas): deteccion dual (navigator.onLine + ping activo), intercepcion AJAX via $.ajaxPrefilter, cola localStorage, sincronizacion al reconectar
- `public/css/conexion-handler.css` - Estilos banner offline, indicador de conexion, botones disabled

**Archivos modificados:**
- `resources/views/layouts/app.blade.php` - Banner offline, indicador verde/rojo en header, carga CSS/JS
- `routes/web.php` - 2 rutas: GET /api/ping (verificacion conectividad), GET /api/csrf-refresh (token fresco)
- `public/js/operario-trabajo.js` - 3 hooks: backup piezasCambios en trackCambio(), limpiar en save exitoso, recuperar en init con SweetAlert
- `public/js/orden-wizard.js` - 3 hooks: backup formData antes de AJAX, limpiar en save exitoso, recuperar borrador en init con SweetAlert

**Arquitectura:**
- Deteccion: navigator.onLine (instantanea) + ping activo GET /api/ping cada 15s (online) / 5s (offline)
- Intercepcion: $.ajaxPrefilter global - encola POST/PUT/DELETE JSON, bloquea FormData (fotos), aborta GETs
- localStorage: prefijo `sinden_cx_`, keys: queue (cola FIFO), operario_{ordenId} (porcentajes), wizard_{ordenId} (formulario)
- Sincronizacion: 1) Refrescar CSRF, 2) Procesar cola FIFO, 3) Resumen toast
- Recuperacion: Al cargar pagina detecta datos huerfanos, muestra dialogo "Datos recuperados / Descartar"
- Excepciones: Ignora heartbeat, DataTables y notificaciones en deteccion de errores
- Garantias: cubiertas automaticamente por interceptor global (POST JSON se encola)

**Checklist:**
- [x] Indicador visual en header (verde=online, rojo=offline con pulso CSS)
- [x] Banner "Sin conexion a internet. Los cambios se guardaran localmente."
- [x] Guardar datos de formularios en localStorage cuando offline (operario + wizard)
- [x] Deshabilitar botones guardar/generar sin conexion (data-offline-disabled)
- [x] Auto-envio de datos pendientes al reconectar (con refresh CSRF previo)
- [x] Manejo de conflictos: "Se encontraron datos no guardados. Desea recuperarlos?"
- [x] Guardar porcentajes del operario localmente y sincronizar
- [x] Manejo de CSRF expirado (auto-refresh en 419)
- [x] Manejo de sesion expirada (redirect a login en 401)
- [x] Fotos: mensaje claro "intente de nuevo" (no se encolan binarios)

---

### FASE 18: Limpieza de Borradores ✅ COMPLETADO (2026-03-12)

> **Objetivo:** Tarea programada para eliminar borradores expirados.

**Archivos creados:**
- `app/Console/Commands/LimpiarBorradores.php` - Comando artisan con opciones --dry-run y --force

**Archivos modificados:**
- `app/Console/Kernel.php` - Schedule diario a medianoche
- `app/Services/NotificacionService.php` - Helper borradorExpirando()
- `app/Models/RegistroActividad.php` - Tipo `sistema.borradores_eliminados` + color categoria `sistema`
- `app/Http/Controllers/OrdenController.php` - Badge expiracion en DataTable para borradores

**Funcionalidades:**
- Comando `ordenes:limpiar-borradores` con opciones `--dry-run` (solo listar) y `--force` (sin confirmacion)
- Lee `dias_expiracion_borradores` de ConfiguracionSistema (default 30 dias)
- Eliminacion en cascada: items, bosquejos, piezas (con historial/asignaciones/fotos/garantias), pagos, comentarios, actividades, entregas, archivos fisicos
- Notificacion `borrador_expirando` al creador 3 dias antes de expirar
- Registro `sistema.borradores_eliminados` con IDs y conteo en datos_extra
- Badge en DataTable ordenes: rojo "Expira en Xd" (<=3 dias), amarillo (<=7 dias)
- Programado diariamente a las 00:00 en Kernel.php

**Checklist:**
- [x] Comando artisan `ordenes:limpiar-borradores`
- [x] Eliminar ordenes con estado_trabajo='borrador' y updated_at < ahora - dias_configurados
- [x] Eliminar en cascada: orden_items, orden_piezas, orden_bosquejos, pagos, comentarios, fotos, actividades, entregas
- [x] Eliminar archivos fisicos en public/uploads/ordenes/{id}/
- [x] Registrar en registro_actividades: `sistema.borradores_eliminados`
- [x] Programar en `app/Console/Kernel.php` (diario a medianoche)
- [x] Notificacion `borrador_expirando` al creador (3 dias antes)
- [x] En UI: badge "Expira en X dias" para borradores proximos (rojo <= 3d, amarillo <= 7d)

---

## RUTAS POR ROL

### Recepcion
```
/recepcion/panel                        (dashboard)
/recepcion/ordenes/crear                (wizard creacion)
/recepcion/ordenes                      (buscar/listar)
/recepcion/ordenes/{id}                 (ver detalle)
/recepcion/ordenes/{id}/editar          (editar)
/recepcion/entregas-pendientes          (entregas)
/recepcion/clientes                     (CRUD clientes)
/recepcion/items                        (catalogo items)
/recepcion/bosquejos-matriz             (biblioteca bosquejos)
/recepcion/consulta-precios             (consultar precios)
/recepcion/actividades                  (mis actividades)
/recepcion/actividades-globales         (todas las actividades)
```

### Operario
```
/operario/panel                         (dashboard)
/operario/ordenes-asignadas             (mis ordenes)
/operario/ordenes/{id}                  (vista de trabajo)
/operario/buscar                        (buscar orden)
/operario/complementar                  (piezas sin operario)
/operario/actividades                   (mis actividades)
```

### Contabilidad
```
/contabilidad/panel                     (dashboard)
/contabilidad/ordenes-pendientes        (ordenes con saldo)
/contabilidad/items                     (catalogo items)
/contabilidad/actividades               (mis actividades)
```

### Administrador
```
/admin/configuracion                    (configuracion sistema)
/admin/usuarios                         (gestion usuarios)
/admin/tabla-precios                    (tabla precios parametrica)
```

### Compartidas
```
/dashboard                              (redirect segun rol)
/ordenes/{id}/pdf                       (generar PDF)
/notificaciones                         (ver notificaciones)
```

---

## NAVEGACION VERTICAL POR ROL

```blade
{{-- Dashboard (todos) --}}
<a href="...">Inicio</a>

@hasanyrole('Administrador|Recepcion')
  {{-- SECCION RECEPCION --}}
  <div class="nav-section-title">Ordenes</div>
  <a>Crear Orden</a>
  <a>Buscar Ordenes</a>
  <a>Entregas Pendientes</a>

  <div class="nav-section-title">Catalogos</div>
  <a>Clientes</a>
  <a>Items</a>
  <a>Bosquejos Matriz</a>
  <a>Consulta Precios</a>
@endhasanyrole

@role('Operario')
  {{-- SECCION OPERARIO --}}
  <div class="nav-section-title">Mi Trabajo</div>
  <a>Ordenes Asignadas</a>
  <a>Buscar Orden</a>
  <a>Complementar Ordenes</a>
@endrole

@role('Contabilidad')
  {{-- SECCION CONTABILIDAD --}}
  <div class="nav-section-title">Finanzas</div>
  <a>Ordenes Pendientes</a>
  <a>Items</a>
@endrole

@role('Administrador')
  {{-- SECCION ADMIN --}}
  <div class="nav-section-title">Administracion</div>
  <a>Usuarios</a>
  <a>Configuracion</a>
  <a>Tabla de Precios</a>
@endrole

{{-- SECCION COMUN --}}
<div class="nav-section-title">Sistema</div>
<a>Mis Actividades</a>
@hasanyrole('Administrador|Recepcion')
  <a>Actividades Globales</a>
@endhasanyrole

{{-- SECCION CUENTA --}}
<div class="nav-section-title">Cuenta</div>
<a>Mi Perfil</a>
```

---

## TECNOLOGIAS Y LIBRERIAS

### Backend
- Laravel 9 (PHP 8.0+)
- MySQL/MariaDB
- Spatie Laravel Permission (roles y permisos)

### Frontend
- Bootstrap 5
- jQuery
- SweetAlert2
- DataTables (con botones de exportacion)
- Alpine.js
- Tailwind CSS (con preflight deshabilitado)

### Generacion de PDFs
- DomPDF

### Otros
- Carbon (fechas)
- Laravel Excel (exportaciones)
- Chart.js (para dashboards)

---

## ESTRUCTURA DE CARPETAS

```
app/
├── Console/
│   └── Commands/
│       └── LimpiarBorradores.php
├── Exports/
│   ├── ClientesExport.php
│   ├── OrdenesExport.php
│   └── TablaPreciosExport.php
├── Http/
│   ├── Controllers/
│   │   ├── Auth/                       (autenticacion)
│   │   ├── Admin/
│   │   │   ├── UserController.php
│   │   │   ├── ConfiguracionController.php
│   │   │   └── TablaPreciosController.php
│   │   ├── Contabilidad/
│   │   │   ├── PanelController.php
│   │   │   └── PagoController.php
│   │   ├── Operario/
│   │   │   ├── PanelController.php
│   │   │   └── OrdenTrabajoController.php
│   │   ├── Recepcion/
│   │   │   └── PanelController.php
│   │   ├── ActividadController.php
│   │   ├── BosquejoMatrizController.php
│   │   ├── CatalogoItemController.php
│   │   ├── ClienteController.php
│   │   ├── ConsultaPrecioController.php
│   │   ├── Controller.php
│   │   ├── EntregaController.php
│   │   ├── GarantiaController.php
│   │   ├── NotificacionController.php
│   │   ├── OrdenController.php
│   │   ├── OrdenPdfController.php
│   │   └── ProfileController.php
│   └── Middleware/
├── Imports/
│   └── TablaPreciosImport.php
├── Models/
│   ├── AsignacionPieza.php
│   ├── CatalogoItem.php
│   ├── Cliente.php
│   ├── ConfiguracionSistema.php
│   ├── DevolucionGarantia.php
│   ├── GrupoBosquejo.php
│   ├── HistorialAvance.php
│   ├── Notificacion.php
│   ├── Orden.php
│   ├── OrdenBosquejo.php
│   ├── OrdenComentario.php
│   ├── OrdenFoto.php
│   ├── OrdenItem.php
│   ├── OrdenPieza.php
│   ├── Pago.php
│   ├── PlantillaBosquejo.php
│   ├── RegistroActividad.php
│   ├── TablaPrecioServicio.php
│   └── User.php
├── Services/
│   ├── Auth/
│   │   └── RoleService.php
│   ├── DashboardService.php
│   ├── NotificacionService.php
│   ├── OrdenEstadoService.php
│   ├── OrdenService.php
│   └── PiezaService.php
├── Traits/
│   └── RegistraActividad.php
└── View/
    └── Components/

database/
├── migrations/                         (base + 18 nuevas)
└── seeders/
    ├── ConfiguracionSistemaSeeder.php
    ├── DatabaseSeeder.php
    ├── RolesAndPermissionsSeeder.php
    └── TablaPreciosSeeder.php

resources/
└── views/
    ├── actividades/
    │   ├── global.blade.php
    │   └── index.blade.php
    ├── admin/
    │   ├── configuracion/
    │   │   └── index.blade.php
    │   ├── tabla-precios/
    │   │   └── index.blade.php
    │   └── users/
    │       └── index.blade.php
    ├── auth/
    ├── bosquejos-matriz/
    │   └── index.blade.php
    ├── catalogo-items/
    │   ├── create.blade.php
    │   ├── edit.blade.php
    │   └── index.blade.php
    ├── clientes/
    │   ├── create.blade.php
    │   ├── edit.blade.php
    │   ├── index.blade.php
    │   └── show.blade.php
    ├── components/
    │   ├── sinden/                     (componentes reutilizables)
    │   └── (componentes Breeze)
    ├── contabilidad/
    │   ├── ordenes-pendientes.blade.php
    │   └── panel.blade.php
    ├── consulta-precios/
    │   └── index.blade.php
    ├── entregas/
    │   ├── flujo.blade.php
    │   └── pendientes.blade.php
    ├── garantias/
    │   ├── create.blade.php
    │   └── show.blade.php
    ├── layouts/
    │   ├── app.blade.php
    │   ├── guest.blade.php
    │   └── navigation-vertical.blade.php
    ├── operario/
    │   ├── buscar.blade.php
    │   ├── complementar.blade.php
    │   ├── orden-trabajo.blade.php
    │   ├── ordenes-asignadas.blade.php
    │   └── panel.blade.php
    ├── ordenes/
    │   ├── create.blade.php
    │   ├── edit.blade.php
    │   ├── index.blade.php
    │   ├── show.blade.php
    │   ├── partials/
    │   │   ├── _seccion-bosquejos-piezas.blade.php
    │   │   ├── _seccion-cliente.blade.php
    │   │   ├── _seccion-fechas.blade.php
    │   │   ├── _seccion-firma.blade.php
    │   │   ├── _seccion-items.blade.php
    │   │   ├── _seccion-operario.blade.php
    │   │   └── _seccion-pagos.blade.php
    │   ├── show/
    │   │   ├── _seccion-encabezado.blade.php
    │   │   ├── _seccion-cliente.blade.php
    │   │   ├── _seccion-fechas.blade.php
    │   │   ├── _seccion-items.blade.php
    │   │   ├── _seccion-bosquejos.blade.php
    │   │   ├── _seccion-piezas.blade.php
    │   │   ├── _seccion-pagos.blade.php
    │   │   ├── _seccion-firma.blade.php
    │   │   ├── _seccion-fotos.blade.php
    │   │   ├── _seccion-comentarios.blade.php
    │   │   └── _seccion-garantias.blade.php
    │   └── pdf/
    │       └── orden.blade.php
    ├── partials/
    │   └── _campana-notificaciones.blade.php
    ├── profile/
    ├── recepcion/
    │   └── panel.blade.php
    ├── dashboard.blade.php
    └── welcome.blade.php

public/
├── css/
│   ├── gva-global.css
│   ├── gva-dashboard.css
│   ├── gva-components.css
│   └── sinden-components.css
├── js/
│   ├── gva-main.js
│   ├── orden-wizard.js
│   ├── orden-detalle.js
│   ├── orden-edit-init.js
│   ├── firma-canvas.js
│   ├── dibujo-canvas.js
│   ├── operario-trabajo.js
│   └── conexion-handler.js
├── images/
└── uploads/
    ├── bosquejos-matriz/
    ├── ordenes/
    ├── profile-photos/
    └── empresa/
```

---

## AGREGAR NUEVAS FUNCIONALIDADES

1. Model en `app/Models/`
2. Migration en `database/migrations/`
3. Controller en `app/Http/Controllers/`
4. Service en `app/Services/` (para logica de negocio compleja)
5. Routes en `routes/web.php`
6. Views en `resources/views/`
7. Actualizar navegacion en `resources/views/layouts/navigation-vertical.blade.php`

---

## MENSAJES DEL SISTEMA

| Trigger | Mensaje | Tipo |
|---------|---------|------|
| Generar sin info completa | "Falta diligenciar informacion para poder GENERAR ORDEN" | Error |
| Confirmar generacion | "Esta seguro de generar orden?" | Confirmacion |
| Orden generada | "La orden ha sido generada con numero #XXXX" | Exito |
| Orden guardada | "La orden ha sido guardada exitosamente." | Exito |
| Auto-guardado | "La orden se guardo automaticamente como borrador." | Info |
| Pieza al 100% | "Esta seguro de colocar terminado esta Pieza [nombre]?" | Confirmacion |
| Toda la orden al 100% | "Esta seguro de colocar la Orden #XXXX como EJECUTADA?" | Confirmacion |
| Sin cambio de % | "No se modifico porcentaje de alguna pieza, esta seguro que no hizo algun avance?" | Advertencia |
| Forzar cierre al operario | "La orden necesita ser cerrada para actualizar. Un usuario de rango mayor necesita editarla." | Notificacion |
| Cierre forzado | "La orden fue cerrada por el sistema. Su progreso se guardo automaticamente." | Info |
| Cierre por inactividad | "La sesion de esta orden se cerro por inactividad." | Info |
| Foto adjuntada | "Esta bien la foto?" -> "Aceptar" / "Repetir" | Confirmacion |
| Sin conexion | "Sin conexion a internet. Los cambios se guardaran cuando se restablezca la conexion." | Advertencia |
| Reconexion | "Se encontraron datos no guardados. Desea recuperarlos?" | Confirmacion |
| Borrador expirando | "Este borrador expira en X dias." | Info |
