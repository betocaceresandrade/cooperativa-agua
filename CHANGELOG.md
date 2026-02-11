# CHANGELOG - Sistema Cooperativa de Agua

Todos los cambios notables del sistema están documentados en este archivo.

---

## [1.2.0] - 2026-02-10

### Agregado
- **Eximir meses**: Posibilidad de eximir meses específicos del cobro en consumos
- **Eliminar gastos**: Botón de eliminar en la lista de gastos con reversión de caja
- **Fondos a rendir en Estado de Resultados**: Sección con entregado/rendido/pendiente
- **Favicon**: Icono de la cooperativa en la pestaña del navegador
- **Créditos**: "v1.2.0 por boliviaimpuestos.com" en el sidebar

### Corregido
- **Notificaciones vacías**: Corregido nombre de tabla (tarifas→tipos_tarifa) y columna (tarifa_id→tipo_tarifa_id)
- **Notificaciones con 1 mes**: Corregido HAVING > 1 a >= 1
- **Anular ingresos**: Corregido nombre de columna (metodo_pago→metodo) en movimientos_caja
- **Registrar otros ingresos**: Cambiado tabla recibos a pagos con columnas correctas
- **Revertir exención**: Cambiado execute() a update() para evitar error 500
- **Logo en recibos y menú**: Corregido logo vacío, añadido LOGO_BASE64

### Mejorado
- **Backup**: Ahora descarga archivo .sql directamente (headers antes de HTML)
- **Fuentes en gastos imprimir**: Aumentado tamaño de letra para legibilidad
- **Recibos**: Muestra otros ingresos cobrados en el detalle

### Limpieza de datos
- Eliminada socia de prueba "PATRICIA 2" y sus acciones
- Eliminados todos los datos de prueba de 2026 (pagos, gastos, fondos, movimientos)
- Reiniciado correlativo de recibos a 0

---

## [2.0.0] - 2026-02-04

### Agregado

#### Sistema de Cobro de 12 Meses
- Nuevo módulo `/modules/consumos/` con interfaz de cobro por acción
- Grilla visual de 12 meses con selección múltiple
- Estados visuales: Pagado (verde), Pendiente (amarillo), No cobrable (gris)
- Botón "Seleccionar pendientes" para cobro rápido
- Soporte para cobrar años adelantados
- API para marcar meses como no cobrables

#### Módulo de Configuración
- Nueva sección `/modules/configuracion/` con:
  - **Zonas**: CRUD de zonas geográficas
  - **Tarifas**: CRUD de tipos de tarifa con montos mensuales
  - **Categorías**: CRUD de categorías de gasto
  - **Otros Ingresos**: CRUD de items adicionales (reconexión, multas, etc.)
  - **General**: Configuración de nombre, dirección y teléfono de la cooperativa
- Validación que impide eliminar registros en uso
- Estadísticas de uso en cada sección

#### Notificaciones de Deuda
- Nuevo módulo `/modules/notificaciones/`
- Generador de avisos de deuda para impresión
- 4 notificaciones por página carta horizontal
- Cuadritos visuales de 12 meses (rojo=pendiente, gris=pagado)
- Filtros por zona, búsqueda y meses mínimo de deuda

#### Reportes Nuevos
- **Gastos Detallado**: Rendición de cuentas para asambleas con filtros y resumen por categoría

#### Comprobante de Egreso
- Nueva vista de impresión para gastos
- Diseño en rojo (diferenciado del Comprobante de Ingreso en azul)
- Incluye monto en literal, categoría y firmas

---

## [1.0.0] - Versión Inicial

- Sistema básico de gestión de cooperativa de agua
- Módulos: Socios, Pagos, Gastos, Caja, Reportes
- Autenticación de usuarios
- Respaldo de base de datos
