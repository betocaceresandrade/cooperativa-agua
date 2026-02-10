# Sistema de Gestión - Cooperativa de Agua Potable "Virgen de las Nieves"

Sistema web para gestionar cobros, socios y contabilidad de caja para la Cooperativa de Agua Potable "Virgen de las Nieves" en Irupana, Bolivia.

## Requisitos

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Servidor web (Apache/Nginx)
- Extensiones PHP: PDO, PDO_MySQL, JSON, Session

## Instalación

1. **Copiar archivos al servidor web**
   ```bash
   # Copiar la carpeta cooperativa-agua a /var/www/html/ o similar
   ```

2. **Configurar permisos**
   ```bash
   chmod 755 -R cooperativa-agua/
   chmod 777 cooperativa-agua/config/
   ```

3. **Ejecutar instalador**
   - Acceder a: `http://tu-servidor/cooperativa-agua/install.php`
   - Seguir los pasos del asistente
   - Ingresar datos de conexión MySQL

4. **Credenciales por defecto**
   - Usuario: `cajera`
   - Contraseña: `password`

5. **Importante: eliminar install.php después de instalar**

## Estructura del Sistema

```
/cooperativa-agua/
├── config/           # Configuración
├── includes/         # Archivos comunes
├── assets/           # CSS, JS, imágenes
├── modules/          # Módulos del sistema
│   ├── socios/       # Gestión de socios
│   ├── servicios/    # Servicios mensuales
│   ├── cobros/       # Registro de pagos
│   ├── recibos/      # Impresión de recibos
│   ├── volanditas/   # Notas de cobro
│   ├── gastos/       # Registro de gastos
│   ├── fondos-rendir/# Fondos a rendir
│   ├── caja/         # Control de caja
│   ├── reportes/     # Reportes
│   └── backup/       # Respaldo BD
└── sql/              # Scripts SQL
```

## Funcionalidades

### Gestión de Socios
- Alta, baja y modificación de socios
- Múltiples acciones (conexiones) por socio
- Cesión de acciones
- Historial completo

### Servicios y Cobros
- Generación automática de servicios mensuales
- Selección múltiple de meses a pagar
- Items adicionales (multas, reconexión, etc.)
- Exoneración de servicios
- Pago en efectivo o QR

### Recibos y Volanditas
- Impresión de recibos (2 por hoja)
- Generación de volanditas (4 por hoja)
- Selección masiva de deudores

### Control de Caja
- Saldo en efectivo y QR
- Registro de gastos
- Fondos a rendir
- Depósitos bancarios
- Movimientos detallados

### Reportes
- Estado de resultados
- Deudores por zona
- Recaudación mensual
- Listado de socios

## Tipos de Tarifa (Preconfigurados)

| Tarifa | Monto (Bs.) |
|--------|-------------|
| Domiciliaria | 12.50 |
| Domiciliaria Social | 10.00 |
| Especial | 25.00 |
| Comercial Cooperativa Alojamiento | 25.00 |
| Comercial Hotel Albergue | 75.00 |
| Comercial Oficina Pensión | 30.00 |
| Domiciliaria Inquilinos Cat. 1 | 20.00 |
| Domiciliaria Inquilinos Cat. 2 | 30.00 |
| Domiciliaria Inquilinos Cat. 3 | 40.00 |
| Domiciliaria Inquilinos Cat. 4 | 50.00 |
| Domiciliaria Inquilinos Cat. 5 | 50.00 |

## Cambiar Contraseña

Para cambiar la contraseña del usuario, ejecutar en MySQL:

```sql
-- Generar hash con password_hash('nueva_contraseña', PASSWORD_DEFAULT)
UPDATE usuarios SET password = '$2y$10$...' WHERE username = 'cajera';
```

## Soporte

Para soporte técnico, contactar al desarrollador.

---

**Versión:** 1.0.0
**Desarrollado para:** Cooperativa de Agua Potable "Virgen de las Nieves" - Irupana, Sud Yungas, La Paz, Bolivia
