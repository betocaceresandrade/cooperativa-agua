# MEMORIA - Sistema Cooperativa de Agua
## Fecha: 4 de febrero 2026

---

## ESTADO ACTUAL

### Migración de Socios - COMPLETADA
- **BD actual**: 924 socios, 979 acciones, 30 zonas
- **Estados**: 828 ACTIVO, 76 BAJA, 19 CORTADO, 56 SIN INST.
- **PDF fuente**: https://taxbo.s3.dualstack.us-east-1.amazonaws.com/%2B%20blog%20cosas/SOCIOS%20DE%20LA%20COOPERATIVA.pdf

---

## COMPLETADO - 4 Feb 2026

### [x] 1. REESTRUCTURACIÓN BASE DE DATOS
**Fecha**: 4 Feb 2026 04:15

**Cambios realizados:**
- [x] `pagos` - Agregado campo `accion_id` (para saber qué acción se pagó)
- [x] `pago_consumos` - Nueva tabla que vincula pagos con consumos_anuales
- [x] `otros_ingresos` - Nueva tabla para cobros que no son consumo (reconexión, multas, etc)
- [x] Estados normalizados a MAYÚSCULAS (ACTIVO, CORTADO, BAJA, SIN INST.)
- [x] Vistas creadas: `v_acciones_completas`, `v_deudas_acciones`

**Estructura confirmada:**
- `socios` → puede tener múltiples `acciones`
- Cada `accion` tiene su propia `zona_id` y `tipo_tarifa_id`
- `consumos_anuales` vinculado a `accion_id` (12 meses por año)
- `gastos` ya tiene campo `numero_recibo_proveedor`

**Backup creado:** `backup_antes_migracion_20260204_041027.sql`

### [x] 2. MIGRACIÓN DE SOCIOS COMPLETADA
**Fecha**: 4 Feb 2026

**Resultados:**
- 924 socios únicos migrados
- 979 acciones migradas
- 30 zonas limpias creadas
- Estados: 828 ACTIVO, 76 BAJA, 19 CORTADO, 56 SIN INST.

**Socios con múltiples acciones (ejemplos):**
- Javier Salgueiro Boyan: 5 acciones
- Sandra Mamani de Cáceres: 3 acciones
- Raúl Rojas Ramírez: 3 acciones

**Scripts creados:**
- `migrar_socios_v2.php` - Script final de migración

### [x] 3. SISTEMA DE COBRO DE 12 MESES
**Fecha**: 4 Feb 2026

**Implementado:**
- Nuevo módulo `/modules/consumos/` con:
  - `index.php` - Buscador de acciones para cobrar
  - `cobrar.php` - Interfaz de 12 meses con selección múltiple
  - `no_cobrable.php` - API para marcar meses como no cobrables

**Características:**
- Grilla visual de 12 meses por acción
- Seleccionar múltiples meses para cobrar de una vez
- Estados: Pagado (verde), Pendiente (amarillo), No cobrable (gris)
- Botón "Seleccionar pendientes" para cobro rápido
- Soporte para cobrar años adelantados
- Otros ingresos integrados (reconexión, multas, etc.)

**Tabla consumos_anuales:**
- accion_id, anio, mes, monto, estado, pago_id, motivo_no_cobrable

### [x] 4. COMPROBANTE DE INGRESO ACTUALIZADO
**Fecha**: 4 Feb 2026

**Cambios:**
- Renombrado de "Recibo" a "Comprobante de Ingreso"
- Muestra "Acción N°" en vez de "Socio N°" cuando hay acción
- Monto en literal (ej: "Doce 50/100 Bolivianos")
- Sección "MESES COBRADOS" con badges visuales
- Incluye zona de la acción
- Tamaño media carta optimizado

### [x] 5. CAMBIOS DE TERMINOLOGÍA EN MENÚ
**Fecha**: 4 Feb 2026

- Dashboard → Inicio
- Servicios → Consumo
- Cobros → Otros Ingresos
- Backup → Respaldo

### [x] 6. NOTIFICACIONES DE DEUDA
**Fecha**: 4 Feb 2026

**Implementado:**
- Lista de acciones con deuda (no socios)
- Filtros: zona, búsqueda, meses mínimo
- 4 notificaciones por página carta horizontal
- Cuadritos visuales de 12 meses (rojo=pendiente, gris=pagado)
- Incluye: nombre socio, acción N°, zona, total deuda
- Mensaje de advertencia de corte

**Archivos:**
- `/modules/notificaciones/index.php` - Selección de deudores
- `/modules/notificaciones/generar.php` - Generador de impresión

### [x] 7. REPORTES ACTUALIZADOS
**Fecha**: 4 Feb 2026

**Cambios realizados:**

**deudores.php:**
- Filtro unificado (buscar por socio, acción, N° socio)
- Basado en acciones (no socios)
- Muestra: acción N°, socio, zona, celular, meses pendientes, deuda
- Link a cobrar.php para cobro rápido
- Resumen por zona

**socios.php:**
- Filtro unificado (buscar por socio, acción, CI, N° socio)
- Dos vistas: "Por Acción" y "Por Socio"
- Vista por Acción muestra: acción, socio, CI, zona, tarifa, meses pendientes, deuda, estado
- Vista por Socio muestra: N° socio, nombre, CI, lista de acciones, cantidad, tarifa total, deuda
- Estados con badges de colores (ACTIVO verde, CORTADO rojo, BAJA gris, SIN INST. azul)
- Resumen por estado

**index.php:**
- Actualizado link "Listado de Socios" → "Socios y Acciones"
- Agregado link a Notificaciones
- Removido link obsoleto a Servicios

### [x] 8. MÓDULO DE CONFIGURACIÓN
**Fecha**: 4 Feb 2026

**Implementado:**
- Nuevo módulo `/modules/configuracion/` con:
  - `index.php` - Panel principal con acceso a todas las secciones
  - `zonas.php` - CRUD de zonas geográficas
  - `tarifas.php` - CRUD de tipos de tarifa (categorías de servicio)
  - `categorias.php` - CRUD de categorías de gasto
  - `otros-ingresos.php` - CRUD de items adicionales (reconexiones, multas, etc.)
  - `general.php` - Configuración general (nombre, dirección, teléfono)

**Características:**
- Cada sección muestra cuántos registros están en uso
- No permite eliminar registros que están siendo usados
- Estado activo/inactivo para cada registro
- Estadísticas en tarifas (recaudación potencial mensual)
- Modales para crear/editar sin recargar página

**Menú:**
- Agregado "Configuración" al sidebar antes de "Respaldo"

### [x] 9. TERMINOLOGÍA ACTUALIZADA
**Fecha**: 4 Feb 2026

**Cambios realizados:**

**Entrega de Dinero (antes Depósito Bancario):**
- `caja/deposito.php` - Renombrado a "Registro de Entrega de Dinero"
- Campo "Banco" → "Entregado a:"
- Campo "Número de Depósito" → "Número de documento"
- Botón en caja/index.php actualizado

**Comprobante de Egreso (para Gastos):**
- `gastos/imprimir.php` - Nueva vista de impresión "Comprobante de Egreso"
- Mismo estilo que Comprobante de Ingreso pero en rojo
- Incluye monto en literal, firmas, categoría

**Gastos mejorados:**
- `gastos/crear.php` - Ahora maneja crear y editar
- Agregado campo "N° Recibo/Factura del Proveedor"
- `gastos/index.php` - Muestra columna de N° recibo proveedor
- Agregado buscador por concepto o número de recibo

### [x] 10. REPORTE DE GASTOS PARA ASAMBLEAS
**Fecha**: 4 Feb 2026

**Implementado:**
- Nuevo archivo `modules/reportes/gastos-detallado.php`

**Características:**
- Filtros: rango de fechas, categoría, método de pago
- Resumen general: total, efectivo, QR, cantidad de comprobantes
- Resumen por categoría con porcentajes
- Detalle por categoría con todos los gastos
- Incluye N° recibo/factura del proveedor
- Subtotales por categoría
- Total general con período
- Formato optimizado para impresión
- Encabezado con datos de la cooperativa
- Espacios para firmas (Elaborado por / Aprobado por)

**Enlace agregado en:**
- `modules/reportes/index.php` - Nueva tarjeta "Gastos Detallado"

---

## PLAN DE CAMBIOS (del PDF "cambios aguas.pdf")

### 1. CAMBIOS DE TERMINOLOGÍA
- [x] "Dashboard" → "Inicio"
- [x] "Servicios" → "Consumo"
- [x] "Cobros" → "Otros Ingresos"
- [x] "Inactivo/Activo" → "CORTADO/ACTIVO"
- [x] "Recibo" → "Comprobante de Ingreso"
- [x] "Gastos" → "Comprobante de Egreso" (vista de impresión)
- [x] "Socio N°" → "Acción N°:" (en comprobantes)
- [x] "Registrar Depósito Bancario" → "Registro de entrega de dinero"
- [x] "Banco" → "Entregado a:"
- [x] "Número de Depósito" → "Número de documento"

### 2. SISTEMA DE COBRO MENSUAL (CAMBIO IMPORTANTE)
**Problema actual**: generar.php genera servicios mensuales, pero si un socio quiere pagar todo el año adelantado, no hay meses generados.

**Solución nueva**:
- [x] Al ingresar a cada usuario/acción, mostrar los 12 meses del año
- [x] Permitir seleccionar y cobrar múltiples meses
- [x] Mostrar visualmente qué meses están pagados y cuáles pendientes
- [x] Permitir marcar meses como "no cobrables" (socio inactivo, sin servicio, etc.)

### 3. ESTRUCTURA SOCIOS/ACCIONES (CAMBIO IMPORTANTE)
**Concepto clave**: Un socio puede tener VARIAS acciones, cada acción tiene su propia zona.

**Ejemplo del PDF**:
- Sandra Mamani de Cáceres tiene 4 acciones:
  - 98 - S en Calle Merizalde
  - 58 - S en Calle Merizalde
  - 10 - P en Calle Bolívar
  - 20 - R en Felipe Molina

**Cambios requeridos**:
- [x] La zona es característica de la ACCIÓN, no del socio
- [x] Cada acción tiene sus propias deudas
- [x] Ver deudas por acción (tiquear cada mes)
- [x] UI para manejar socios con múltiples acciones de forma rápida

### 4. FORMATO DE CÓDIGOS DE ACCIÓN
- [x] Mantener espacios: "98 - S" (espacio antes y después del guión)
- [ ] Agregar campo: Fecha de nacimiento
- [x] Agregar campo: CI (Cédula de Identidad)

### 5. COMPROBANTE DE INGRESO (RECIBO)
- [x] Tamaño: Media carta (no modificar)
- [x] Mostrar meses cobrados en la parte inferior
- [x] Decir "Acción N°:" en vez de "Socio N°"
- [x] Incluir monto en literal

### 6. NOTIFICACIONES DE DEUDA
**Modelo**: https://taxbo.s3.dualstack.us-east-1.amazonaws.com/%2B%20blog%20cosas/NOTIFICACIONES%20DE%20DEUDA.pdf

- [x] 4 notificaciones por página (tamaño carta, horizontal)
- [x] Agregar: zona y número de acción
- [x] Opción de notificar varias acciones
- [x] Cuadritos didácticos de cada mes faltante
- [x] Diseño amigable para personas mayores

### 7. REPORTES
- [x] Filtrar por: zona, socio, número de acción (un solo buscador)
- [x] Incluir código de acción en reportes
- [x] Agregar columna de código de acción donde sea necesario
- [x] Lista de meses como opción de visualización

### 8. CONFIGURACIÓN
Nueva sección para editar:
- [x] Categorías (de gasto)
- [x] Zonas
- [x] Tipos de otros ingresos (items adicionales)
- [x] Otros parámetros editables (configuración general)
- [x] Tarifas (tipos de tarifa)

### 9. CATEGORÍAS/TARIFAS
(Ver imagen en PDF para lista completa de categorías)

### 10. GASTOS
- [x] Agregar campo: Número de recibo del proveedor (ya existe en BD)
- [ ] Reporte detallado de gastos (para rendiciones en asamblea)

### 11. OTROS INGRESOS
Tipos de cargos adicionales:
- Reconexión
- Multas
- Instalaciones
- Otros conceptos varios

---

## MIGRACIÓN DE SOCIOS - COMPLETADA

1. [x] Extracción del PDF completada
2. [x] Migrados 924 socios únicos con 979 acciones
3. [x] Formato de códigos preservado (ej: "98 - S")
4. [x] Campo CI existente en tabla socios

---

## ARCHIVOS CLAVE
- `/var/www/e.taxlawbolivia.com/agua/` - Directorio principal
- `modules/consumos/cobrar.php` - Sistema de cobro de 12 meses
- `modules/consumos/index.php` - Buscador de acciones
- `modules/notificaciones/generar.php` - Generador de notificaciones de deuda
- `modules/reportes/deudores.php` - Reporte de deudores por acción
- `modules/reportes/socios.php` - Reporte de socios y acciones
- `modules/recibos/imprimir.php` - Comprobante de Ingreso
- `modules/gastos/imprimir.php` - Comprobante de Egreso
- `modules/gastos/crear.php` - Crear/editar gastos (con N° recibo proveedor)
- `modules/caja/deposito.php` - Registro de entrega de dinero
- `modules/configuracion/` - Módulo de configuración (zonas, tarifas, categorías, etc.)
- `includes/functions.php` - Funciones del sistema
- `includes/sidebar.php` - Menú lateral

---

## NOTAS UX/UI
- Usuarios no técnicos, personas mayores
- Lenguaje simple y local (La Paz, Bolivia)
- Interfaz didáctica y clara
- Evitar términos técnicos en inglés

---

## PRÓXIMOS PASOS SUGERIDOS

### COMPLETADOS:
1. ~~Reestructurar el modelo de datos para soportar múltiples acciones por socio~~
2. ~~Crear nuevo sistema de cobro mensual (12 meses seleccionables)~~
3. ~~Migrar todos los socios con el nuevo modelo~~
4. ~~Actualizar comprobantes y notificaciones~~
5. ~~Actualizar reportes~~
6. ~~Sección de Configuración (zonas, tarifas, categorías, otros ingresos)~~
7. ~~Terminología (Comprobante de Egreso, Entrega de Dinero, N° recibo proveedor)~~
8. ~~Reporte detallado de Gastos para Asambleas~~

### PENDIENTES:
(Todos los requerimientos del PDF han sido implementados)
