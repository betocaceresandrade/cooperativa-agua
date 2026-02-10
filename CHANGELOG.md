# CHANGELOG - Sistema Cooperativa de Agua

Todos los cambios notables del sistema están documentados en este archivo.

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
- **Gastos Detallado** (`/modules/reportes/gastos-detallado.php`):
  - Rendición de cuentas para asambleas
  - Filtros por fecha, categoría y método de pago
  - Resumen por categoría con porcentajes
  - Detalle completo de cada gasto
  - Formato optimizado para impresión
  - Espacios para firmas

#### Comprobante de Egreso
- Nueva vista de impresión para gastos (`/modules/gastos/imprimir.php`)
- Diseño en rojo (diferenciado del Comprobante de Ingreso en azul)
- Incluye monto en literal, categoría y firmas

#### Base de Datos
- Nueva tabla `pago_consumos` - Vincula pagos con consumos_anuales
- Nueva tabla `otros_ingresos` - Cobros adicionales (reconexión, multas)
- Campo `accion_id` en tabla `pagos`
- Vistas: `v_acciones_completas`, `v_deudas_acciones`

### Modificado

#### Estructura Socios/Acciones
- Un socio puede tener múltiples acciones
- Cada acción tiene su propia zona y tipo de tarifa
- Las deudas se manejan por acción, no por socio
- Migrados 924 socios con 979 acciones y 30 zonas

#### Reportes Actualizados
- **Deudores** (`deudores.php`):
  - Ahora basado en acciones (no socios)
  - Filtro unificado (socio, acción, N° socio)
  - Link directo a cobrar
  - Resumen por zona

- **Socios** (`socios.php`):
  - Dos vistas: "Por Acción" y "Por Socio"
  - Filtro unificado (socio, acción, CI, N° socio)
  - Estados con badges de colores
  - Resumen por estado

#### Comprobante de Ingreso (Recibo)
- Muestra "Acción N°" en vez de "Socio N°"
- Sección "MESES COBRADOS" con badges visuales
- Incluye zona de la acción
- Monto en literal (ej: "Doce 50/100 Bolivianos")

#### Gastos
- Campo "N° Recibo/Factura del Proveedor" agregado
- Buscador por concepto o número de recibo
- Formulario unificado para crear y editar

#### Entrega de Dinero (antes Depósito Bancario)
- Renombrado a "Registro de Entrega de Dinero"
- Campo "Banco" → "Entregado a:"
- Campo "Número de Depósito" → "Número de documento"

### Cambios de Terminología
| Antes | Después |
|-------|---------|
| Dashboard | Inicio |
| Servicios | Consumo |
| Cobros | Otros Ingresos |
| Inactivo/Activo | CORTADO/ACTIVO |
| Recibo | Comprobante de Ingreso |
| (vista impresión gastos) | Comprobante de Egreso |
| Socio N° | Acción N°: |
| Registrar Depósito Bancario | Registro de entrega de dinero |
| Banco | Entregado a: |
| Número de Depósito | Número de documento |
| Backup | Respaldo |

### Estados de Socios/Acciones
- **ACTIVO** (verde) - 828 registros
- **CORTADO** (rojo) - 19 registros
- **BAJA** (gris) - 76 registros
- **SIN INST.** (azul) - 56 registros

---

## [1.0.0] - Versión Inicial

- Sistema básico de gestión de cooperativa de agua
- Módulos: Socios, Pagos, Gastos, Caja, Reportes
- Autenticación de usuarios
- Respaldo de base de datos
