/**
 * JavaScript Principal
 * Cooperativa de Agua Potable "Virgen de las Nieves"
 */

$(document).ready(function() {
    // Inicializar DataTables
    if ($.fn.DataTable) {
        $('.datatable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            pageLength: 25,
            responsive: true
        });
    }

    // Toggle Sidebar
    $('#sidebarToggle').on('click', function() {
        $('.sidebar').toggleClass('show');
    });

    // Cerrar sidebar al hacer clic fuera (mobile)
    $(document).on('click', function(e) {
        if ($(window).width() < 992) {
            if (!$(e.target).closest('.sidebar, #sidebarToggle').length) {
                $('.sidebar').removeClass('show');
            }
        }
    });

    // Tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Auto-cerrar alertas
    setTimeout(function() {
        $('.alert-dismissible').fadeOut('slow');
    }, 5000);
});

/**
 * Formatear moneda
 */
function formatMoney(amount) {
    return 'Bs. ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Confirmar accion
 */
function confirmarAccion(mensaje, callback) {
    Swal.fire({
        title: 'Confirmar',
        text: mensaje,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#079FEA',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, continuar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed && typeof callback === 'function') {
            callback();
        }
    });
}

/**
 * Mostrar mensaje de exito
 */
function mostrarExito(mensaje) {
    Swal.fire({
        title: 'Exito',
        text: mensaje,
        icon: 'success',
        confirmButtonColor: '#079FEA'
    });
}

/**
 * Mostrar mensaje de error
 */
function mostrarError(mensaje) {
    Swal.fire({
        title: 'Error',
        text: mensaje,
        icon: 'error',
        confirmButtonColor: '#079FEA'
    });
}

/**
 * Calcular total de servicios seleccionados
 */
function calcularTotalServicios() {
    let total = 0;
    $('.servicio-check:checked').each(function() {
        total += parseFloat($(this).data('monto') || 0);
    });
    return total;
}

/**
 * Calcular total de items adicionales
 */
function calcularTotalItems() {
    let total = 0;
    $('.item-monto').each(function() {
        total += parseFloat($(this).val() || 0);
    });
    return total;
}

/**
 * Actualizar total general
 */
function actualizarTotalGeneral() {
    const totalServicios = calcularTotalServicios();
    const totalItems = calcularTotalItems();
    const totalGeneral = totalServicios + totalItems;

    $('#totalServicios').text(formatMoney(totalServicios));
    $('#totalItems').text(formatMoney(totalItems));
    $('#totalGeneral').text(formatMoney(totalGeneral));
}

/**
 * Seleccionar/deseleccionar todos los servicios
 */
function toggleAllServicios(checked) {
    $('.servicio-check').prop('checked', checked);
    actualizarTotalGeneral();
}

/**
 * Agregar item adicional
 */
let itemCounter = 0;
function agregarItemAdicional() {
    itemCounter++;
    const html = `
        <div class="item-adicional-row" id="item-${itemCounter}">
            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="text" class="form-control item-nombre" name="items[${itemCounter}][nombre]"
                           placeholder="Descripcion (ej: Multa, Reconexion)" required>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text">Bs.</span>
                        <input type="number" step="0.01" min="0" class="form-control item-monto"
                               name="items[${itemCounter}][monto]" placeholder="0.00"
                               onchange="actualizarTotalGeneral()" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100"
                            onclick="eliminarItem(${itemCounter})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    $('#items-container').append(html);
}

/**
 * Eliminar item adicional
 */
function eliminarItem(id) {
    $(`#item-${id}`).remove();
    actualizarTotalGeneral();
}

/**
 * Imprimir elemento
 */
function imprimirElemento(elementId) {
    const contenido = document.getElementById(elementId).innerHTML;
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Imprimir</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                .recibo { border: 2px solid #079FEA; padding: 15px; margin-bottom: 15px; }
                .recibo-header { border-bottom: 2px solid #079FEA; padding-bottom: 10px; margin-bottom: 10px; }
                .recibo-title { color: #079FEA; font-size: 14px; font-weight: bold; }
                .recibo-number { background: #079FEA; color: white; padding: 3px 10px; border-radius: 15px; }
                table { width: 100%; font-size: 11px; }
                th { background: #e8f6fd; }
                @page { margin: 10mm; }
            </style>
        </head>
        <body>
            ${contenido}
            <script>window.onload = function() { window.print(); window.close(); }<\/script>
        </body>
        </html>
    `);
    ventana.document.close();
}

/**
 * Buscar socio por nombre o numero
 */
function buscarSocio(query) {
    if (query.length < 2) return;

    $.get('/cooperativa-agua/modules/socios/buscar.php', { q: query }, function(data) {
        $('#resultados-busqueda').html(data);
    });
}

/**
 * Seleccionar socio para cobro
 */
function seleccionarSocio(socioId) {
    window.location.href = '/cooperativa-agua/modules/cobros/registrar.php?socio_id=' + socioId;
}

/**
 * Generar servicios mensuales
 */
function generarServiciosMes(periodo) {
    if (!periodo) {
        mostrarError('Seleccione un periodo');
        return;
    }

    confirmarAccion('Se generaran los servicios mensuales para ' + periodo, function() {
        $.post('/cooperativa-agua/modules/servicios/generar.php', { periodo: periodo }, function(response) {
            if (response.success) {
                mostrarExito('Se generaron ' + response.generados + ' servicios');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                mostrarError(response.error || 'Error al generar servicios');
            }
        }, 'json').fail(function() {
            mostrarError('Error de conexion');
        });
    });
}

/**
 * Exonerar servicio
 */
function exonerarServicio(servicioId) {
    Swal.fire({
        title: 'Exonerar Servicio',
        html: `
            <div class="mb-3 text-start">
                <label class="form-label">Motivo de exoneracion:</label>
                <select id="motivo-exoneracion" class="form-select">
                    <option value="Socio de baja en ese periodo">Socio de baja en ese periodo</option>
                    <option value="Autorizado por directorio">Autorizado por directorio</option>
                    <option value="Error de generacion">Error de generacion</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#079FEA',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Exonerar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            return document.getElementById('motivo-exoneracion').value;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('/cooperativa-agua/modules/cobros/exonerar.php', {
                servicio_id: servicioId,
                motivo: result.value
            }, function(response) {
                if (response.success) {
                    mostrarExito('Servicio exonerado correctamente');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    mostrarError(response.error || 'Error al exonerar');
                }
            }, 'json');
        }
    });
}

/**
 * Confirmar eliminacion
 */
function confirmarEliminar(url, mensaje) {
    mensaje = mensaje || 'Esta accion no se puede deshacer';
    confirmarAccion(mensaje, function() {
        window.location.href = url;
    });
}
