<?php
// admin.php - Panel de administración para empleados
require_once 'config.php';

// Verificación básica de acceso (en un sistema real esto sería más robusto)
session_start();
if (!isset($_SESSION['es_admin'])) {
    // En un sistema real, aquí iría un login proper
    $_SESSION['es_admin'] = true; // Solo para desarrollo
}

function sanitize($s) { return htmlspecialchars(trim($s)); }

$filtro_fecha = $_GET['fecha'] ?? '';
$filtro_origen = $_GET['origen'] ?? '';
$filtro_destino = $_GET['destino'] ?? '';
$vuelo_seleccionado = $_GET['vuelo_id'] ?? '';
$mensaje = '';

// Procesar cancelaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancelar_reserva'])) {
        $id_reserva = $_POST['id_reserva'];
        try {
            $pdo->beginTransaction();
            
            // Liberar asientos asignados
            $stmt = $pdo->prepare("
                UPDATE vuelo_asientos va
                JOIN asignacion_asiento aa ON va.id_vuelo_asiento = aa.id_vuelo_asiento
                SET va.disponible = 1
                WHERE aa.id_reserva = ?
            ");
            $stmt->execute([$id_reserva]);
            
            // Eliminar asignaciones de asientos
            $stmt = $pdo->prepare("DELETE FROM asignacion_asiento WHERE id_reserva = ?");
            $stmt->execute([$id_reserva]);
            
            // Eliminar pasajeros
            $stmt = $pdo->prepare("DELETE FROM pasajeros WHERE id_reserva = ?");
            $stmt->execute([$id_reserva]);
            
            // Eliminar reserva
            $stmt = $pdo->prepare("DELETE FROM reservas WHERE id_reserva = ?");
            $stmt->execute([$id_reserva]);
            
            $pdo->commit();
            $mensaje = "✅ Reserva cancelada exitosamente";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "❌ Error al cancelar la reserva: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['cancelar_vuelo_completo'])) {
        $id_vuelo = $_POST['id_vuelo'];
        try {
            $pdo->beginTransaction();
            
            // Obtener todas las reservas del vuelo
            $reservas = $pdo->prepare("
                SELECT id_reserva FROM reservas 
                WHERE id_vuelo_ida = ? OR id_vuelo_regreso = ?
            ");
            $reservas->execute([$id_vuelo, $id_vuelo]);
            $reservas_ids = $reservas->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($reservas_ids as $id_reserva) {
                // Liberar asientos
                $stmt = $pdo->prepare("
                    UPDATE vuelo_asientos va
                    JOIN asignacion_asiento aa ON va.id_vuelo_asiento = aa.id_vuelo_asiento
                    SET va.disponible = 1
                    WHERE aa.id_reserva = ?
                ");
                $stmt->execute([$id_reserva]);
                
                // Eliminar asignaciones
                $stmt = $pdo->prepare("DELETE FROM asignacion_asiento WHERE id_reserva = ?");
                $stmt->execute([$id_reserva]);
            }
            
            // Eliminar pasajeros de estas reservas
            $stmt = $pdo->prepare("DELETE FROM pasajeros WHERE id_reserva IN (" . 
                implode(',', array_fill(0, count($reservas_ids), '?')) . ")");
            $stmt->execute($reservas_ids);
            
            // Eliminar reservas
            $stmt = $pdo->prepare("DELETE FROM reservas WHERE id_vuelo_ida = ? OR id_vuelo_regreso = ?");
            $stmt->execute([$id_vuelo, $id_vuelo]);
            
            $pdo->commit();
            $mensaje = "✅ Vuelo completo cancelado exitosamente";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "❌ Error al cancelar el vuelo: " . $e->getMessage();
        }
    }
}

// Obtener vuelos con filtros
try {
    $sql_vuelos = "
        SELECT v.*, 
               COUNT(DISTINCT r.id_reserva) as total_reservas,
               COUNT(DISTINCT p.id_pasajero) as total_pasajeros
        FROM vuelos v
        LEFT JOIN reservas r ON v.id_vuelo = r.id_vuelo_ida OR v.id_vuelo = r.id_vuelo_regreso
        LEFT JOIN pasajeros p ON r.id_reserva = p.id_reserva
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($filtro_fecha) {
        $sql_vuelos .= " AND DATE(v.salida) = ?";
        $params[] = $filtro_fecha;
    }
    
    if ($filtro_origen) {
        $sql_vuelos .= " AND v.origen LIKE ?";
        $params[] = "%$filtro_origen%";
    }
    
    if ($filtro_destino) {
        $sql_vuelos .= " AND v.destino LIKE ?";
        $params[] = "%$filtro_destino%";
    }
    
    $sql_vuelos .= " GROUP BY v.id_vuelo ORDER BY v.salida DESC";
    
    $stmt = $pdo->prepare($sql_vuelos);
    $stmt->execute($params);
    $vuelos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_vuelos = "Error al cargar vuelos: " . $e->getMessage();
}

// Obtener detalles del vuelo seleccionado
$detalle_vuelo = null;
$pasajeros = [];
if ($vuelo_seleccionado) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM vuelos WHERE id_vuelo = ?");
        $stmt->execute([$vuelo_seleccionado]);
        $detalle_vuelo = $stmt->fetch();
        
        if ($detalle_vuelo) {
            $stmt = $pdo->prepare("
                SELECT r.folio, r.nombre_contacto, r.correo_contacto, r.telefono_contacto,
                       p.id_pasajero, p.nombre, p.apellidos, p.asiento_seleccionado,
                       cp.categoria,
                       v_origen.origen as origen_ida, v_destino.destino as destino_ida
                FROM reservas r
                JOIN pasajeros p ON r.id_reserva = p.id_reserva
                JOIN categorias_pasajero cp ON p.id_categoria = cp.id_categoria
                JOIN vuelos v_origen ON r.id_vuelo_ida = v_origen.id_vuelo
                LEFT JOIN vuelos v_destino ON r.id_vuelo_regreso = v_destino.id_vuelo
                WHERE r.id_vuelo_ida = ? OR r.id_vuelo_regreso = ?
                ORDER BY r.folio, p.id_pasajero
            ");
            $stmt->execute([$vuelo_seleccionado, $vuelo_seleccionado]);
            $pasajeros = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error_detalle = "Error al cargar detalles: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Cherry Airlines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            background: var(--vintage-light);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--vintage-dark), #4a3d41);
            color: var(--vintage-cream);
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-bottom: 3px solid var(--vintage-teal);
        }
        
        .filtros-card {
            background: var(--vintage-cream);
            border: 2px solid var(--vintage-teal);
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .vuelos-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .vuelo-card {
            background: white;
            border: 1px solid var(--vintage-teal);
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .vuelo-card:hover {
            border-color: var(--vintage-orange);
            box-shadow: 0 4px 12px rgba(92, 76, 81, 0.15);
        }
        
        .vuelo-card.active {
            border-color: var(--vintage-orange);
            background: rgba(243, 181, 98, 0.05);
        }
        
        .pasajeros-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(92, 76, 81, 0.1);
        }
        
        .btn-cancelar {
            background: var(--vintage-coral);
            border: none;
            color: white;
        }
        
        .btn-cancelar:hover {
            background: #e05555;
            color: white;
        }
        
        .estadisticas-card {
            background: linear-gradient(135deg, var(--vintage-teal), #7ba89d);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-6 fw-bold">
                            <svg width="32" height="32" fill="currentColor" class="me-2">
                                <path d="M8 256a8 8 0 1 1 16 0A8 8 0 0 1 8 256zm0-128a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm0 256a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm128-128a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm0-128a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm0 256a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm128-128a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm0-128a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm0 256a8 8 0 1 1 16 0 8 8 0 0 1-16 0z"/>
                            </svg>
                            Panel de Administración
                        </h1>
                        <p class="lead mb-0">Gestión de vuelos y pasajeros - Cherry Airlines</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="index.php" class="btn btn-outline-light">Volver al Sitio Principal</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php if ($mensaje): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?= $mensaje ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="filtros-card p-4">
                <h5 class="mb-3">Filtrar Vuelos</h5>
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha de Salida</label>
                        <input type="date" name="fecha" class="form-control" value="<?= $filtro_fecha ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Origen</label>
                        <input type="text" name="origen" class="form-control" placeholder="Ej: Ciudad de México" value="<?= $filtro_origen ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Destino</label>
                        <input type="text" name="destino" class="form-control" placeholder="Ej: Monterrey" value="<?= $filtro_destino ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Aplicar Filtros</button>
                    </div>
                </form>
                <?php if ($filtro_fecha || $filtro_origen || $filtro_destino): ?>
                    <div class="mt-3">
                        <a href="admin.php" class="btn btn-sm btn-outline-secondary">Limpiar Filtros</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <!-- Lista de Vuelos -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-vintage-dark text-white">
                            <h5 class="mb-0">Lista de Vuelos (<?= count($vuelos) ?>)</h5>
                        </div>
                        <div class="card-body vuelos-list">
                            <?php if (empty($vuelos)): ?>
                                <div class="text-center text-muted py-4">
                                    <p>No se encontraron vuelos con los filtros aplicados</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($vuelos as $vuelo): ?>
                                    <div class="vuelo-card p-3 <?= $vuelo_seleccionado == $vuelo['id_vuelo'] ? 'active' : '' ?>"
                                         onclick="window.location.href='admin.php?<?= http_build_query(array_merge($_GET, ['vuelo_id' => $vuelo['id_vuelo']])) ?>'">
                                        <div class="row align-items-center">
                                            <div class="col-8">
                                                <h6 class="mb-1"><?= $vuelo['origen'] ?> → <?= $vuelo['destino'] ?></h6>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($vuelo['salida'])) ?> - 
                                                    $<?= number_format($vuelo['precio'], 2) ?>
                                                </small>
                                            </div>
                                            <div class="col-4 text-end">
                                                <span class="badge bg-primary"><?= $vuelo['total_pasajeros'] ?> pasajeros</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Detalles del Vuelo Seleccionado -->
                <div class="col-lg-6">
                    <?php if ($detalle_vuelo): ?>
                        <div class="card">
                            <div class="card-header bg-vintage-teal text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Detalles del Vuelo</h5>
                                <form method="post" class="d-inline" onsubmit="return confirm('¿Está seguro de cancelar TODAS las reservas de este vuelo?')">
                                    <input type="hidden" name="id_vuelo" value="<?= $detalle_vuelo['id_vuelo'] ?>">
                                    <button type="submit" name="cancelar_vuelo_completo" class="btn btn-sm btn-cancelar">
                                        Cancelar Vuelo Completo
                                    </button>
                                </form>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <strong>Origen:</strong><br>
                                        <?= $detalle_vuelo['origen'] ?>
                                    </div>
                                    <div class="col-6">
                                        <strong>Destino:</strong><br>
                                        <?= $detalle_vuelo['destino'] ?>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <strong>Salida:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($detalle_vuelo['salida'])) ?>
                                    </div>
                                    <div class="col-6">
                                        <strong>Llegada:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($detalle_vuelo['llegada'])) ?>
                                    </div>
                                </div>
                                
                                <h6 class="mt-4 mb-3">Pasajeros (<?= count($pasajeros) ?>)</h6>
                                
                                <?php if (empty($pasajeros)): ?>
                                    <p class="text-muted">No hay pasajeros en este vuelo</p>
                                <?php else: ?>
                                    <div class="pasajeros-table">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Folio</th>
                                                        <th>Pasajero</th>
                                                        <th>Categoría</th>
                                                        <th>Asiento</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $folio_actual = '';
                                                    foreach ($pasajeros as $pasajero): 
                                                        if ($folio_actual != $pasajero['folio']):
                                                            $folio_actual = $pasajero['folio'];
                                                    ?>
                                                    <tr class="table-info">
                                                        <td colspan="5">
                                                            <strong>Folio: <?= $pasajero['folio'] ?></strong>
                                                            <small class="ms-3">Contacto: <?= $pasajero['nombre_contacto'] ?> (<?= $pasajero['correo_contacto'] ?>)</small>
                                                            <form method="post" class="d-inline float-end" onsubmit="return confirm('¿Cancelar toda esta reserva?')">
                                                                <input type="hidden" name="id_reserva" value="<?= $pasajero['id_reserva'] ?? '' ?>">
                                                                <button type="submit" name="cancelar_reserva" class="btn btn-sm btn-cancelar">
                                                                    Cancelar Reserva
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr>
                                                        <td><?= $pasajero['folio'] ?></td>
                                                        <td><?= $pasajero['nombre'] ?> <?= $pasajero['apellidos'] ?></td>
                                                        <td><?= $pasajero['categoria'] ?></td>
                                                        <td><?= $pasajero['asiento_seleccionado'] ?: 'No asignado' ?></td>
                                                        <td>
                                                            <small class="text-muted">Individual</small>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center text-muted py-5">
                                <svg width="48" height="48" fill="currentColor" class="mb-3">
                                    <path d="M8 256a8 8 0 1 1 16 0A8 8 0 0 1 8 256zm0-128a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm0 256a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm128-128a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm0-128a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm0 256a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm128-128a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm0-128a8 8 0 1 1 16 0 8 8 0 0 1-16 0zm0 256a8 8 0 1 1 16 0 8 8 0 0 1-16 0z"/>
                                </svg>
                                <p>Selecciona un vuelo para ver los detalles</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
