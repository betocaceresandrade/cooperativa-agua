<?php
/**
 * Registrar Cobro
 */

$pageTitle = 'Registrar Cobro';
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

$socio_id = intval($_GET['socio_id'] ?? 0);
$socio = getSocioById($socio_id);

if (!$socio) {
    setFlash('error', 'Socio no encontrado');
    redirect('/modules/cobros/');
}

$serviciosPendientes = getServiciosPendientesSocio($socio_id);
$itemsAdicionales = getItemsAdicionales();
$acciones = getAccionesSocio($socio_id);

// Procesar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servicios_ids = $_POST['servicios'] ?? [];
    $items = $_POST['items'] ?? [];
    $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
    $notas = sanitize($_POST['notas'] ?? '');

    // Limpiar items vacíos
    $items_limpios = [];
    foreach ($items as $item) {
        if (!empty($item['nombre']) && floatval($item['monto']) > 0) {
            $items_limpios[] = [
                'nombre' => sanitize($item['nombre']),
                'monto' => floatval($item['monto'])
            ];
        }
    }

    if (empty($servicios_ids) && empty($items_limpios)) {
        setFlash('error', 'Seleccione al menos un servicio o agregue un item');
    } else {
        $resultado = registrarPago($socio_id, $servicios_ids, $items_limpios, $metodo_pago, $notas);

        if ($resultado['success']) {
            setFlash('success', 'Pago registrado correctamente. Recibo #' . $resultado['numero_recibo']);
            redirect('/modules/recibos/imprimir.php?id=' . $resultado['pago_id']);
        } else {
            setFlash('error', 'Error al registrar pago: ' . $resultado['error']);
        }
    }
}

// Calcular totales
$totalDeuda = array_sum(array_column($serviciosPendientes, 'monto'));
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/cobros/">Cobros</a></li>
            <li class="breadcrumb-item active">Registrar</li>
        </ol>
    </nav>
    <h1 class="page-title">Registrar Cobro</h1>
</div>

<form method="POST" action="" id="form-cobro">
    <div class="row">
        <div class="col-lg-8">
            <!-- Info del Socio -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person me-2"></i>Datos del Socio</span>
                    <a href="<?= BASE_URL ?>/modules/socios/ver.php?id=<?= $socio['id'] ?>" class="btn btn-sm btn-outline-primary">
                        Ver detalle
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <small class="text-muted">N° Socio</small>
                            <div class="fw-bold fs-5"><?= htmlspecialchars($socio['numero_socio']) ?></div>
                        </div>
                        <div class="col-md-5">
                            <small class="text-muted">Nombre</small>
                            <div class="fw-bold"><?= htmlspecialchars($socio['nombre']) ?></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Zona</small>
                            <div><?= htmlspecialchars($socio['zona_nombre'] ?? '-') ?></div>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Celular</small>
                            <div><?= htmlspecialchars($socio['celular'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Servicios Pendientes -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-2"></i>Servicios Pendientes</span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" onclick="toggleAllServicios(true)">
                            Seleccionar todos
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleAllServicios(false)">
                            Ninguno
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($serviciosPendientes)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <p class="mt-2">Este socio no tiene servicios pendientes</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="40"></th>
                                    <th>Periodo</th>
                                    <th>Tipo</th>
                                    <th>Acción</th>
                                    <th class="text-end">Monto</th>
                                    <th width="40"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($serviciosPendientes as $servicio): ?>
                                <tr class="service-row" onclick="$(this).find('.servicio-check').click()">
                                    <td onclick="event.stopPropagation()">
                                        <input type="checkbox" class="form-check-input servicio-check"
                                               name="servicios[]" value="<?= $servicio['id'] ?>"
                                               data-monto="<?= $servicio['monto'] ?>"
                                               onchange="actualizarTotalGeneral()">
                                    </td>
                                    <td><strong><?= formatPeriodo($servicio['periodo']) ?></strong></td>
                                    <td><?= htmlspecialchars($servicio['tipo_nombre']) ?></td>
                                    <td><?= htmlspecialchars($servicio['numero_accion'] ?? '-') ?></td>
                                    <td class="text-end"><?= formatMoney($servicio['monto']) ?></td>
                                    <td onclick="event.stopPropagation()">
                                        <button type="button" class="btn btn-sm btn-link text-warning p-0"
                                                onclick="exonerarServicio(<?= $servicio['id'] ?>)"
                                                title="Exonerar">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="4" class="text-end"><strong>Total Deuda:</strong></td>
                                    <td class="text-end text-danger"><strong><?= formatMoney($totalDeuda) ?></strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Items Adicionales -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-plus-circle me-2"></i>Items Adicionales</span>
                    <button type="button" class="btn btn-sm btn-primary" onclick="agregarItemAdicional()">
                        <i class="bi bi-plus"></i> Agregar Item
                    </button>
                </div>
                <div class="card-body">
                    <div id="items-container">
                        <!-- Items dinámicos aquí -->
                    </div>

                    <!-- Items predefinidos -->
                    <div class="row g-2 mt-2">
                        <?php foreach ($itemsAdicionales as $item): ?>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    onclick="agregarItemPredefinido('<?= htmlspecialchars($item['nombre']) ?>', <?= $item['monto_default'] ?? 0 ?>)">
                                <?= htmlspecialchars($item['nombre']) ?>
                                <?php if ($item['monto_default']): ?>
                                (<?= formatMoney($item['monto_default']) ?>)
                                <?php endif; ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Resumen de Pago -->
            <div class="card sticky-top" style="top: 80px;">
                <div class="card-header">
                    <i class="bi bi-calculator me-2"></i>Resumen de Pago
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Servicios:</span>
                            <span id="totalServicios">Bs. 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Items adicionales:</span>
                            <span id="totalItems">Bs. 0,00</span>
                        </div>
                    </div>

                    <div class="total-box mb-4">
                        <div class="total-label">Total a Pagar</div>
                        <div class="total-value" id="totalGeneral">Bs. 0,00</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Método de Pago</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="metodo_pago" value="efectivo" id="metodo_efectivo" checked>
                            <label class="btn btn-outline-success" for="metodo_efectivo">
                                <i class="bi bi-cash me-1"></i> Efectivo
                            </label>
                            <input type="radio" class="btn-check" name="metodo_pago" value="qr" id="metodo_qr">
                            <label class="btn btn-outline-primary" for="metodo_qr">
                                <i class="bi bi-qr-code me-1"></i> QR
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notas (opcional)</label>
                        <textarea class="form-control" name="notas" rows="2"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100" id="btn-cobrar">
                        <i class="bi bi-check-circle me-2"></i>
                        Registrar Pago
                    </button>

                    <a href="<?= BASE_URL ?>/modules/cobros/" class="btn btn-outline-secondary w-100 mt-2">
                        Cancelar
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$pageScripts = <<<HTML
<script>
// Agregar item predefinido
function agregarItemPredefinido(nombre, monto) {
    itemCounter++;
    const html = `
        <div class="item-adicional-row" id="item-\${itemCounter}">
            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="text" class="form-control item-nombre" name="items[\${itemCounter}][nombre]"
                           value="\${nombre}" required>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text">Bs.</span>
                        <input type="number" step="0.01" min="0" class="form-control item-monto"
                               name="items[\${itemCounter}][monto]" value="\${monto}"
                               onchange="actualizarTotalGeneral()" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100"
                            onclick="eliminarItem(\${itemCounter})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    $('#items-container').append(html);
    actualizarTotalGeneral();
}

// Validar antes de enviar
$('#form-cobro').on('submit', function(e) {
    const servicios = $('.servicio-check:checked').length;
    const items = $('.item-monto').filter(function() { return parseFloat($(this).val()) > 0; }).length;

    if (servicios === 0 && items === 0) {
        e.preventDefault();
        mostrarError('Seleccione al menos un servicio o agregue un item adicional');
        return false;
    }

    // Confirmar
    if (!confirm('¿Confirmar el registro del pago?')) {
        e.preventDefault();
        return false;
    }
});

// Resaltar fila al seleccionar checkbox
$('.servicio-check').on('change', function() {
    $(this).closest('tr').toggleClass('selected', $(this).is(':checked'));
});
</script>
HTML;

require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>
