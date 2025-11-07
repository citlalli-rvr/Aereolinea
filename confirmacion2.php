<?php
// confirmacion.php - Página de confirmación y generación de boleto
session_start();
require_once '../config.php';

function esc($s){ 
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

$folio = $_GET['folio'] ?? '';

if (empty($folio)) {
    header("Location: index.php");
    exit;
}

try {
    // ✅ Obtener información completa de la reserva
    $stmt = $pdo->prepare("
        SELECT 
            r.folio,
            r.nombre_contacto,
            r.correo_contacto,
            r.telefono_contacto,
            r.fecha_reserva,
            v.id_vuelo,
            v.origen,
            v.destino,
            v.salida,
            v.llegada,
            v.precio
        FROM reservas r
        JOIN vuelos v ON r.id_vuelo_ida = v.id_vuelo
        WHERE r.folio = ?
    ");
    $stmt->execute([$folio]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        die("Reserva no encontrada.");
    }

    // ✅ Obtener pasajeros y sus asientos
    $stmt = $pdo->prepare("
        SELECT 
            p.nombre,
            p.apellidos,
            p.asiento_seleccionado,
            cp.categoria
        FROM pasajeros p
        JOIN categorias_pasajero cp ON p.id_categoria = cp.id_categoria
        WHERE p.id_reserva = (
            SELECT id_reserva FROM reservas WHERE folio = ?
        )
    ");
    $stmt->execute([$folio]);
    $pasajeros = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmación de Reserva - Cherry Airlines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">¡Reserva Confirmada!</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <h5>Reserva realizada exitosamente</h5>
                            <p class="mb-0">Folio: <strong><?= esc($reserva['folio']) ?></strong></p>
                        </div>

                        <!-- Boleto para cada pasajero -->
                        <?php foreach($pasajeros as $pasajero): ?>
                        <div class="ticket mb-4">
                            <div class="ticket-header">
                                <div class="row">
                                    <div class="col-6">
                                        <h3 class="mb-0">Cherry Airlines</h3>
                                        <small>Aerolínea de Confianza</small>
                                    </div>
                                    <div class="col-6 text-end">
                                        <h4 class="mb-0">BOLETO ELECTRÓNICO</h4>
                                        <small>Folio: <?= esc($reserva['folio']) ?></small>
                                    </div>
                                </div>
                            </div>

                            <div class="ticket-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Pasajero:</strong><br>
                                        <?= esc($pasajero['nombre'] . ' ' . $pasajero['apellidos']) ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Categoría:</strong><br>
                                        <?= esc($pasajero['categoria']) ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Vuelo:</strong><br>
                                        <?= esc($reserva['origen']) ?> → <?= esc($reserva['destino']) ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Asiento:</strong><br>
                                        <?= esc($pasajero['asiento_seleccionado']) ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Salida:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($reserva['salida'])) ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Llegada:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($reserva['llegada'])) ?>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12">
                                        <strong>Precio:</strong> $<?= number_format($reserva['precio'], 2) ?> MXN
                                    </div>
                                </div>
                            </div>

                            <div class="ticket-footer text-center mt-3">
                                <small>¡Gracias por volar con Cherry Airlines!</small>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Información de contacto -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Información de Contacto</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Contacto:</strong> <?= esc($reserva['nombre_contacto']) ?></p>
                                <p><strong>Correo:</strong> <?= esc($reserva['correo_contacto']) ?></p>
                                <p><strong>Teléfono:</strong> <?= esc($reserva['telefono_contacto']) ?></p>
                                <p><strong>Fecha de reserva:</strong> <?= date('d/m/Y H:i', strtotime($reserva['fecha_reserva'])) ?></p>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end no-print">
                            <button onclick="window.print()" class="btn btn-primary">
                                📄 Imprimir Boletos
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                🏠 Volver al Inicio
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll para impresión
        window.onbeforeprint = function() {
            window.scrollTo(0, 0);
        };
    </script>
</body>

</html>

