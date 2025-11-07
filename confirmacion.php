<?php
session_start();
require_once 'config.php';

function esc($s){ 
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

$folio = $_GET['folio'] ?? '';
$error = '';
$reserva = null;
$pasajeros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $_POST['correo'] ?? '';
    $telefono = $_POST['telefono'] ?? '';

    if (empty($correo) && empty($telefono)) {
        $error = "Debe ingresar correo electrónico o teléfono.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM reservas 
                WHERE folio = ? AND (correo_contacto = ? OR telefono_contacto = ?)
            ");
            $stmt->execute([$folio, $correo, $telefono]);
            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($reserva) {
                $stmt = $pdo->prepare("SELECT * FROM vuelos WHERE id_vuelo = ?");
                $stmt->execute([$reserva['id_vuelo_ida']]);
                $vuelo_ida = $stmt->fetch(PDO::FETCH_ASSOC);

                $vuelo_regreso = null;
                if (!empty($reserva['id_vuelo_regreso'])) {
                    $stmt = $pdo->prepare("SELECT * FROM vuelos WHERE id_vuelo = ?");
                    $stmt->execute([$reserva['id_vuelo_regreso']]);
                    $vuelo_regreso = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                $stmt = $pdo->prepare("
                    SELECT p.nombre, p.apellidos, p.asiento_seleccionado, cp.categoria
                    FROM pasajeros p
                    JOIN categorias_pasajero cp ON p.id_categoria = cp.id_categoria
                    WHERE p.id_reserva = ?
                ");
                $stmt->execute([$reserva['id_reserva']]);
                $pasajeros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $error = "No se encontró una reserva con ese folio y contacto.";
            }

        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmar Información - Cherry Airlines</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-cherry mb-5">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <div class="brand-logo">
                <i class="bi bi-airplane-fill"></i>
            </div>
            Cherry Airlines
        </a>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- FORMULARIO DE CONFIRMACIÓN -->
            <?php if (!$reserva): ?>
            <div class="booking-card shadow mb-4">
                <h4 class="mb-3 text-center">
                    <i class="bi bi-search"></i> Confirmar mi Reserva
                </h4>
                <form method="POST">
                    <p class="text-muted mb-3 text-center">
                        Ingrese <strong>correo electrónico</strong> o <strong>teléfono</strong> para confirmar el folio <strong><?= esc($folio) ?></strong>.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="correo" class="form-control" placeholder="usuario@correo.com">
                    </div>
                    <div class="text-center my-2"><strong>o</strong></div>
                    <div class="mb-3">
                        <label class="form-label">Número telefónico</label>
                        <input type="text" name="telefono" class="form-control" placeholder="Ejemplo: 3121234567">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">
                        <i class="bi bi-check-circle"></i> Confirmar Reserva
                    </button>
                </form>
                <?php if ($error): ?>
                    <div class="alert alert-danger mt-3">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?= esc($error) ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- BOLETOS CONFIRMADOS -->
            <?php if ($reserva): ?>
                <div class="alert alert-success">
                    <h5>Reserva confirmada</h5>
                    <p>Folio: <strong><?= esc($reserva['folio']) ?></strong></p>
                </div>

                <div class="printable">
                    <?php foreach($pasajeros as $pasajero): ?>
                    <div class="ticket">
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
                                    <?= esc($vuelo_ida['origen']) ?> → <?= esc($vuelo_ida['destino']) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Asiento:</strong><br>
                                    <?= esc($pasajero['asiento_seleccionado']) ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Salida:</strong><br>
                                    <?= date('d/m/Y H:i', strtotime($vuelo_ida['salida'])) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Llegada:</strong><br>
                                    <?= date('d/m/Y H:i', strtotime($vuelo_ida['llegada'])) ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <strong>Precio:</strong> $<?= number_format($vuelo_ida['precio'], 2) ?> MXN
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <small>¡Gracias por volar con Cherry Airlines!</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- INFORMACIÓN DE CONTACTO -->
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

                <!-- BOTONES -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end no-print">
                    <button onclick="window.print()" class="btn btn-primary">📄 Imprimir Boletos</button>
                    <a href="index.php" class="btn btn-secondary">🏠 Volver al Inicio</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer class="footer-cherry mt-5 text-center">
    <p>&copy; <?= date("Y") ?> Cherry Airlines — Elegancia en el aire ✈️</p>
</footer>

<!-- SCRIPT DE IMPRESIÓN -->
<script>
    window.onbeforeprint = function() {
        window.scrollTo(0, 0);
    };
</script>

</body>
</html>
